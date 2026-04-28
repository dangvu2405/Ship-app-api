<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Chat\GetChatMessagesRequest;
use App\Http\Requests\Chat\GetChatSessionsRequest;
use App\Http\Requests\Chat\StoreChatMessageRequest;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * @OA\Tag(name="Chat", description="API chat app")
 */
final class ChatController extends BaseController
{
    public function __construct(private readonly ChatService $chatService) {}

    /**
     * @OA\Post(
     *     path="/api/chat/messages",
     *     tags={"Chat"},
     *     summary="Gửi tin nhắn chat và nhận phản hồi AI",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function store(StoreChatMessageRequest $request): JsonResponse
    {
        try {
            $result = $this->chatService->send($request->user(), $request->validated());

            return $this->successResponse($result, 'api.chat.response_generated');
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/chat/messages",
     *     tags={"Chat"},
     *     summary="Lịch sử chat theo session",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(GetChatMessagesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->chatService->listMessages(
            $request->user(),
            (string) $validated['session_id'],
            (int) ($validated['limit'] ?? 30)
        );

        return $this->successResponse($result, 'api.common.ok');
    }

    /**
     * @OA\Get(
     *     path="/api/chat/sessions",
     *     tags={"Chat"},
     *     summary="Danh sách session chat",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function sessions(GetChatSessionsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $sessions = $this->chatService->listSessions($request->user(), (int) ($validated['limit'] ?? 20));

        return $this->successResponse(['sessions' => $sessions], 'api.common.ok');
    }

    /**
     * @OA\Delete(
     *     path="/api/chat/sessions/{sessionId}",
     *     tags={"Chat"},
     *     summary="Xóa toàn bộ tin nhắn trong một session chat",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="sessionId", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy session")
     * )
     */
    public function destroySession(string $sessionId): JsonResponse
    {
        try {
            $result = $this->chatService->deleteSession(request()->user(), $sessionId);

            return $this->successResponse($result, 'api.chat.session_deleted');
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/chat/messages/stream",
     *     tags={"Chat"},
     *     summary="Stream phản hồi chat theo SSE",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function stream(StoreChatMessageRequest $request): StreamedResponse|JsonResponse
    {
        try {
            $user = $request->user();
            $payload = $request->validated();

            return response()->stream(function () use ($user, $payload): void {
                @ini_set('output_buffering', 'off');
                @ini_set('zlib.output_compression', '0');

                try {
                    foreach ($this->chatService->streamSend($user, $payload) as $event) {
                        $name = (string) ($event['event'] ?? 'message');
                        $data = $event['data'] ?? [];

                        echo 'event: '.$name."\n";
                        echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";

                        if (function_exists('ob_flush')) {
                            @ob_flush();
                        }
                        flush();
                    }
                } catch (Throwable $e) {
                    echo 'event: error'."\n";
                    echo 'data: '.json_encode([
                        'success' => false,
                        'message' => $e->getMessage(),
                    ], JSON_UNESCAPED_UNICODE)."\n\n";

                    if (function_exists('ob_flush')) {
                        @ob_flush();
                    }
                    flush();
                }
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache, no-transform',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }
}
