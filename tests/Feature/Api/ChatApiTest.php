<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Smoke tests nhanh cho chat. Bộ đầy đủ: {@see ChatBotComprehensiveTest}; enrich company/driver: `tests/Feature/Chat/ChatEnrichDatasetTest.php` (Pest).
 */
class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_message_returns_guarded_response(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/chat/messages', [
            'message' => 'ok',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.guarded', true)
            ->assertJsonPath('data.response_text', 'Vui lòng nhập chi tiết hơn.');
    }

    public function test_question_message_returns_ai_response_payload(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        $this->mock(\App\Services\GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateContent')
                ->once()
                ->andReturn([
                    'raw' => [],
                    'text' => 'Đây là phản hồi AI mẫu.',
                ]);
        });

        $response = $this->postJson('/api/v1/chat/messages', [
            'message' => 'Tình hình vận hành hôm nay thế nào?',
            'task' => 'chat',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.guarded', false)
            ->assertJsonPath('data.cached', false)
            ->assertJsonPath('data.response_text', 'Đây là phản hồi AI mẫu.')
            ->assertJsonPath('data.message.status', 'success');
    }
}
