<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\KnowledgeArticle;
use App\Services\GroqService;
use Database\Seeders\ChatContextSeeder;
use Database\Seeders\EnsureAdminAbcTransportSeeder;
use Database\Seeders\KnowledgeArticleSeeder;
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

        $this->mock(GroqService::class, function ($mock): void {
            $mock->shouldReceive('generateContent')
                ->once()
                ->with(
                    Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Tài liệu nội bộ liên quan:')
                        && str_contains($prompt, 'Quy định payroll tháng')
                        && str_contains($prompt, 'đối soát phụ cấp và khấu trừ')),
                    Mockery::type('array')
                )
                ->andReturn([
                    'text' => 'Cần đối soát phụ cấp và khấu trừ trước khi khóa bảng lương.',
                    'model' => 'openai/gpt-oss-20b',
                    'raw' => [],
                ]);
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

    public function test_chat_falls_back_to_seeded_knowledge_when_groq_key_is_missing(): void
    {
        config(['services.groq.api_key' => '']);

        KnowledgeArticle::query()->create([
            'company_id' => null,
            'category' => 'MAINTENANCE',
            'title' => 'Quy trình bảo trì xe demo',
            'content' => 'Khi xe vào bảo trì, chuyển trạng thái maintenance và không phân công chuyến mới.',
            'tags' => ['maintenance'],
            'status' => 'published',
            'source' => 'test',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/chat/messages', [
            'message' => 'Quy trình bảo trì xe như thế nào?',
            'task' => 'chat',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.llm_unavailable', true)
            ->assertJsonPath('data.sources.0.title', 'Quy trình bảo trì xe demo')
            ->assertJsonPath('data.sources.0.content_preview', 'Khi xe vào bảo trì, chuyển trạng thái maintenance và không phân công chuyến mới.');
    }

    public function test_knowledge_article_seeders_create_demo_rag_content(): void
    {
        $this->seed(EnsureAdminAbcTransportSeeder::class);
        $this->seed(KnowledgeArticleSeeder::class);
        $this->seed(ChatContextSeeder::class);

        $this->assertGreaterThanOrEqual(12, KnowledgeArticle::query()->count());
        $this->assertDatabaseHas('knowledge_articles', [
            'category' => 'FLEET',
            'title' => 'Quản lý xe trong Ship-app',
            'status' => 'published',
            'source' => 'seed:knowledge-article',
        ]);
        $this->assertDatabaseHas('knowledge_articles', [
            'category' => 'INCIDENTS',
            'title' => 'Quy trình xử lý sự cố trên đường',
            'status' => 'published',
        ]);
    }
}
