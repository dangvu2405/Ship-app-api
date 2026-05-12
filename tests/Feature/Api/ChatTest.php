<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_get_chat_sessions()
    {
        $response = $this->getJson('/api/chat/sessions');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    /** @test */
    public function it_can_get_chat_messages()
    {
        $response = $this->getJson('/api/chat/messages');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    /** @test */
    public function it_can_send_a_chat_message()
    {
        $response = $this->postJson('/api/chat/messages', [
            'message' => 'Hello, world!',
        ]);

        $response->assertStatus(200);
    }
}
