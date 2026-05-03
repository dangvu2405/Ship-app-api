<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RagIndex;

/**
 * Hybrid retrieval over rag_index:
 *   1. MySQL FULLTEXT search → up to 40 candidates
 *   2. Cosine re-ranking against query embedding (when Ollama is up)
 *   3. RRF score merge when both signals are available
 *
 * Falls back gracefully to keyword-only when Ollama is unavailable.
 */
class RetrievalService
{
    private const CANDIDATE_LIMIT = 40;

    public function __construct(
        private readonly EmbeddingService $embedder,
    ) {}

    /**
     * @param  array<string, mixed>  $filters  e.g. ['company_id' => 1, 'source_table' => 'trips']
     * @return list<array{source: string, content: string, metadata: array, score: float}>
     */
    public function hybridSearch(string $query, array $filters = [], int $topK = 8): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $queryEmbedding = $this->embedder->embed($query);

        $keywordResults = $this->keywordSearch($query, $filters);
        $vectorResults = $queryEmbedding !== null
            ? $this->vectorRerank($keywordResults, $queryEmbedding)
            : [];

        $merged = $this->rrfMerge($keywordResults, $vectorResults, $topK);

        return $merged;
    }

    /**
     * MySQL FULLTEXT search (+ LIKE fallback for short queries).
     *
     * @param  array<string, mixed>  $filters
     * @return list<array{id: int, content: string, metadata: array, source_table: string, source_id: int, score: float}>
     */
    private function keywordSearch(string $query, array $filters): array
    {
        $base = RagIndex::query();

        // Apply tenant / table filters
        if (isset($filters['company_id'])) {
            $base->where('company_id', $filters['company_id']);
        }
        if (isset($filters['source_table'])) {
            $base->where('source_table', $filters['source_table']);
        }

        if (mb_strlen($query) >= 4) {
            $safeQuery = $this->sanitizeFtQuery($query);
            if ($safeQuery !== '') {
                $rows = (clone $base)
                    ->selectRaw('id, source_table, source_id, content, metadata, MATCH(content) AGAINST(? IN NATURAL LANGUAGE MODE) AS ft_score', [$safeQuery])
                    ->whereRaw('MATCH(content) AGAINST(? IN NATURAL LANGUAGE MODE)', [$safeQuery])
                    ->orderByDesc('ft_score')
                    ->limit(self::CANDIDATE_LIMIT)
                    ->get();

                if ($rows->isNotEmpty()) {
                    return $rows->map(fn ($r) => [
                        'id' => (int) $r->id,
                        'source_table' => $r->source_table,
                        'source_id' => (int) $r->source_id,
                        'content' => $r->content,
                        'metadata' => is_array($r->metadata) ? $r->metadata : (json_decode((string) $r->metadata, true) ?? []),
                        'score' => (float) ($r->ft_score ?? 0),
                    ])->values()->all();
                }
            }
        }

        // LIKE fallback for short queries or FULLTEXT misses
        $words = collect(explode(' ', mb_strtolower($query)))
            ->map(fn (string $w) => trim($w))
            ->filter(fn (string $w) => mb_strlen($w) >= 2)
            ->unique()
            ->take(4)
            ->values();

        if ($words->isEmpty()) {
            return [];
        }

        $likeBase = (clone $base)->where(function ($q) use ($words): void {
            foreach ($words as $word) {
                $q->orWhere('content', 'LIKE', '%'.$word.'%');
            }
        })->limit(self::CANDIDATE_LIMIT)->get(['id', 'source_table', 'source_id', 'content', 'metadata']);

        return $likeBase->map(fn ($r) => [
            'id' => (int) $r->id,
            'source_table' => $r->source_table,
            'source_id' => (int) $r->source_id,
            'content' => $r->content,
            'metadata' => is_array($r->metadata) ? $r->metadata : (json_decode((string) $r->metadata, true) ?? []),
            'score' => 0.1,   // flat score for LIKE matches
        ])->values()->all();
    }

    /**
     * Re-rank keyword candidates by cosine similarity to the query embedding.
     *
     * @param  list<array{id: int, ...}>  $candidates
     * @param  float[]  $queryEmbedding
     * @return list<array{id: int, content: string, metadata: array, source_table: string, source_id: int, score: float}>
     */
    private function vectorRerank(array $candidates, array $queryEmbedding): array
    {
        if (empty($candidates)) {
            return [];
        }

        $ids = array_column($candidates, 'id');

        // Fetch stored embeddings for these candidates
        $embeddingRows = RagIndex::query()
            ->whereIn('id', $ids)
            ->whereNotNull('embedding')
            ->get(['id', 'embedding'])
            ->keyBy('id');

        $ranked = [];
        foreach ($candidates as $candidate) {
            $row = $embeddingRows->get($candidate['id']);
            if ($row === null) {
                continue;
            }

            /** @var float[]|null $storedEmb */
            $storedEmb = is_array($row->embedding) ? $row->embedding : null;
            if ($storedEmb === null) {
                continue;
            }

            $similarity = EmbeddingService::cosineSimilarity($queryEmbedding, $storedEmb);
            $ranked[] = array_merge($candidate, ['score' => $similarity]);
        }

        usort($ranked, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_values($ranked);
    }

    /**
     * Reciprocal Rank Fusion — merges two ranked lists into one.
     *
     * @param  list<array{id: int, ...}>  $listA  keyword results
     * @param  list<array{id: int, ...}>  $listB  vector results (may be empty)
     * @return list<array{source: string, content: string, metadata: array, score: float}>
     */
    private function rrfMerge(array $listA, array $listB, int $topK): array
    {
        $k = 60;  // standard RRF constant
        $scores = [];
        $byId = [];

        foreach ($listA as $rank => $item) {
            $id = $item['id'];
            $scores[$id] = ($scores[$id] ?? 0.0) + 1.0 / ($k + $rank + 1);
            $byId[$id] = $item;
        }
        foreach ($listB as $rank => $item) {
            $id = $item['id'];
            $scores[$id] = ($scores[$id] ?? 0.0) + 1.0 / ($k + $rank + 1);
            $byId[$id] = $item;
        }

        arsort($scores);

        $result = [];
        $count = 0;
        foreach ($scores as $id => $score) {
            if ($count >= $topK) {
                break;
            }
            $item = $byId[$id];
            $result[] = [
                'source' => "{$item['source_table']}#{$item['source_id']}",
                'content' => $item['content'],
                'metadata' => $item['metadata'],
                'score' => round($score, 6),
            ];
            $count++;
        }

        return $result;
    }

    private function sanitizeFtQuery(string $query): string
    {
        $cleaned = preg_replace('/[+\-><()~*"@]+/', ' ', $query) ?? $query;
        $cleaned = trim((string) preg_replace('/\s+/', ' ', $cleaned));

        $hasLongWord = collect(explode(' ', $cleaned))
            ->filter(fn (string $w): bool => mb_strlen($w) >= 3)
            ->isNotEmpty();

        return $hasLongWord ? $cleaned : '';
    }
}
