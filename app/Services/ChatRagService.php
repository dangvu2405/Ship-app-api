<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\KnowledgeArticle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class ChatRagService
{
    private const SNIPPET_CHARS = 500;

    private static ?bool $hasTenantPriorityColumn = null;

    /**
     * Tìm tài liệu nghiệp vụ liên quan đến câu hỏi.
     *
     * @return list<array{title: string, snippet: string, category: string, score: float}>
     */
    public function search(string $query, string $intent, ?int $companyId = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        if (! Schema::hasTable('knowledge_articles')) {
            return [];
        }

        // Thử FULLTEXT trước (MySQL/MariaDB; SQLite dùng keywordSearch)
        if (mb_strlen($query) >= 6 && $this->databaseSupportsFullText()) {
            $results = $this->fullTextSearch($query, $intent, $companyId);
            if ($results->count() >= 1) {
                return $this->formatDocs($results, $query);
            }
        }

        // Fallback: LIKE search theo từ khoá + lọc theo category
        $results = $this->keywordSearch($query, $intent, $companyId);

        return $this->formatDocs($results, $query);
    }

    private function databaseSupportsFullText(): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        return in_array($driver, ['mysql', 'mariadb'], true);
    }

    private function fullTextSearch(string $query, string $intent, ?int $companyId): Collection
    {
        $safeQuery = $this->sanitizeFullTextQuery($query);
        if ($safeQuery === '') {
            return collect();
        }

        $base = KnowledgeArticle::query()
            ->visibleTo($companyId)
            ->selectRaw('*, MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE) AS score', [$safeQuery])
            ->whereRaw('MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE)', [$safeQuery]);

        $this->applyTenantPriorityOrdering($base, $companyId);
        $base->orderByDesc('score');

        // Ưu tiên bài cùng intent, nhưng không loại bỏ các bài intent khác
        if ($intent !== 'GENERAL') {
            $base->orderByRaw('CASE WHEN category = ? THEN 0 ELSE 1 END', [$intent]);
        }

        return $base->limit($this->maxDocs())->get();
    }

    private function keywordSearch(string $query, string $intent, ?int $companyId): Collection
    {
        $keywords = collect(explode(' ', mb_strtolower($query)))
            ->map(fn (string $w): string => trim($w))
            ->filter(fn (string $w): bool => mb_strlen($w) >= 2)
            ->unique()
            ->take(5)
            ->values();

        if ($keywords->isEmpty()) {
            // Không có từ khoá → trả về bài thuộc intent
            $query = KnowledgeArticle::query()
                ->visibleTo($companyId)
                ->where('category', $intent !== 'GENERAL' ? $intent : 'GENERAL');

            $this->applyTenantPriorityOrdering($query, $companyId);

            return $query
                ->limit($this->maxDocs())
                ->get();
        }

        $dbQuery = KnowledgeArticle::query()->visibleTo($companyId);
        $this->applyTenantPriorityOrdering($dbQuery, $companyId);

        // Ưu tiên bài cùng category
        if ($intent !== 'GENERAL') {
            $dbQuery->orderByRaw('CASE WHEN category = ? THEN 0 ELSE 1 END', [$intent]);
        }

        $dbQuery->where(function ($q) use ($keywords): void {
            foreach ($keywords as $word) {
                $q->orWhere('title', 'LIKE', '%'.$word.'%')
                    ->orWhere('content', 'LIKE', '%'.$word.'%');
            }
        });

        return $dbQuery->limit($this->maxDocs())->get();
    }

    private function maxDocs(): int
    {
        return max(1, min((int) config('services.rag.top_k', 3), 10));
    }

    private function applyTenantPriorityOrdering(Builder $query, ?int $companyId): void
    {
        if ($companyId !== null) {
            $query->orderByRaw('CASE WHEN company_id = ? THEN 0 WHEN company_id IS NULL THEN 1 ELSE 2 END', [$companyId]);
        } else {
            $query->orderByRaw('CASE WHEN company_id IS NULL THEN 0 ELSE 1 END');
        }

        if ($this->hasTenantPriorityColumn()) {
            $query->orderByDesc('tenant_priority');
        }
    }

    private function hasTenantPriorityColumn(): bool
    {
        if (self::$hasTenantPriorityColumn !== null) {
            return self::$hasTenantPriorityColumn;
        }

        self::$hasTenantPriorityColumn = Schema::hasColumn('knowledge_articles', 'tenant_priority');

        return self::$hasTenantPriorityColumn;
    }

    /**
     * @param  Collection<int, KnowledgeArticle>  $articles
     * @return list<array{title: string, snippet: string, category: string, score: float}>
     */
    private function formatDocs(Collection $articles, string $query): array
    {
        $keywords = collect(explode(' ', mb_strtolower($query)))
            ->map(fn (string $w): string => trim($w))
            ->filter(fn (string $w): bool => mb_strlen($w) >= 2)
            ->unique()
            ->values();

        return $articles->map(function (KnowledgeArticle $article) use ($keywords): array {
            $content = $article->content;
            $snippet = mb_strlen($content) > self::SNIPPET_CHARS
                ? mb_substr($content, 0, self::SNIPPET_CHARS).'...'
                : $content;

            $score = is_numeric($article->getAttribute('score'))
                ? (float) $article->getAttribute('score')
                : $this->keywordScore($article, $keywords);

            return [
                'title' => $article->title,
                'snippet' => $snippet,
                'category' => $article->category,
                'score' => round(min(1.0, max(0.1, $score)), 2),
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, string>  $keywords
     */
    private function keywordScore(KnowledgeArticle $article, Collection $keywords): float
    {
        if ($keywords->isEmpty()) {
            return 0.5;
        }

        $haystack = mb_strtolower($article->title.' '.$article->content.' '.implode(' ', $article->tags ?? []));
        $matches = $keywords->filter(fn (string $keyword): bool => str_contains($haystack, $keyword))->count();

        return $matches / max(1, $keywords->count());
    }

    private function sanitizeFullTextQuery(string $query): string
    {
        // Loại bỏ ký tự đặc biệt của MySQL FULLTEXT
        $cleaned = preg_replace('/[+\-><()~*"@]+/', ' ', $query) ?? $query;
        $cleaned = trim((string) preg_replace('/\s+/', ' ', $cleaned));

        // MySQL FULLTEXT cần ít nhất 1 từ có ≥ 3 ký tự
        $hasLongWord = collect(explode(' ', $cleaned))
            ->filter(fn (string $w): bool => mb_strlen($w) >= 3)
            ->isNotEmpty();

        return $hasLongWord ? $cleaned : '';
    }
}
