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
use Illuminate\Support\Str;
use Throwable;

class ChatService
{
    public function __construct(
        private readonly GeminiService $geminiService,
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

        $cacheKey = $this->buildCacheKey($user->id, $message, $task, $context, $resolvedModel);
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

        $history = ChatMessage::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->reverse()
            ->values();

        $ragDocs = $task === 'chat'
            ? $this->chatRagService->search($message, 'GENERAL', $this->tenantContext->getCompanyId())
            : [];

        $prompt = $this->buildPrompt($history, $message, $context, $task, $ragDocs);

        try {
            $result = $this->geminiService->generateContent($prompt, [
                'model' => $payload['model'] ?? null,
                'generation_config' => [
                    'temperature' => 0.2,
                    'topP' => 0.8,
                    'maxOutputTokens' => 80,
                ],
            ]);

            $responseText = trim((string) ($result['text'] ?? ''));
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
                    'sources' => $this->sourcesFromRagDocs($ragDocs),
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
            'sources' => $this->sourcesFromRagDocs($ragDocs),
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
     * @param  list<array{title: string, snippet: string, category: string}>  $ragDocs
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

        $lines = [
            'Bạn là trợ lý chat cho hệ thống Company Ship API.',
            'Trả lời cực ngắn, rõ ràng, tập trung nghiệp vụ logistics, nhân sự, chấm công, payroll.',
            'Nếu câu hỏi thiếu dữ liệu, hãy nói rõ giả định.',
        ];

        if ($ragDocs !== []) {
            $lines[] = 'Tài liệu nội bộ liên quan:';
            foreach ($ragDocs as $index => $doc) {
                $lines[] = sprintf(
                    '[%d] %s (%s): %s',
                    $index + 1,
                    $doc['title'],
                    $doc['category'],
                    $doc['snippet']
                );
            }
            $lines[] = 'Ưu tiên trả lời dựa trên tài liệu nội bộ ở trên; nếu tài liệu không đủ, nói rõ phần thiếu dữ liệu.';
        }

        if ($context !== []) {
            $lines[] = 'Context bổ sung: '.json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        if ($history->isNotEmpty()) {
            $lines[] = 'Lịch sử hội thoại gần nhất:';
            foreach ($history as $item) {
                $lines[] = 'User: '.$item->message;
                if (! empty($item->response)) {
                    $lines[] = 'Assistant: '.$item->response;
                }
            }
        }

        $lines[] = 'User hiện tại: '.$message;

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
     * @param  list<array{title: string, snippet: string, category: string}>  $ragDocs
     * @return list<array{id: string, title: string, content: string, category: string}>
     */
    private function sourcesFromRagDocs(array $ragDocs): array
    {
        return array_map(
            fn (array $doc): array => [
                'id' => sha1($doc['category'].'|'.$doc['title']),
                'title' => $doc['title'],
                'content' => $doc['snippet'],
                'category' => $doc['category'],
            ],
            $ragDocs
        );
    }
}
