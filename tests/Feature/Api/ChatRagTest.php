<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\KnowledgeArticle;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class ChatRagTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_message_prompt_includes_relevant_rag_article(): void
    {
        KnowledgeArticle::query()->create([
            'company_id' => $this->company?->id,
            'category' => 'PAYROLL',
            'title' => 'Quy định payroll tháng',
            'content' => 'Payroll tháng 5 phải đối soát phụ cấp và khấu trừ trước khi khóa bảng lương.',
            'tags' => ['payroll'],
            'is_active' => true,
        ]);

        $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldReceive('generateContent')
                ->once()
                ->with(
                    Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Tài liệu nội bộ liên quan:')
                        && str_contains($prompt, 'Quy định payroll tháng')
                        && str_contains($prompt, 'đối soát phụ cấp và khấu trừ')),
                    Mockery::type('array')
                )
                ->andReturn(['text' => 'Cần đối soát phụ cấp và khấu trừ trước khi khóa bảng lương.']);
        });

        $response = $this->postJson('/api/chat/messages', [
            'message' => 'Payroll tháng 5 cần kiểm tra gì trước khi khóa?',
            'task' => 'chat',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.response_text', 'Cần đối soát phụ cấp và khấu trừ trước khi khóa bảng lương.')
            ->assertJsonPath('data.sources.0.title', 'Quy định payroll tháng')
            ->assertJsonPath('data.sources.0.category', 'PAYROLL');
    }
}
