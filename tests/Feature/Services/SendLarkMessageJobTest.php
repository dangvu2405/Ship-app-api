<?php

namespace Tests\Feature\Services;

use App\Jobs\Lark\SendLarkMessageJob;
use App\Services\Lark\LarkTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendLarkMessageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_sends_message_to_lark_api(): void
    {
        Http::fake([
            'https://open.larksuite.com/open-apis/im/v1/messages*' => Http::response(['code' => 0], 200),
        ]);

        $tokenService = $this->createMock(LarkTokenService::class);
        $tokenService->method('getTenantAccessToken')->willReturn('token-abc');

        $job = new SendLarkMessageJob('chat-1', 'text', ['text' => 'hello']);
        $job->handle($tokenService);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/open-apis/im/v1/messages')
                && $request->hasHeader('Authorization', 'Bearer token-abc');
        });
    }
}
