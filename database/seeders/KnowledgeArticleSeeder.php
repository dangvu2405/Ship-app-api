<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\KnowledgeArticle;
use Illuminate\Database\Seeder;

final class KnowledgeArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            [
                'category' => 'TRIPS',
                'title' => 'Vòng đời chuyến xe CETA',
                'content' => 'Chuyến xe đi qua các trạng thái pending, in_progress, completed hoặc cancelled. Điều phối có thể assign, start, deliver, complete, cancel, change-vehicle và change-driver theo state machine trong spec.',
                'tags' => ['trips', 'state-machine', 'dispatch'],
            ],
            [
                'category' => 'COSTS',
                'title' => 'Chi phí chuyến và phê duyệt vượt định mức',
                'content' => 'Chi phí phát sinh được ghi vào trip_costs theo cost_categories. Nếu amount vượt approval_threshold, hệ thống tạo cost_approval_requests để kế toán hoặc quản lý duyệt.',
                'tags' => ['trip_costs', 'cost_approval_requests', 'approval'],
            ],
            [
                'category' => 'ACCOUNTING',
                'title' => 'Đối soát và ghi nhận thanh toán',
                'content' => 'Kế toán tạo reconciliation_sessions cho các chuyến completed/unpaid, điều chỉnh reconciliation_items nếu cần, confirm phiên đối soát rồi ghi payment_records theo khách hàng.',
                'tags' => ['reconciliation', 'payment_records', 'debt'],
            ],
            [
                'category' => 'FLEET',
                'title' => 'Quản lý xe, giấy tờ và bảo dưỡng',
                'content' => 'Xe dùng vehicle_types, vehicle_documents, vehicle_assignments, maintenance_schedules và maintenance_records. Mỗi xe hoặc tài xế chỉ nên có một assignment active với to_date = null.',
                'tags' => ['vehicles', 'documents', 'maintenance'],
            ],
            [
                'category' => 'DRIVERS',
                'title' => 'Hồ sơ tài xế và lịch làm việc',
                'content' => 'Tài xế thuộc driver_teams, có driver_documents, driver_work_schedules và leave_requests. Lịch làm việc được quản lý theo ngày, xe, ca và trạng thái draft/submitted/approved/locked.',
                'tags' => ['drivers', 'driver_work_schedules', 'leave_requests'],
            ],
        ];

        foreach ($articles as $article) {
            KnowledgeArticle::query()->updateOrCreate(
                ['category' => $article['category'], 'title' => $article['title']],
                [
                    'content' => $article['content'],
                    'tags' => $article['tags'],
                    'source' => 'spec.md',
                    'is_active' => true,
                ],
            );
        }
    }
}
