<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Lark\LarkWebhookRequest;
use App\Models\LarkEventLog;
use App\Services\Lark\LarkCommandRouterService;
use App\Services\Lark\LarkNotificationService;
use App\Services\Lark\LarkSignatureService;
use App\Services\Lark\LarkUserMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Throwable;

class LarkWebhookController extends BaseController
{
    public function __construct(
        private readonly LarkSignatureService $signatureService,
        private readonly LarkUserMappingService $userMappingService,
        private readonly LarkCommandRouterService $commandRouterService,
        private readonly LarkNotificationService $notificationService,
    ) {}

    public function handle(LarkWebhookRequest $request): JsonResponse
    {
        $payload = $request->validated();

        if (($payload['type'] ?? null) === 'url_verification') {
            if (! $this->signatureService->verifyChallengeToken($payload['token'] ?? null)) {
                return $this->errorResponse('Invalid verification token', 401);
            }

            return response()->json(['challenge' => $payload['challenge'] ?? '']);
        }

        $signatureValid = $this->signatureService->verifyRequest($request);
        $eventId = (string) data_get($payload, 'header.event_id', data_get($payload, 'event.message.message_id', ''));
        $requestId = (string) data_get($payload, 'header.event_id', '');
        $eventType = (string) data_get($payload, 'header.event_type', data_get($payload, 'type', 'unknown'));

        $log = LarkEventLog::create([
            'event_id' => $eventId !== '' ? $eventId : null,
            'request_id' => $requestId !== '' ? $requestId : null,
            'event_type' => $eventType,
            'signature_valid' => $signatureValid,
            'status' => 'received',
            'headers' => $request->headers->all(),
            'payload' => $payload,
        ]);

        if (! $signatureValid) {
            $log->update(['status' => 'rejected', 'error_message' => 'Invalid signature']);

            return $this->errorResponse('Invalid signature', 401);
        }

        if ($eventId !== '' && ! Cache::add('lark:event:dedupe:'.$eventId, true, now()->addMinutes(10))) {
            $log->update(['status' => 'ignored', 'replay_blocked' => true, 'processed_at' => now()]);

            return $this->successResponse(['deduplicated' => true], 'OK');
        }

        $nonce = (string) $request->header('X-Lark-Request-Nonce', '');
        if ($nonce !== '' && ! Cache::add('lark:nonce:dedupe:'.$nonce, true, now()->addMinutes(10))) {
            $log->update(['status' => 'ignored', 'replay_blocked' => true, 'processed_at' => now()]);

            return $this->successResponse(['deduplicated' => true], 'OK');
        }

        try {
            $handled = $this->handleEvent($payload);
            $log->update([
                'status' => $handled ? 'processed' : 'ignored',
                'processed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'processed_at' => now()]);

            return $this->errorResponse('Webhook processing failed', 500, [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->successResponse(['processed' => true], 'OK');
    }

    private function handleEvent(array $payload): bool
    {
        $eventType = (string) data_get($payload, 'header.event_type', '');

        if ($eventType !== 'im.message.receive_v1') {
            return false;
        }

        $larkUserId = (string) data_get($payload, 'event.sender.sender_id.user_id', '');
        $text = (string) data_get($payload, 'event.message.content', '');

        $decodedText = $text;
        if ($text !== '' && str_starts_with($text, '{')) {
            $decoded = json_decode($text, true);
            $decodedText = (string) ($decoded['text'] ?? $text);
        }

        $user = $this->userMappingService->findByLarkUserId($larkUserId);
        if (! $user) {
            return false;
        }

        $responseMessage = $this->commandRouterService->handle($user, $decodedText);
        $chatId = (string) data_get($payload, 'event.message.chat_id', '');
        $this->notificationService->sendTextToChat($chatId, $responseMessage);

        return true;
    }
}
