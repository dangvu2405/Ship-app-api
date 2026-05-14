<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\KnowledgeArticle;
use App\Exceptions\ApiException;
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
                    Mockery::on(fn (string $prompt): bool => str_contains($prompt, '[CONTEXT]')
                        && str_contains($prompt, '[/CONTEXT]')
                        && str_contains($prompt, '[QUESTION]')
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
            ->assertJsonPath('data.sources.0.category', 'PAYROLL')
            ->assertJsonPath('data.sources.0.section', 'PAYROLL')
            ->assertJsonPath('data.confidence', 'medium');
    }

    public function test_chat_answers_valid_question_with_context_sources_and_confidence(): void
    {
        KnowledgeArticle::query()->create([
            'company_id' => null,
            'category' => 'PERMISSIONS',
            'title' => 'Admin quản lý tài xế',
            'content' => 'Admin có toàn quyền quản lý tài xế trong công ty, bao gồm xem, tạo, sửa và cập nhật trạng thái tài xế.',
            'tags' => ['admin', 'drivers', 'permissions'],
            'status' => 'published',
            'source' => 'test',
            'is_active' => true,
        ]);

        $this->mock(GroqService::class, function ($mock): void {
            $mock->shouldReceive('generateContent')
                ->once()
                ->andReturn([
                    'text' => 'Admin có thể quản lý tài xế trong phạm vi công ty.',
                    'model' => 'openai/gpt-oss-20b',
                    'raw' => [],
                ]);
        });

        $response = $this->postJson('/api/chat/messages', [
            'message' => 'Admin có thể quản lý tài xế không?',
            'task' => 'chat',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.answer', 'Admin có thể quản lý tài xế trong phạm vi công ty.')
            ->assertJsonPath('data.sources.0.title', 'Admin quản lý tài xế')
            ->assertJsonPath('data.confidence', 'high');
    }

    public function test_chat_rejects_empty_message_with_validation_error(): void
    {
        $response = $this->postJson('/api/chat/messages', [
            'message' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_chat_returns_no_knowledge_message_when_context_is_empty(): void
    {
        KnowledgeArticle::query()->delete();

        $response = $this->postJson('/api/chat/messages', [
            'message' => 'Admin có thể quản lý tài xế không?',
            'task' => 'chat',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.answer', 'Hiện chưa có tài liệu được cung cấp để trả lời câu hỏi này.')
            ->assertJsonPath('data.confidence', 'none')
            ->assertJsonPath('data.error_code', 'RAG_CONTEXT_EMPTY');
    }

    public function test_chat_returns_no_match_message_for_low_relevance_context(): void
    {
        KnowledgeArticle::query()->create([
            'company_id' => null,
            'category' => 'FLEET',
            'title' => 'Quản lý xe',
            'content' => 'Xe bảo trì không được phân công chuyến mới.',
            'tags' => ['vehicles'],
            'status' => 'published',
            'source' => 'test',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/chat/messages', [
            'message' => 'Quy trình nhập kho lạnh quốc tế là gì?',
            'task' => 'chat',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.answer', 'Tôi không tìm thấy thông tin này trong tài liệu được cung cấp.')
            ->assertJsonPath('data.confidence', 'none')
            ->assertJsonPath('data.error_code', 'RAG_CONTEXT_NOT_FOUND');
    }

    public function test_chat_does_not_invent_runtime_numbers_without_runtime_context(): void
    {
        KnowledgeArticle::query()->create([
            'company_id' => null,
            'category' => 'REPORTS',
            'title' => 'Báo cáo vận hành đội xe',
            'content' => 'Báo cáo vận hành theo dõi doanh thu, chi phí và số chuyến nhưng không tự tạo số liệu nếu thiếu dữ liệu.',
            'tags' => ['reports', 'revenue'],
            'status' => 'published',
            'source' => 'test',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/chat/messages', [
            'message' => 'Báo cáo doanh thu tháng 12 là bao nhiêu?',
            'task' => 'chat',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.answer', 'Tôi không tìm thấy thông tin này trong tài liệu được cung cấp.')
            ->assertJsonPath('data.error_code', 'RAG_RUNTIME_CONTEXT_NOT_FOUND');
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
            ->assertJsonPath('data.sources.0.content_preview', 'Khi xe vào bảo trì, chuyển trạng thái maintenance và không phân công chuyến mới.')
            ->assertJsonPath('data.confidence', 'high');
    }

    public function test_chat_handles_groq_timeout_with_local_rag_fallback(): void
    {
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

        $this->mock(GroqService::class, function ($mock): void {
            $mock->shouldReceive('generateContent')
                ->once()
                ->andThrow(new ApiException('Groq API timeout: không nhận được phản hồi sau 30 giây', 504));
        });

        $response = $this->postJson('/api/chat/messages', [
            'message' => 'Quy trình bảo trì xe như thế nào?',
            'task' => 'chat',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.llm_unavailable', true)
            ->assertJsonPath('data.sources.0.title', 'Quy trình bảo trì xe demo');
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
