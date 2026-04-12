<?php

declare(strict_types=1);

namespace App\Jobs\Lark;

use App\Services\Lark\LarkTokenService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SendLarkMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly string $chatId,
        public readonly string $msgType,
        public readonly array $content,
    ) {
        $this->onQueue('lark-notifications');
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function handle(LarkTokenService $tokenService): void
    {
        Http::timeout(10)
            ->retry(3, 200)
            ->withToken($tokenService->getTenantAccessToken())
            ->post('https://open.larksuite.com/open-apis/im/v1/messages?receive_id_type=chat_id', [
                'receive_id' => $this->chatId,
                'msg_type' => $this->msgType,
                'content' => json_encode($this->content, JSON_UNESCAPED_UNICODE),
            ])->throw();
    }
}
