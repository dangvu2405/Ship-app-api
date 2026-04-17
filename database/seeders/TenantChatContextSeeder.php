<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\KnowledgeArticle;
use App\Models\Office;
use Illuminate\Database\Seeder;

class TenantChatContextSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::query()->select(['id', 'code', 'name'])->get();

        foreach ($companies as $company) {
            $officeCodes = Office::query()
                ->where('company_id', $company->id)
                ->orderBy('code')
                ->limit(3)
                ->pluck('code')
                ->all();

            $officeHint = $officeCodes !== [] ? implode(', ', $officeCodes) : 'OFF001';
            $companyName = (string) $company->name;
            $companyCode = (string) $company->code;

            $articles = [
                [
                    'category' => 'DRIVER',
                    'title' => sprintf('[%s] Tra cứu tài xế theo văn phòng', $companyCode),
                    'content' => sprintf(
                        'Khi người dùng hỏi tài xế theo office của %s, cần ưu tiên mã văn phòng dạng OFFxxx.
Nếu người dùng chưa cung cấp mã office, yêu cầu bổ sung đúng 1 thông tin ngắn: mã văn phòng (ví dụ: %s).
Khi có dữ liệu, trả theo gạch đầu dòng gồm: mã tài xế, tên tài xế, trạng thái.
Nếu văn phòng không tồn tại hoặc chưa có tài xế, trả rõ trạng thái thiếu dữ liệu thay vì suy đoán.',
                        $companyName,
                        $officeHint
                    ),
                    'tags' => ['tenant', 'driver', 'office', 'offxxx', 'company-specific'],
                ],
                [
                    'category' => 'TRACKING',
                    'title' => sprintf('[%s] Tra cứu lịch trình tài xế', $companyCode),
                    'content' => sprintf(
                        'Với yêu cầu lịch trình tài xế của %s, cần tối thiểu: khoảng thời gian (từ ngày - đến ngày).
Nếu thiếu thời gian hoặc thiếu office/driver filter, yêu cầu bổ sung đúng 1 câu ngắn.
Nếu có đủ dữ liệu, trả theo cấu trúc: kết quả chính, dữ liệu liên quan, đề xuất tiếp theo.
Không được trả chung chung khi thiếu bộ lọc quan trọng.',
                        $companyName
                    ),
                    'tags' => ['tenant', 'schedule', 'tracking', 'driver-schedule', 'company-specific'],
                ],
                [
                    'category' => 'REVENUE',
                    'title' => sprintf('[%s] Tra cứu doanh thu theo office', $companyCode),
                    'content' => sprintf(
                        'Khi hỏi doanh thu theo office tại %s, bắt buộc xác định mã office trước.
Nếu có office và có dữ liệu, trả doanh thu + số chuyến theo kỳ phân tích hiện tại.
Nếu office không tồn tại: trả "Không tìm thấy văn phòng <mã> trong công ty hiện tại."
Nếu có office nhưng chưa có doanh thu: trả "Chưa có dữ liệu doanh thu cho <mã> trong kỳ này."',
                        $companyName
                    ),
                    'tags' => ['tenant', 'revenue', 'office', 'offxxx', 'company-specific'],
                ],
            ];

            foreach ($articles as $article) {
                KnowledgeArticle::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'title' => $article['title'],
                    ],
                    array_merge($article, [
                        'tenant_priority' => 100,
                        'is_active' => true,
                    ])
                );
            }
        }
    }
}
