<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ChatMessage;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\RagAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Bao phủ các nhánh chính của Chat bot: validation, guard ngắn, cache, RAG agent,
 * lỗi Gemini, task khác chat, luồng structured (thiếu dữ liệu payroll), session CRUD, SSE stream.
 */
final class ChatBotComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Config::set('services.rag.agent_enabled', false);
        parent::tearDown();
    }

    private function actingUser(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        return $user;
    }

    private static function longChatMessage(): string
    {
        return 'Tình hình vận hành hôm nay thế nào?';
    }

    public function test_guest_post_messages_returns_401(): void
    {
        $this->postJson('/api/v1/chat/messages', [
            'message' => self::longChatMessage(),
        ])->assertStatus(401);
    }

    public function test_guest_get_messages_returns_401(): void
    {
        $this->getJson('/api/v1/chat/messages?session_id=s1')->assertStatus(401);
    }

    public function test_guest_delete_session_returns_401(): void
    {
        $this->deleteJson('/api/v1/chat/sessions/s1')->assertStatus(401);
    }

    public function test_validation_requires_message(): void
    {
        $this->actingUser();

        $this->postJson('/api/v1/chat/messages', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_validation_message_max_4000(): void
    {
        $this->actingUser();

        $this->postJson('/api/v1/chat/messages', [
            'message' => str_repeat('x', 4001),
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_validation_rejects_invalid_task(): void
    {
        $this->actingUser();

        $this->postJson('/api/v1/chat/messages', [
            'message' => self::longChatMessage(),
            'task' => 'invalid-task',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_validation_session_id_max_64(): void
    {
        $this->actingUser();

        $this->postJson('/api/v1/chat/messages', [
            'message' => self::longChatMessage(),
            'session_id' => str_repeat('a', 65),
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_short_message_returns_guarded_response(): void
    {
        $this->actingUser();

        $this->postJson('/api/v1/chat/messages', [
            'message' => 'ok',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.guarded', true)
            ->assertJsonPath('data.response_text', 'Vui lòng nhập chi tiết hơn.');
    }

    public function test_two_character_message_guarded(): void
    {
        $this->actingUser();

        $this->postJson('/api/v1/chat/messages', [
            'message' => 'ab',
        ])
            ->assertOk()
            ->assertJsonPath('data.guarded', true);
    }

    public function test_whitespace_only_message_returns_422(): void
    {
        $this->actingUser();

        $this->postJson('/api/v1/chat/messages', [
            'message' => '   ',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_question_message_returns_ai_response_payload(): void
    {
        $this->actingUser();

        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateContent')
                ->once()
                ->andReturn([
                    'raw' => [],
                    'text' => 'Đây là phản hồi AI mẫu.',
                ]);
        });

        $this->postJson('/api/v1/chat/messages', [
            'message' => self::longChatMessage(),
            'task' => 'chat',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.guarded', false)
            ->assertJsonPath('data.cached', false)
            ->assertJsonPath('data.response_text', 'Đây là phản hồi AI mẫu.')
            ->assertJsonPath('data.message.status', 'success');
    }

    public function test_second_identical_message_uses_cache(): void
    {
        $this->actingUser();

        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateContent')
                ->once()
                ->andReturn([
                    'raw' => [],
                    'text' => 'Trả lời duy nhất từ AI.',
                ]);
        });

        $payload = [
            'message' => 'Câu hỏi cache kiểm thử đủ dài?',
            'task' => 'chat',
        ];

        $this->postJson('/api/v1/chat/messages', $payload)->assertJsonPath('data.cached', false);
        $this->postJson('/api/v1/chat/messages', $payload)->assertJsonPath('data.cached', true);
    }

    public function test_provided_session_id_echoed_in_response(): void
    {
        $this->actingUser();

        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateContent')->once()->andReturn(['raw' => [], 'text' => 'OK']);
        });

        $sessionId = 'fixed-session-01';

        $this->postJson('/api/v1/chat/messages', [
            'message' => 'Hỏi với session cố định đủ dài?',
            'session_id' => $sessionId,
        ])
            ->assertJsonPath('data.session_id', $sessionId);
    }

    public function test_task_extract_calls_gemini_once(): void
    {
        $this->actingUser();

        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateContent')
                ->once()
                ->andReturn([
                    'raw' => [],
                    'text' => '{"sender_name":null}',
                ]);
        });

        $this->postJson('/api/v1/chat/messages', [
            'message' => 'Nội dung extract đủ dài để qua guard?',
            'task' => 'extract',
        ])
            ->assertOk()
            ->assertJsonPath('data.guarded', false);
    }

    public function test_task_classify_calls_gemini_once(): void
    {
        $this->actingUser();

        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateContent')
                ->once()
                ->andReturn([
                    'raw' => [],
                    'text' => 'OTHER',
                ]);
        });

        $this->postJson('/api/v1/chat/messages', [
            'message' => 'Phân loại câu này giúp tôi đủ dài?',
            'task' => 'classify',
        ])
            ->assertOk()
            ->assertJsonPath('data.guarded', false);
    }

    public function test_payroll_query_missing_fields_uses_structured_gemini_path(): void
    {
        $this->actingUser();

        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateContent')
                ->once()
                ->andReturn([
                    'raw' => [],
                    'text' => '{"status":"Chưa đủ dữ liệu","missing_info":"base_salary","next_action":"Nhập kỳ lương"}',
                ]);
        });

        $this->postJson('/api/v1/chat/messages', [
            'message' => 'Giải thích chi tiết bảng lương tháng này giúp tôi?',
            'context' => [
                'task' => 'payroll_query',
                'payroll' => [],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.guarded', false)
            ->assertJsonPath('data.response_text', fn (string $t): bool => str_contains($t, 'Trạng thái dữ liệu'));
    }

    public function test_rag_agent_path_when_enabled(): void
    {
        $this->actingUser();

        Config::set('services.rag.agent_enabled', true);

        $this->mock(RagAgentService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('ask')
                ->once()
                ->andReturn([
                    'answer' => 'Kết quả từ RAG agent.',
                    'trace' => ['step' => 'mock'],
                ]);
        });

        $this->postJson('/api/v1/chat/messages', [
            'message' => self::longChatMessage(),
            'task' => 'chat',
        ])
            ->assertOk()
            ->assertJsonPath('data.response_text', 'Kết quả từ RAG agent.')
            ->assertJsonPath('data.message.model', fn (?string $m): bool => is_string($m) && str_starts_with($m, 'rag-agent/'));
    }

    public function test_gemini_429_returns_busy_guarded_message(): void
    {
        $this->actingUser();

        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateContent')
                ->once()
                ->andThrow(new \App\Exceptions\ApiException('Rate limited', 429));
        });

        $this->postJson('/api/v1/chat/messages', [
            'message' => self::longChatMessage(),
        ])
            ->assertOk()
            ->assertJsonPath('data.guarded', true)
            ->assertJsonPath('data.response_text', 'Xin lỗi, hệ thống đang bận. Vui lòng thử lại sau ít phút.');
    }

    public function test_gemini_api_error_returns_error_json(): void
    {
        $this->actingUser();

        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateContent')
                ->once()
                ->andThrow(new \App\Exceptions\ApiException('Upstream unavailable', 503, ['reason' => 'maintenance']));
        });

        $this->postJson('/api/v1/chat/messages', [
            'message' => self::longChatMessage(),
        ])
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.chat_message_id', fn ($id): bool => is_int($id) || is_numeric($id));
    }

    public function test_gemini_unexpected_failure_returns_502(): void
    {
        $this->actingUser();

        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateContent')
                ->once()
                ->andThrow(new \RuntimeException('network reset'));
        });

        $this->postJson('/api/v1/chat/messages', [
            'message' => self::longChatMessage(),
        ])
            ->assertStatus(502)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.chat_message_id', fn ($id): bool => is_int($id) || is_numeric($id));
    }

    public function test_list_messages_requires_session_id(): void
    {
        $this->actingUser();

        $this->getJson('/api/v1/chat/messages')
            ->assertStatus(422);
    }

    public function test_list_messages_returns_session_messages(): void
    {
        $user = $this->actingUser();
        $sessionId = 'sess-list-1';

        ChatMessage::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'message' => 'm1',
            'response' => 'r1',
            'context' => [],
            'model' => 'test',
            'status' => 'success',
        ]);

        $this->getJson('/api/v1/chat/messages?session_id='.$sessionId)
            ->assertOk()
            ->assertJsonPath('data.session_id', $sessionId)
            ->assertJsonPath('data.messages.0.message', 'm1');
    }

    public function test_list_sessions_returns_sessions(): void
    {
        $user = $this->actingUser();
        $sessionId = 'sess-dash-1';

        ChatMessage::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'message' => 'hi',
            'response' => 'bye',
            'context' => [],
            'model' => 'test',
            'status' => 'success',
        ]);

        $this->getJson('/api/v1/chat/sessions')
            ->assertOk()
            ->assertJsonPath('data.sessions.0.session_id', $sessionId);
    }

    public function test_delete_session_removes_rows(): void
    {
        $user = $this->actingUser();
        $sessionId = 'sess-del-1';

        ChatMessage::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'message' => 'x',
            'response' => 'y',
            'context' => [],
            'model' => 'test',
            'status' => 'success',
        ]);

        $this->deleteJson('/api/v1/chat/sessions/'.$sessionId)
            ->assertOk()
            ->assertJsonPath('data.deleted_messages', 1);

        $this->assertSame(0, ChatMessage::query()->where('session_id', $sessionId)->count());
    }

    public function test_delete_unknown_session_returns_404(): void
    {
        $this->actingUser();

        $this->deleteJson('/api/v1/chat/sessions/non-existent-session')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_stream_returns_sse_with_meta_chunk_done(): void
    {
        $this->actingUser();

        $response = $this->post('/api/v1/chat/messages/stream', [
            'message' => 'ok',
        ], [
            'Accept' => 'text/event-stream',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('event: meta', $body);
        $this->assertStringContainsString('event: done', $body);
        $this->assertStringContainsString('"guarded":true', $body);
    }
}
