<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class GeminiService
{
    /**
     * @var array<string, string>
     */
    private array $legacyModelAliases = [
        'gemini-1.5-flash' => 'gemini-2.0-flash',
    ];

    /**
     * @return array<string, mixed>
     */
    public function generateContent(string $prompt, array $options = []): array
    {
        $apiKey = (string) config('services.gemini.api_key');
        $requestedModel = (string) ($options['model'] ?? config('services.gemini.model', 'gemini-2.0-flash'));
        $model = $this->legacyModelAliases[$requestedModel] ?? $requestedModel;
        $baseUrl = rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');

        if ($apiKey === '') {
            throw new ApiException('GEMINI_API_KEY is missing', 500);
        }

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
}
