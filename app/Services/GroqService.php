<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class GroqService
{
    /**
     * @return array{raw: array<string, mixed>, text: string, model: string}
     */
    public function generateContent(string $prompt, array $options = []): array
    {
        $apiKey = (string) config('services.groq.api_key');
        $baseUrl = rtrim((string) config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/');
        $model = $this->resolveModel($options['model'] ?? null);

        if ($apiKey === '') {
            throw new ApiException('GROQ_API_KEY is missing', 500);
        }

        try {
            $response = Http::timeout(30)
                ->withToken($apiKey)
                ->post("{$baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Bạn là trợ lý AI nội bộ cho hệ thống quản lý vận tải Company Ship / CETA. Trả lời bằng tiếng Việt, ngắn gọn, không bịa số liệu.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => (float) Arr::get($options, 'generation_config.temperature', 0.2),
                    'top_p' => (float) Arr::get($options, 'generation_config.topP', 0.8),
                    'max_tokens' => (int) Arr::get($options, 'generation_config.maxOutputTokens', 512),
                ]);
        } catch (ConnectionException) {
            throw new ApiException('Groq API timeout: không nhận được phản hồi sau 30 giây', 504);
        }

        if ($response->failed()) {
            $status = $response->status();
            $errorBody = $response->json() ?? [];
            $message = (string) Arr::get($errorBody, 'error.message', 'Groq API request failed');

            // Map common Groq quota/rate-limit status codes to descriptive messages
            if ($status === 429) {
                $message = 'Groq API quota exceeded — vui lòng thử lại sau';
            } elseif ($status === 503) {
                $message = 'Groq API tạm thời không khả dụng — vui lòng thử lại sau';
            }

            throw new ApiException($message, $status >= 400 && $status <= 599 ? $status : 502, [
                'requested_model' => $options['model'] ?? null,
                'resolved_model' => $model,
                'status' => $status,
                'body' => $errorBody,
            ]);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        return [
            'raw' => $payload,
            'text' => (string) Arr::get($payload, 'choices.0.message.content', ''),
            'model' => $model,
        ];
    }

    private function resolveModel(mixed $requestedModel): string
    {
        $model = is_string($requestedModel) ? trim($requestedModel) : '';

        if ($model === '' || str_contains($model, 'gemini')) {
            return (string) config('services.groq.model', 'openai/gpt-oss-20b');
        }

        return $model;
    }
}
