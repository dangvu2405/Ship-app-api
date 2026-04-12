<?php

namespace Tests\Feature\Api;

use App\Jobs\Lark\SendLarkMessageJob;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LarkWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_url_verification_returns_challenge(): void
    {
        config()->set('lark.verification_token', 'token-123');

        $response = $this->postJson('/api/lark/webhook', [
            'type' => 'url_verification',
            'token' => 'token-123',
            'challenge' => 'abc-challenge',
        ]);

        $response->assertStatus(200)
            ->assertJson(['challenge' => 'abc-challenge']);
    }

    public function test_v1_webhook_alias_url_verification_returns_challenge(): void
    {
        config()->set('lark.verification_token', 'token-123');

        $response = $this->postJson('/api/v1/lark/webhook', [
            'type' => 'url_verification',
            'token' => 'token-123',
            'challenge' => 'abc-v1-challenge',
        ]);

        $response->assertStatus(200)
            ->assertJson(['challenge' => 'abc-v1-challenge']);
    }

    public function test_message_event_requires_valid_signature(): void
    {
        config()->set('lark.signing_secret', 'secret-123');

        $payload = [
            'header' => [
                'event_id' => 'event-1',
                'event_type' => 'im.message.receive_v1',
            ],
            'event' => [
                'sender' => ['sender_id' => ['user_id' => 'ou_xxx']],
                'message' => ['chat_id' => 'oc_xxx', 'content' => json_encode(['text' => '/trip 1'])],
            ],
        ];

        $response = $this->postJson('/api/lark/webhook', $payload, [
            'X-Lark-Request-Timestamp' => (string) time(),
            'X-Lark-Request-Nonce' => 'nonce-1',
            'X-Lark-Signature' => 'bad-signature',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Invalid signature']);
    }

    public function test_message_event_is_processed_and_deduplicated(): void
    {
        Queue::fake();
        config()->set('lark.signing_secret', 'secret-123');

        $driverRole = Role::create(['name' => 'driver']);
        $user = User::factory()->create(['lark_user_id' => 'ou_valid', 'status' => 'active']);
        $user->roles()->sync([$driverRole->id]);

        $payload = [
            'header' => [
                'event_id' => 'event-2',
                'event_type' => 'im.message.receive_v1',
            ],
            'event' => [
                'sender' => ['sender_id' => ['user_id' => 'ou_valid']],
                'message' => ['chat_id' => 'oc_xxx', 'content' => json_encode(['text' => '/payroll 03-2026'])],
            ],
        ];

        $content = json_encode($payload);
        $timestamp = (string) time();
        $nonce = 'nonce-2';
        $signature = base64_encode(hash_hmac('sha256', $timestamp.$nonce.$content, 'secret-123', true));

        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LARK_REQUEST_TIMESTAMP' => $timestamp,
            'HTTP_X_LARK_REQUEST_NONCE' => $nonce,
            'HTTP_X_LARK_SIGNATURE' => $signature,
        ];

        $firstResponse = $this->call('POST', '/api/lark/webhook', [], [], [], $headers, $content);
        $firstResponse->assertStatus(200)->assertJson(['success' => true]);

        Queue::assertPushed(SendLarkMessageJob::class);

        $secondResponse = $this->call('POST', '/api/lark/webhook', [], [], [], $headers, $content);
        $secondResponse->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['deduplicated' => true]]);
    }

    public function test_unmapped_lark_user_is_ignored_with_success_response(): void
    {
        Queue::fake();
        config()->set('lark.signing_secret', 'secret-123');

        $payload = [
            'header' => [
                'event_id' => 'event-3',
                'event_type' => 'im.message.receive_v1',
            ],
            'event' => [
                'sender' => ['sender_id' => ['user_id' => 'ou_unknown']],
                'message' => ['chat_id' => 'oc_xxx', 'content' => json_encode(['text' => '/trip 1'])],
            ],
        ];

        $content = json_encode($payload);
        $timestamp = (string) time();
        $nonce = 'nonce-3';
        $signature = base64_encode(hash_hmac('sha256', $timestamp.$nonce.$content, 'secret-123', true));

        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LARK_REQUEST_TIMESTAMP' => $timestamp,
            'HTTP_X_LARK_REQUEST_NONCE' => $nonce,
            'HTTP_X_LARK_SIGNATURE' => $signature,
        ];

        $response = $this->call('POST', '/api/lark/webhook', [], [], [], $headers, $content);
        $response->assertStatus(200)->assertJson(['success' => true]);

        Queue::assertNotPushed(SendLarkMessageJob::class);
    }
}
