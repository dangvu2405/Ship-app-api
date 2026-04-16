<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class GeminiService
{
    /**
     * @return array<string, mixed>
     */
    public function generateContent(string $prompt, array $options = []): array
    {
        $apiKey = (string) config('services.gemini.api_key');
        $requestedModel = (string) ($options['model'] ?? config('services.gemini.model', 'gemini-2.0-flash'));
        $model = $requestedModel;
        $baseUrl = rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');

        if ($apiKey === '') {
            throw new ApiException('GEMINI_API_KEY / GROQ_API_KEY is missing', 500);
        }

        if ($this->isOpenAiCompatibleBaseUrl($baseUrl)) {
            return $this->generateWithOpenAiCompatibleApi(
                prompt: $prompt,
                apiKey: $apiKey,
                model: $model,
                baseUrl: $baseUrl
            );
        }

        try {
            return $this->generateWithGeminiApi(
                prompt: $prompt,
                options: $options,
                apiKey: $apiKey,
                requestedModel: $requestedModel,
                model: $model,
                baseUrl: $baseUrl
            );
        } catch (ApiException $exception) {
            if ($exception->getStatusCode() !== 429) {
                throw $exception;
            }

            $groqApiKey = (string) config('services.groq.api_key');
            $groqModel = (string) config('services.groq.model', 'openai/gpt-oss-20b');
            $groqBaseUrl = rtrim((string) config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/');

            if ($groqApiKey === '' || $groqBaseUrl === '') {
                throw $exception;
            }

            return $this->generateWithOpenAiCompatibleApi(
                prompt: $prompt,
                apiKey: $groqApiKey,
                model: $groqModel,
                baseUrl: $groqBaseUrl
            );
        }
    }

    private function isOpenAiCompatibleBaseUrl(string $baseUrl): bool
    {
        return str_contains($baseUrl, '/openai/');
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function generateWithGeminiApi(
        string $prompt,
        array $options,
        string $apiKey,
        string $requestedModel,
        string $model,
        string $baseUrl
    ): array {
        $endpoint = sprintf('%s/models/%s:generateContent', $baseUrl, $model);

        $payload = [
            'contents' => [[
                'parts' => [[
                    'text' => $prompt,
                ]],
            ]],
        ];

        if (isset($options['system_instruction']) && is_string($options['system_instruction']) && $options['system_instruction'] !== '') {
            $payload['systemInstruction'] = [
                'parts' => [[
                    'text' => $options['system_instruction'],
                ]],
            ];
        }

        if (isset($options['generation_config']) && is_array($options['generation_config'])) {
            $payload['generationConfig'] = $options['generation_config'];
        }

        $response = Http::timeout(30)
            ->withQueryParameters(['key' => $apiKey])
            ->post($endpoint, $payload);

        if ($response->failed()) {
            $status = $response->status();
            $errorBody = $response->json() ?? [];
            $message = (string) Arr::get($errorBody, 'error.message', 'Gemini API request failed');

            throw new ApiException($message, $status >= 400 && $status <= 599 ? $status : 502, [
                'requested_model' => $requestedModel,
                'resolved_model' => $model,
                'status' => $status,
                'body' => $errorBody,
            ]);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        return [
            'raw' => $payload,
            'text' => Arr::get($payload, 'candidates.0.content.parts.0.text', ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function generateWithOpenAiCompatibleApi(
        string $prompt,
        string $apiKey,
        string $model,
        string $baseUrl
    ): array {
        $endpoint = sprintf('%s/responses', $baseUrl);

        $payload = [
            'model' => $model,
            'input' => $prompt,
        ];

        $response = Http::timeout(30)
            ->withToken($apiKey)
            ->post($endpoint, $payload);

        if ($response->failed()) {
            $status = $response->status();
            $errorBody = $response->json() ?? [];
            $message = (string) Arr::get($errorBody, 'error.message', 'OpenAI-compatible API request failed');

            throw new ApiException($message, $status >= 400 && $status <= 599 ? $status : 502, [
                'resolved_model' => $model,
                'status' => $status,
                'body' => $errorBody,
            ]);
        }

        /** @var array<string, mixed> $decoded */
        $decoded = $response->json() ?? [];
        $text = (string) (Arr::get($decoded, 'output_text', '') ?: Arr::get($decoded, 'output.0.content.0.text', ''));

        return [
            'raw' => $decoded,
            'text' => $text,
        ];
    }
}
