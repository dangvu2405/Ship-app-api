<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_get_chat_sessions(): void
    {
        $response = $this->getJson('/api/chat/sessions');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_it_can_get_chat_messages(): void
    {
        $this->mock(ChatService::class, function ($mock) {
            $mock->shouldReceive('listMessages')
                ->once()
                ->andReturn([]);
        });

        $response = $this->getJson('/api/chat/messages?session_id=test-session-001');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_it_can_send_a_chat_message(): void
    {
        $this->mock(ChatService::class, function ($mock) {
            $mock->shouldReceive('send')
                ->once()
                ->andReturn([
                    'reply'      => 'Hello! How can I help you?',
                    'session_id' => 'test-session-001',
                ]);
        });

        $response = $this->postJson('/api/chat/messages', [
            'message' => 'Hello, world!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_chat_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/chat/sessions');

        $response->assertUnauthorized();
    }
}
