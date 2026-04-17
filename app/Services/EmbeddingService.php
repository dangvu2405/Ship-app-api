<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates text embeddings via a local Ollama server (bge-m3 model).
 *
 * If Ollama is unreachable, every method returns NULL — callers must
 * handle graceful degradation (keyword-only fallback).
 *
 * Setup:
 *   curl -fsSL https://ollama.com/install.sh | sh
 *   ollama pull bge-m3
 *   ollama serve          # listens on :11434 by default
 *
 * Env:
 *   OLLAMA_URL=http://localhost:11434
 *   OLLAMA_EMBEDDING_MODEL=bge-m3
 */
class EmbeddingService
{
    private readonly string $baseUrl;
    private readonly string $model;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.ollama.url', 'http://localhost:11434'), '/');
        $this->model   = (string) config('services.ollama.model', 'bge-m3');
    }

    /**
     * Embed a single piece of text.
     *
     * @return float[]|null  1024-dim vector, or NULL on failure
     */
    public function embed(string $text): ?array
    {
        if (trim($text) === '') {
            return null;
        }

        try {
            $response = Http::timeout(30)
                ->post("{$this->baseUrl}/api/embeddings", [
                    'model'  => $this->model,
                    'prompt' => $text,
                ]);

            if ($response->failed()) {
                Log::warning('EmbeddingService: Ollama returned error', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            /** @var mixed $embedding */
            $embedding = $response->json('embedding');

            return is_array($embedding) ? array_map('floatval', $embedding) : null;
        } catch (Throwable $e) {
            Log::warning('EmbeddingService: Ollama unreachable', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Embed multiple texts.  Ollama has no native batch endpoint, so we
     * send requests sequentially (acceptable for indexing jobs).
     *
     * @param  string[] $texts
     * @return array<int, float[]|null>
     */
    public function embedBatch(array $texts): array
    {
        return array_map(fn (string $t): ?array => $this->embed($t), $texts);
    }

    /**
     * Cosine similarity between two equal-length vectors.
     *
     * @param float[] $a
     * @param float[] $b
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $dot  = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dot   += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $denom = sqrt($normA) * sqrt($normB);

        return $denom > 0.0 ? $dot / $denom : 0.0;
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(3)->get("{$this->baseUrl}/api/tags");

            return $response->successful();
        } catch (Throwable) {
            return false;
        }
    }
}
