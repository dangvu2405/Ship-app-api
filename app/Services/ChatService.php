<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\ChatMessage;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class ChatService
{
    public function __construct(
        private readonly GeminiService $geminiService,
        private readonly ChatPromptService $chatPromptService,
        private readonly ChatDataService $chatDataService,
        private readonly ChatRagService $chatRagService,
        private readonly TenantContext $tenantContext,
        private readonly RagAgentService $ragAgentService,
    ) {}

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function send(User $user, array $payload): array
    {
        $sessionId = (string) ($payload['session_id'] ?? Str::uuid()->toString());
        $message = trim((string) ($payload['message'] ?? ''));
        $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        $task = (string) ($payload['task'] ?? Arr::get($context, 'task', 'chat'));
        $resolvedModel = (string) ($payload['model'] ?? config('services.gemini.model', 'gemini-2.0-flash'));

        if ($message === '') {
            throw new ApiException('Message is required', 422);
        }

        if (mb_strlen($message) < 3) {
            $chat = ChatMessage::create([
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'message' => $message,
                'response' => 'Vui lòng nhập chi tiết hơn.',
                'context' => $context,
                'model' => 'local-guard',
                'status' => 'success',
            ]);

            return [
                'session_id' => $sessionId,
                'message' => $chat,
                'response_text' => (string) $chat->response,
                'cached' => false,
                'guarded' => true,
            ];
        }

        $cacheKey = $this->buildCacheKey($user->id, (int) $this->tenantContext->getCompanyId(), $message, $task, $context, $resolvedModel);
        $cachedText = Cache::get($cacheKey);
        if (is_string($cachedText) && $cachedText !== '') {
            $chat = ChatMessage::create([
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'message' => $message,
                'response' => $cachedText,
                'context' => $context,
                'model' => $resolvedModel,
                'status' => 'success',
            ]);

            return [
                'session_id' => $sessionId,
                'message' => $chat,
                'response_text' => (string) $chat->response,
                'cached' => true,
                'guarded' => false,
            ];
        }

        // ── RAG Agent path (opt-in via RAG_AGENT_ENABLED=true) ───────────────
        if ((bool) config('services.rag.agent_enabled', false) && $task === 'chat') {
            return $this->handleWithRagAgent($user, $sessionId, $message, $context, $resolvedModel, $cacheKey);
        }
        // ─────────────────────────────────────────────────────────────────────

        $context = $this->chatDataService->resolve($user, $message, $task, $context);

        $intent = (string) ($context['_intent'] ?? 'GENERAL');
        unset($context['_intent']);
        unset($context['_user_message']);

        $ragDocs = $this->chatRagService->search($message, $intent, $this->tenantContext->getCompanyId());

        $history = ChatMessage::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->where('status', 'success')
            ->whereNotNull('response')
            ->orderBy('id', 'desc')
            ->limit(16)
            ->get()
            ->reverse()
            ->values();

        $missingContextQuestion = $this->chatPromptService->detectMissingContext($task, $context);
        $useStructuredStatusMode = $this->shouldUseStructuredStatusMode($task, $intent, $message, $missingContextQuestion);
        if ($missingContextQuestion !== null && ! $useStructuredStatusMode) {
            $chat = ChatMessage::create([
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'message' => $message,
                'response' => $missingContextQuestion,
                'context' => $context,
                'model' => 'local-missing-context',
                'status' => 'success',
            ]);

            return [
                'session_id' => $sessionId,
                'message' => $chat,
                'response_text' => (string) $chat->response,
                'cached' => false,
                'guarded' => true,
            ];
        }

        $prompt = $this->chatPromptService->build(
            history: $history,
            message: $message,
            context: $context,
            task: $task,
            docs: $ragDocs,
            metadata: [
                'task' => $task,
                'intent' => $intent,
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'rag_docs_count' => count($ragDocs),
            ],
        );

        try {
            if ($useStructuredStatusMode) {
                $responseText = $this->generateStructuredStatusResponse(
                    prompt: $prompt,
                    message: $message,
                    task: $task,
                    intent: $intent,
                    missingContextQuestion: $missingContextQuestion,
                    model: $payload['model'] ?? null,
                );
            } else {
                $result = $this->geminiService->generateContent($prompt, [
                    'model' => $payload['model'] ?? null,
                    'generation_config' => [
                        'temperature' => 0.2,
                        'topP' => 0.8,
                        'maxOutputTokens' => 400,
                    ],
                ]);

                $responseText = trim((string) ($result['text'] ?? ''));
            }

            Cache::put($cacheKey, $responseText, now()->addMinutes(5));

            $chat = ChatMessage::create([
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'message' => $message,
                'response' => $responseText,
                'context' => $context,
                'model' => $resolvedModel,
                'status' => 'success',
            ]);
        } catch (ApiException $e) {
            if ($e->getStatusCode() === 429) {
                $fallback = 'Xin lỗi, hệ thống đang bận. Vui lòng thử lại sau ít phút.';
                $chat = ChatMessage::create([
                    'user_id' => $user->id,
                    'session_id' => $sessionId,
                    'message' => $message,
                    'response' => $fallback,
                    'context' => $context,
                    'model' => 'local-fallback-429',
                    'status' => 'success',
                ]);

                return [
                    'session_id' => $sessionId,
                    'message' => $chat,
                    'response_text' => (string) $chat->response,
                    'cached' => false,
                    'guarded' => true,
                ];
            }

            $chat = ChatMessage::create([
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'message' => $message,
                'context' => $context,
                'model' => $resolvedModel,
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);

            $errors = $e->getErrors();
            $mergedErrors = [
                'chat_message_id' => $chat->id,
            ];

            if (is_array($errors)) {
                $mergedErrors = array_merge($mergedErrors, $errors);
            }

            throw new ApiException($e->getMessage(), $e->getStatusCode(), $mergedErrors);
        } catch (Throwable $e) {
            $chat = ChatMessage::create([
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'message' => $message,
                'context' => $context,
                'model' => $resolvedModel,
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);

            throw new ApiException('Chat generation failed', 502, [
                'chat_message_id' => $chat->id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'session_id' => $sessionId,
            'message' => $chat,
            'response_text' => (string) $chat->response,
            'cached' => false,
            'guarded' => false,
        ];
    }

    /**
     * Execute the two-tool RAG agent and return the standard response shape.
     *
     * @param  array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function handleWithRagAgent(
        User $user,
        string $sessionId,
        string $message,
        array $context,
        string $resolvedModel,
        string $cacheKey,
    ): array {
        $companyId = $this->tenantContext->getCompanyId();

        $agentResult = $this->ragAgentService->ask($message, $companyId);
        $responseText = trim($agentResult['answer']);

        if ($responseText === '') {
            $responseText = 'Xin lỗi, không thể xử lý câu hỏi này.';
        }

        Cache::put($cacheKey, $responseText, now()->addMinutes(5));

        $chat = ChatMessage::create([
            'user_id'    => $user->id,
            'session_id' => $sessionId,
            'message'    => $message,
            'response'   => $responseText,
            'context'    => array_merge($context, ['_agent_trace' => $agentResult['trace']]),
            'model'      => 'rag-agent/'.$resolvedModel,
            'status'     => 'success',
        ]);

        return [
            'session_id'    => $sessionId,
            'message'       => $chat,
            'response_text' => (string) $chat->response,
            'cached'        => false,
            'guarded'       => false,
        ];
    }

    private function shouldUseStructuredStatusMode(string $task, string $intent, string $message, ?string $missingContextQuestion): bool
    {
        if ($missingContextQuestion !== null) {
            return true;
        }

        if (in_array(strtoupper($task), ['CHECK_STATUS', 'STATUS_CHECK'], true)) {
            return true;
        }

        $statusIntents = ['COMPLIANCE', 'VIOLATION'];
        if (in_array(strtoupper($intent), $statusIntents, true) && preg_match('/\b(trạng thái|status|kiểm tra)\b/ui', $message) === 1) {
            return true;
        }

        return preg_match('/\b(thiếu dữ liệu|chưa đủ dữ liệu|kiểm tra trạng thái|status check|data status)\b/ui', $message) === 1;
    }

    /**
     * @param array{system: string, user: string, turns: list<array{role: string, text: string}>} $prompt
     */
    private function generateStructuredStatusResponse(
        array $prompt,
        string $message,
        string $task,
        string $intent,
        ?string $missingContextQuestion,
        mixed $model,
    ): string {
        $jsonSystem = implode("\n", [
            $prompt['system'],
            'Bạn là API trạng thái dữ liệu.',
            'Chỉ trả về DUY NHẤT JSON object hợp lệ, không markdown, không giải thích.',
            'Schema bắt buộc: {"status":"string","missing_info":"string","next_action":"string"}',
        ]);

        $jsonUser = implode("\n\n", [
            $prompt['user'],
            'Yêu cầu thêm:',
            '- status: "Đủ dữ liệu" hoặc "Chưa đủ dữ liệu".',
            '- missing_info: 1 thông tin còn thiếu (nếu có), ngắn gọn.',
            '- next_action: 1 hành động tiếp theo ngắn gọn.',
            $missingContextQuestion !== null ? 'Gợi ý thiếu dữ liệu hiện tại: '.$missingContextQuestion : '',
        ]);

        $structuredPrompt = [
            'system' => $jsonSystem,
            'user' => $jsonUser,
            'turns' => $prompt['turns'],
        ];

        $result = $this->geminiService->generateContent($structuredPrompt, [
            'model' => is_string($model) ? $model : null,
            'response_format' => ['type' => 'json_object'],
            'stop' => ["\n\n\n"],
            'generation_config' => [
                'temperature' => 0.0,
                'topP' => 0.1,
                'maxOutputTokens' => 180,
            ],
        ]);

        $rawText = trim((string) ($result['text'] ?? ''));
        $parsed = $this->decodeJsonObject($rawText);
        if ($parsed === null) {
            return $this->buildStructuredFallbackText($task, $intent, $missingContextQuestion);
        }

        $status = (string) ($parsed['status'] ?? 'Chưa đủ dữ liệu');
        $missingInfo = (string) ($parsed['missing_info'] ?? 'Cần bổ sung thêm thông tin đầu vào.');
        $nextAction = (string) ($parsed['next_action'] ?? 'Vui lòng cung cấp thêm dữ liệu còn thiếu.');

        return implode("\n", [
            '- Trạng thái dữ liệu: '.$status.'.',
            '- Cần bổ sung: '.$missingInfo,
            '- Bước tiếp theo: '.$nextAction,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(string $rawText): ?array
    {
        if ($rawText === '') {
            return null;
        }

        $candidate = trim($rawText);
        if (str_starts_with($candidate, '```')) {
            $candidate = preg_replace('/^```(?:json)?\s*/', '', $candidate) ?? $candidate;
            $candidate = preg_replace('/\s*```$/', '', $candidate) ?? $candidate;
            $candidate = trim($candidate);
        }

        /** @var mixed $decoded */
        $decoded = json_decode($candidate, true);
        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function buildStructuredFallbackText(string $task, string $intent, ?string $missingContextQuestion): string
    {
        $missingInfo = $missingContextQuestion ?? 'Cần bổ sung thông tin đầu vào phù hợp với yêu cầu.';
        $nextAction = in_array(strtoupper($intent), ['COMPLIANCE', 'VIOLATION'], true)
            ? 'Vui lòng cung cấp mã đối tượng hoặc khoảng thời gian cần kiểm tra.'
            : 'Vui lòng cung cấp thêm thông tin còn thiếu để hệ thống tổng hợp.';

        return implode("\n", [
            '- Trạng thái dữ liệu: Chưa đủ dữ liệu.',
            '- Cần bổ sung: '.$missingInfo,
            '- Bước tiếp theo: '.$nextAction,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return \Generator<int, array<string, mixed>>
     */
    public function streamSend(User $user, array $payload): \Generator
    {
        $result = $this->send($user, $payload);

        $sessionId = (string) ($result['session_id'] ?? '');
        $text = (string) Arr::get($result, 'message.response', '');
        $chunkSize = 40;
        $chunks = $text !== '' ? str_split($text, $chunkSize) : [];

        yield [
            'event' => 'meta',
            'data' => [
                'session_id' => $sessionId,
                'cached' => (bool) ($result['cached'] ?? false),
                'guarded' => (bool) ($result['guarded'] ?? false),
            ],
        ];

        foreach ($chunks as $index => $chunk) {
            yield [
                'event' => 'chunk',
                'data' => [
                    'index' => $index,
                    'text' => $chunk,
                ],
            ];
        }

        yield [
            'event' => 'done',
            'data' => array_merge($result, [
                'response_text' => (string) ($result['response_text'] ?? ''),
                'result' => $result,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listMessages(User $user, string $sessionId, int $limit = 30): array
    {
        $limit = max(1, min($limit, 100));

        $messages = ChatMessage::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        return [
            'session_id' => $sessionId,
            'messages' => $messages,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSessions(User $user, int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        return ChatMessage::query()
            ->where('user_id', $user->id)
            ->select('session_id')
            ->selectRaw('MAX(created_at) as last_message_at')
            ->groupBy('session_id')
            ->orderByDesc('last_message_at')
            ->limit($limit)
            ->get()
            ->map(fn (ChatMessage $item): array => [
                'session_id' => $item->session_id,
                'last_message_at' => $item->last_message_at,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteSession(User $user, string $sessionId): array
    {
        $deleted = ChatMessage::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->delete();

        if ($deleted === 0) {
            throw new ApiException('Chat session not found', 404);
        }

        return [
            'session_id' => $sessionId,
            'deleted_messages' => $deleted,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildCacheKey(int $userId, int $companyId, string $message, string $task, array $context, string $model): string
    {
        ksort($context);
        $fingerprint = json_encode([
            'message' => $message,
            'task' => $task,
            'context' => $context,
            'model' => $model,
        ], JSON_UNESCAPED_UNICODE);

        // Include both userId and companyId to prevent cross-user and cross-tenant cache hits.
        return sprintf('chat:%d:%d:%s', $userId, $companyId, sha1((string) $fingerprint));
    }
}
