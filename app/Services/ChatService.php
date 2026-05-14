<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\ChatMessage;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ChatService
{
    private const NO_KNOWLEDGE_MESSAGE = 'Hiện chưa có tài liệu được cung cấp để trả lời câu hỏi này.';

    private const NO_MATCH_MESSAGE = 'Tôi không tìm thấy thông tin này trong tài liệu được cung cấp.';

    public function __construct(
        private readonly GroqService $groqService,
        private readonly ChatRagService $chatRagService,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function send(User $user, array $payload): array
    {
        $sessionId = (string) ($payload['session_id'] ?? Str::uuid()->toString());
        $message = trim((string) ($payload['message'] ?? ''));
        $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        $task = (string) ($payload['task'] ?? Arr::get($context, 'task', 'chat'));
        $requestedModel = (string) ($payload['model'] ?? '');
        $resolvedModel = $requestedModel !== '' && ! str_contains($requestedModel, 'gemini')
            ? $requestedModel
            : (string) config('services.groq.model', 'openai/gpt-oss-20b');

        if ($message === '') {
            throw new ApiException('Message is required', 422);
        }

        Log::info('ChatService: received message', [
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'task' => $task,
            'model' => $resolvedModel,
            'message_length' => mb_strlen($message),
        ]);

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
                'answer' => (string) $chat->response,
                'response_text' => (string) $chat->response,
                'sources' => [],
                'confidence' => 'none',
                'cached' => false,
                'guarded' => true,
            ];
        }

        $cacheKey = $this->buildCacheKey($user->id, $message, $task, $context, $resolvedModel);

        $history = ChatMessage::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->reverse()
            ->values();

        $companyId = $this->tenantContext->getCompanyId();
        $ragDocs = $task === 'chat'
            ? $this->chatRagService->search($message, 'GENERAL', $companyId)
            : [];

        Log::info('ChatService: retrieved RAG context', [
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'task' => $task,
            'source_count' => count($ragDocs),
            'top_score' => $ragDocs[0]['score'] ?? null,
        ]);

        $hasVisibleKnowledge = $task === 'chat'
            ? $this->chatRagService->hasVisibleKnowledge($companyId)
            : false;

        if ($task === 'chat' && $this->requiresRuntimeData($message) && $hasVisibleKnowledge && ! $this->hasRuntimeData($context)) {
            Log::warning('ChatService: runtime-data question without runtime context', [
                'user_id' => $user->id,
                'session_id' => $sessionId,
            ]);

            return $this->persistAnswer($user, $sessionId, $message, self::NO_MATCH_MESSAGE, $context, 'local-rag-missing-runtime-data', [
                'sources' => $this->sourcesFromRagDocs($ragDocs),
                'confidence' => $ragDocs === [] ? 'none' : 'low',
                'cached' => false,
                'guarded' => true,
                'error_code' => 'RAG_RUNTIME_CONTEXT_NOT_FOUND',
            ]);
        }

        if ($task === 'chat' && $ragDocs === []) {
            $answer = $hasVisibleKnowledge
                ? self::NO_MATCH_MESSAGE
                : self::NO_KNOWLEDGE_MESSAGE;

            Log::warning('ChatService: no usable RAG context', [
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'has_visible_knowledge' => $hasVisibleKnowledge,
            ]);

            return $this->persistAnswer($user, $sessionId, $message, $answer, $context, 'local-rag-no-context', [
                'sources' => [],
                'confidence' => 'none',
                'cached' => false,
                'guarded' => true,
                'error_code' => $answer === self::NO_KNOWLEDGE_MESSAGE ? 'RAG_CONTEXT_EMPTY' : 'RAG_CONTEXT_NOT_FOUND',
            ]);
        }

        $cachedText = Cache::get($cacheKey);
        if (is_string($cachedText) && $cachedText !== '') {
            return $this->persistAnswer($user, $sessionId, $message, $cachedText, $context, $resolvedModel, [
                'sources' => $this->sourcesFromRagDocs($ragDocs),
                'confidence' => $this->confidenceFromRagDocs($ragDocs),
                'cached' => true,
                'guarded' => false,
            ]);
        }

        $prompt = $this->buildPrompt($history, $message, $context, $task, $ragDocs);

        try {
            Log::info('ChatService: calling Groq', [
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'model' => $payload['model'] ?? $resolvedModel,
                'source_count' => count($ragDocs),
            ]);

            $result = $this->groqService->generateContent($prompt, [
                'model' => $payload['model'] ?? null,
                'generation_config' => [
                    'temperature' => 0.2,
                    'topP' => 0.8,
                    'maxOutputTokens' => 512,
                ],
            ]);

            $responseText = trim((string) ($result['text'] ?? ''));
            $resolvedModel = (string) ($result['model'] ?? $resolvedModel);
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
                    'answer' => (string) $chat->response,
                'response_text' => (string) $chat->response,
                'sources' => $this->sourcesFromRagDocs($ragDocs),
                'confidence' => $this->confidenceFromRagDocs($ragDocs),
                'cached' => false,
                'guarded' => true,
            ];
            }

            if ($e->getStatusCode() >= 500 || str_contains($e->getMessage(), 'GROQ_API_KEY')) {
                $fallback = $this->buildLocalRagFallback($ragDocs);
                $chat = ChatMessage::create([
                    'user_id' => $user->id,
                    'session_id' => $sessionId,
                    'message' => $message,
                    'response' => $fallback,
                    'context' => $context,
                    'model' => 'local-rag-fallback',
                    'status' => 'success',
                ]);

                return [
                    'session_id' => $sessionId,
                    'message' => $chat,
                    'answer' => (string) $chat->response,
                'response_text' => (string) $chat->response,
                'sources' => $this->sourcesFromRagDocs($ragDocs),
                'confidence' => $this->confidenceFromRagDocs($ragDocs),
                'cached' => false,
                'guarded' => true,
                'llm_unavailable' => true,
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
            if ($ragDocs !== []) {
                $fallback = $this->buildLocalRagFallback($ragDocs);
                $chat = ChatMessage::create([
                    'user_id' => $user->id,
                    'session_id' => $sessionId,
                    'message' => $message,
                    'response' => $fallback,
                    'context' => $context,
                    'model' => 'local-rag-fallback',
                    'status' => 'success',
                ]);

                return [
                    'session_id' => $sessionId,
                    'message' => $chat,
                    'answer' => (string) $chat->response,
                'response_text' => (string) $chat->response,
                'sources' => $this->sourcesFromRagDocs($ragDocs),
                'confidence' => $this->confidenceFromRagDocs($ragDocs),
                'cached' => false,
                'guarded' => true,
                'llm_unavailable' => true,
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

            throw new ApiException('Chat generation failed', 502, [
                'chat_message_id' => $chat->id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'session_id' => $sessionId,
            'message' => $chat,
            'answer' => (string) $chat->response,
            'response_text' => (string) $chat->response,
            'sources' => $this->sourcesFromRagDocs($ragDocs),
            'confidence' => $this->confidenceFromRagDocs($ragDocs),
            'cached' => false,
            'guarded' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
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
     * @param  Collection<int, ChatMessage>  $history
     * @param  array<string, mixed>  $context
     * @param  list<array{title: string, snippet: string, category: string, score?: float}>  $ragDocs
     */
    private function buildPrompt(Collection $history, string $message, array $context, string $task, array $ragDocs = []): string
    {
        if ($task === 'classify') {
            return str_replace('{input}', $message, "Bạn là bộ lọc tin nhắn cho ứng dụng Ship-app. Phân tích tin nhắn sau và chỉ trả về 1 từ khóa duy nhất trong danh sách: [ORDER, PRICE, TRACKING, OTHER].\n\nORDER: Khách muốn đặt giao hàng.\nPRICE: Khách hỏi giá tiền.\nTRACKING: Khách tìm đơn hàng.\nOTHER: Tin nhắn chào hỏi hoặc không liên quan.\n\nTin nhắn: {input}");
        }

        if ($task === 'extract') {
            return str_replace('{input}', $message, "Bạn là máy trích xuất dữ liệu. Hãy chuyển câu lệnh sau thành JSON.\nYêu cầu:\n- Không giải thích, không viết chữ ngoài JSON.\n- Nếu thiếu thông tin, để giá trị là null.\n- Các key: {'sender': tên, 'phone': sđt, 'from': địa chỉ lấy, 'to': địa chỉ giao, 'item': loại hàng}.\n\nNội dung: {input}");
        }

        if ($task === 'advice') {
            $item = (string) Arr::get($context, 'item', 'hàng hóa');
            $from = (string) Arr::get($context, 'from', 'điểm lấy');
            $to = (string) Arr::get($context, 'to', 'điểm giao');

            return "Bạn là chuyên gia tư vấn vận chuyển của Ship-app. Dựa vào loại hàng là '{$item}', hãy đưa ra 1 lời khuyên duy nhất về cách đóng gói để hàng không bị hỏng khi vận chuyển từ {$from} đến {$to}. Trả lời tối đa 20 từ.";
        }

        $contextLines = [];

        if ($ragDocs !== []) {
            foreach ($ragDocs as $index => $doc) {
                $contextLines[] = sprintf(
                    '[%d] %s (%s): %s',
                    $index + 1,
                    $doc['title'],
                    $doc['category'],
                    $doc['snippet']
                );
            }
        }

        if ($context !== []) {
            $contextLines[] = 'Context bổ sung: '.json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        $lines = [
            'Bạn là trợ lý RAG Chatbot của hệ thống Company Ship / CETA.',
            'Chỉ trả lời dựa trên nội dung trong [CONTEXT].',
            'Không dùng kiến thức bên ngoài cho câu hỏi nghiệp vụ. Không bịa đặt.',
            'Nếu không tìm thấy thông tin trong context, trả lời: '.self::NO_MATCH_MESSAGE,
            'Trả lời bằng tiếng Việt, ngắn gọn, ưu tiên gạch đầu dòng.',
            '',
            '[CONTEXT]',
            $contextLines !== [] ? implode("\n", $contextLines) : self::NO_KNOWLEDGE_MESSAGE,
            '[/CONTEXT]',
            '',
            '[QUESTION]',
            $message,
            '[/QUESTION]',
        ];

        if ($history->isNotEmpty()) {
            $lines[] = 'Lịch sử hội thoại gần nhất:';
            foreach ($history as $item) {
                $lines[] = 'User: '.$item->message;
                if (! empty($item->response)) {
                    $lines[] = 'Assistant: '.$item->response;
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function buildCacheKey(int $userId, string $message, string $task, array $context, string $model): string
    {
        ksort($context);
        $fingerprint = json_encode([
            'message' => $message,
            'task' => $task,
            'context' => $context,
            'model' => $model,
        ], JSON_UNESCAPED_UNICODE);

        return sprintf('chat:%d:%s', $userId, sha1((string) $fingerprint));
    }

    /**
     * @param  list<array{title: string, snippet: string, category: string, score?: float}>  $ragDocs
     * @return list<array{id: string, title: string, content: string, content_preview: string, category: string, score: float}>
     */
    private function sourcesFromRagDocs(array $ragDocs): array
    {
        return array_map(
            fn (array $doc): array => [
                'id' => sha1($doc['category'].'|'.$doc['title']),
                'title' => $doc['title'],
                'section' => $doc['category'],
                'content' => $doc['snippet'],
                'content_preview' => $doc['snippet'],
                'category' => $doc['category'],
                'score' => (float) ($doc['score'] ?? 0.5),
            ],
            $ragDocs
        );
    }

    /**
     * @param  list<array{title: string, snippet: string, category: string, score?: float}>  $ragDocs
     */
    private function buildLocalRagFallback(array $ragDocs): string
    {
        if ($ragDocs === []) {
            return self::NO_KNOWLEDGE_MESSAGE;
        }

        $lines = ['Dựa trên tài liệu nội bộ hiện có:'];
        foreach (array_slice($ragDocs, 0, 3) as $doc) {
            $lines[] = '- '.$doc['title'].': '.$doc['snippet'];
        }
        $lines[] = 'Lưu ý: đây là câu trả lời fallback khi dịch vụ AI chưa sẵn sàng.';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function persistAnswer(User $user, string $sessionId, string $message, string $answer, array $context, string $model, array $extra = []): array
    {
        $chat = ChatMessage::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'message' => $message,
            'response' => $answer,
            'context' => $context,
            'model' => $model,
            'status' => 'success',
        ]);

        Log::info('ChatService: response generated', [
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'model' => $model,
            'answer_length' => mb_strlen($answer),
            'source_count' => count($extra['sources'] ?? []),
            'confidence' => $extra['confidence'] ?? null,
        ]);

        return array_merge([
            'session_id' => $sessionId,
            'message' => $chat,
            'answer' => $answer,
            'response_text' => $answer,
        ], $extra);
    }

    /**
     * @param  list<array{score?: float}>  $ragDocs
     */
    private function confidenceFromRagDocs(array $ragDocs): string
    {
        $topScore = (float) ($ragDocs[0]['score'] ?? 0.0);

        if ($topScore >= 0.85) {
            return 'high';
        }

        if ($topScore >= (float) config('services.rag.min_score', 0.72)) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Runtime-number questions require real context data, not descriptive KB snippets.
     */
    private function requiresRuntimeData(string $message): bool
    {
        $normalized = mb_strtolower($message);

        return preg_match('/(bao nhiêu|doanh thu.*(tháng|ngày|hôm nay|năm|\d)|tổng\s+(doanh thu|chi phí|chuyến|đơn))/u', $normalized) === 1;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function hasRuntimeData(array $context): bool
    {
        return isset($context['data']) && is_array($context['data']) && $context['data'] !== [];
    }
}
