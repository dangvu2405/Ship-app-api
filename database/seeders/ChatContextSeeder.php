<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\KnowledgeArticle;
use Illuminate\Database\Seeder;

class ChatContextSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            [
                'category' => 'REVENUE',
                'title' => 'Chuẩn trả lời doanh thu theo văn phòng',
                'content' => 'Khi người dùng hỏi doanh thu theo văn phòng, bắt buộc xác định mã văn phòng dạng OFFxxx.
Nếu có mã văn phòng hợp lệ và có dữ liệu, trả doanh thu + số chuyến của văn phòng trong kỳ phân tích.
Nếu không tìm thấy văn phòng, trả rõ: "Không tìm thấy văn phòng <mã> trong công ty hiện tại."
Nếu có văn phòng nhưng chưa phát sinh dữ liệu, trả rõ: "Chưa có dữ liệu doanh thu cho <mã> trong kỳ này."
Không suy đoán số liệu khi context không có.',
                'tags' => ['revenue', 'office', 'offxxx', 'thiếu dữ liệu', 'chat'],
            ],
            [
                'category' => 'DRIVER',
                'title' => 'Chuẩn trả lời danh sách tài xế theo office',
                'content' => 'Khi người dùng hỏi "tài xế của office", cần lấy mã office trước (ví dụ OFF006).
Nếu chưa có mã office, yêu cầu bổ sung đúng 1 thông tin: mã văn phòng.
Nếu có mã office và tìm thấy dữ liệu, trả danh sách tài xế theo từng gạch đầu dòng: mã tài xế, tên, trạng thái.
Nếu không có dữ liệu, trả rõ: "Không có dữ liệu tài xế cho <mã office>."',
                'tags' => ['driver', 'office', 'off006', 'danh sách', 'chat'],
            ],
            [
                'category' => 'GENERAL',
                'title' => 'Nguyên tắc không suy đoán khi thiếu dữ liệu',
                'content' => 'Ưu tiên dữ liệu thực tế từ hệ thống (context data) trước tài liệu mô tả nghiệp vụ.
Nếu dữ liệu vận hành thiếu hoặc rỗng, phải nói rõ trạng thái thiếu dữ liệu và hỏi đúng 1 câu ngắn để bổ sung.
Không tạo số liệu giả cho doanh thu, lương, số chuyến, vi phạm hoặc danh sách nhân sự.
Câu trả lời nên ngắn, rõ và theo gạch đầu dòng để dễ hành động.',
                'tags' => ['no-hallucination', 'missing-data', 'chat-style', 'rag'],
            ],
            [
                'category' => 'TRACKING',
                'title' => 'Chuẩn trả lời lịch trình nhân viên',
                'content' => 'Với câu hỏi lịch trình nhân viên/tài xế, cần xác định ít nhất khoảng thời gian (từ ngày - đến ngày).
Nếu thiếu thời gian, yêu cầu bổ sung đúng 1 câu ngắn.
Khi có đủ dữ liệu, trả theo cấu trúc: kết quả chính, dữ liệu liên quan, đề xuất tiếp theo.
Không trả chung chung nếu thiếu bộ lọc văn phòng hoặc tài xế cụ thể.',
                'tags' => ['lịch trình', 'driver-schedule', 'tracking', 'context'],
            ],
        ];

        foreach ($articles as $article) {
            KnowledgeArticle::firstOrCreate(
                ['title' => $article['title']],
                array_merge($article, [
                    'company_id' => null,
                    'tenant_priority' => 10,
                    'is_active' => true,
                ])
            );
        }
    }
}
