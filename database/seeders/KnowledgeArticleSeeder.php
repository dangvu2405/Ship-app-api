<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\KnowledgeArticle;
use App\Models\User;
use Illuminate\Database\Seeder;

final class KnowledgeArticleSeeder extends Seeder
{
    public function run(): void
    {
        $createdBy = User::query()
            ->where('email', 'admin@abctransport.com')
            ->orWhere('username', 'admin')
            ->value('id');

        $articles = [
            [
                'category' => 'FLEET',
                'title' => 'Quản lý xe trong Ship-app',
                'content' => 'Mỗi xe có biển số, loại xe, trạng thái hoạt động và hồ sơ giấy tờ. Xe ở trạng thái maintenance hoặc broken không được đưa vào danh sách phân công chuyến. Khi đổi trạng thái xe, hệ thống cần ghi nhận lý do và người thao tác để phục vụ audit vận hành.',
                'tags' => ['vehicles', 'fleet', 'status'],
                'metadata' => ['module' => 'vehicles', 'demo_questions' => ['Có những loại xe nào đang được quản lý?', 'Xe bảo trì có được phân công không?']],
            ],
            [
                'category' => 'DRIVERS',
                'title' => 'Quản lý tài xế và trạng thái sẵn sàng',
                'content' => 'Tài xế có mã hệ thống, thông tin liên hệ, hạng giấy phép, ngày hết hạn giấy phép và trạng thái available, busy hoặc offline. Khi phân công chuyến, điều phối cần kiểm tra lịch làm việc, đơn nghỉ phép đã duyệt và cảnh báo giấy phép sắp hết hạn.',
                'tags' => ['drivers', 'license', 'availability'],
                'metadata' => ['module' => 'drivers', 'demo_questions' => ['Tài xế cần điều kiện gì để được phân công?', 'GPLX sắp hết hạn xử lý thế nào?']],
            ],
            [
                'category' => 'HR',
                'title' => 'Quản lý nhân sự và nghỉ phép',
                'content' => 'Nhân sự có thể tạo leave request theo ngày hoặc khoảng ngày. Khi đơn nghỉ phép được approve, tài xế tương ứng không được hiển thị trong danh sách phân công cho ngày đó. Admin hoặc người có quyền duyệt có thể approve, reject hoặc cancel theo quy trình nhân sự.',
                'tags' => ['leave_requests', 'hr', 'approval'],
                'metadata' => ['module' => 'leave-requests', 'demo_questions' => ['Đơn nghỉ phép ảnh hưởng tới phân công thế nào?']],
            ],
            [
                'category' => 'TRIPS',
                'title' => 'Vòng đời chuyến xe CETA',
                'content' => 'Chuyến xe đi qua các trạng thái pending, in_progress, completed hoặc cancelled. Điều phối có thể assign tài xế và xe, start chuyến, xác nhận giao hàng, complete hoặc cancel theo state machine trong spec. Chuyến completed không nên sửa trạng thái hoặc xóa.',
                'tags' => ['trips', 'state-machine', 'dispatch'],
                'metadata' => ['module' => 'trips', 'demo_questions' => ['Quy trình assign trip là gì?', 'Chuyến completed có xóa được không?']],
            ],
            [
                'category' => 'SCHEDULE',
                'title' => 'Lịch trình vận chuyển và phân công tài xế',
                'content' => 'Lịch trình vận chuyển cần có ngày chạy, điểm lấy hàng, điểm giao hàng, tài xế và xe. Khi phân công, hệ thống loại xe đang bảo trì hoặc hỏng, loại tài xế có nghỉ phép approved và cảnh báo nếu giấy phép tài xế sắp hết hạn. Một tài xế chỉ nên nhận một lịch làm việc trong cùng ngày.',
                'tags' => ['driver_work_schedules', 'dispatch', 'assignment'],
                'metadata' => ['module' => 'driver-work-schedules', 'demo_questions' => ['Lịch trình vận chuyển được kiểm tra ra sao?']],
            ],
            [
                'category' => 'COSTS',
                'title' => 'Chi phí chuyến và phê duyệt vượt định mức',
                'content' => 'Chi phí phát sinh được ghi vào trip_costs theo cost_categories. Nếu amount vượt approval_threshold, hệ thống tạo cost_approval_requests để kế toán hoặc quản lý duyệt. Chi phí được approve mới nên dùng trong báo cáo đối soát và tính hiệu quả chuyến.',
                'tags' => ['trip_costs', 'cost_approval_requests', 'approval'],
                'metadata' => ['module' => 'accounting-costs', 'demo_questions' => ['Chi phí vượt định mức xử lý thế nào?']],
            ],
            [
                'category' => 'ACCOUNTING',
                'title' => 'Đối soát và ghi nhận thanh toán',
                'content' => 'Kế toán tạo reconciliation_sessions cho các chuyến completed/unpaid, điều chỉnh reconciliation_items nếu cần, confirm phiên đối soát rồi ghi payment_records theo khách hàng.',
                'tags' => ['reconciliation', 'payment_records', 'debt'],
                'metadata' => ['module' => 'accounting', 'demo_questions' => ['Quy trình đối soát diễn ra thế nào?']],
            ],
            [
                'category' => 'MAINTENANCE',
                'title' => 'Quy trình bảo trì xe',
                'content' => 'Bảo trì xe gồm lịch bảo dưỡng định kỳ theo km, ngày hoặc cả hai, và phiếu maintenance_records cho sửa chữa thực tế. Khi xe vào bảo trì, trạng thái xe nên chuyển maintenance để điều phối không phân công chuyến mới. Sau khi hoàn tất, cập nhật odometer, chi phí, ngày hoàn tất và trạng thái xe phù hợp.',
                'tags' => ['vehicles', 'documents', 'maintenance'],
                'metadata' => ['module' => 'maintenance-records', 'demo_questions' => ['Quy trình bảo trì xe như thế nào?']],
            ],
            [
                'category' => 'PERMISSIONS',
                'title' => 'Phân quyền người dùng trong Ship-app',
                'content' => 'Hệ thống có các role super_admin, admin, dispatcher, accountant và viewer. Admin có toàn quyền trong công ty. Các role khác cần kiểm tra user_permissions theo module và action như can_view, can_create, can_edit, can_delete, can_approve và can_export.',
                'tags' => ['users', 'roles', 'permissions'],
                'metadata' => ['module' => 'settings-users', 'demo_questions' => ['Hệ thống hỗ trợ phân quyền người dùng ra sao?']],
            ],
            [
                'category' => 'INCIDENTS',
                'title' => 'Quy trình xử lý sự cố trên đường',
                'content' => 'Khi xe gặp sự cố trên đường, tài xế cần báo điều phối, ghi nhận vị trí, mô tả sự cố, ảnh hiện trường nếu có và mức độ ảnh hưởng. Điều phối quyết định đổi xe, đổi tài xế, cập nhật trạng thái chuyến hoặc tạo maintenance record. Các quyết định quan trọng cần có audit log.',
                'tags' => ['incident', 'vehicle-breakdown', 'dispatch'],
                'metadata' => ['module' => 'dispatch', 'demo_questions' => ['Tài xế cần làm gì khi xe gặp sự cố?']],
            ],
            [
                'category' => 'REPORTS',
                'title' => 'Báo cáo vận hành đội xe',
                'content' => 'Báo cáo vận hành nên theo dõi số chuyến, doanh thu, chi phí, tỷ lệ chuyến hoàn thành, tình trạng xe, tình trạng giấy tờ, tài xế bận/rảnh và cảnh báo bảo trì. Khi không có dữ liệu thực tế, chatbot phải nói rõ chưa có dữ liệu thay vì tự tạo số liệu.',
                'tags' => ['reports', 'kpi', 'operations'],
                'metadata' => ['module' => 'reports', 'demo_questions' => ['Báo cáo vận hành gồm những chỉ số nào?']],
            ],
            [
                'category' => 'GENERAL',
                'title' => 'Nguyên tắc trả lời của chatbot RAG',
                'content' => 'Chatbot ưu tiên trả lời dựa trên tài liệu nội bộ và dữ liệu vận hành thật. Nếu tài liệu không đủ hoặc thiếu dữ liệu runtime, chatbot phải nói rõ phần còn thiếu, không bịa số liệu doanh thu, lương, chuyến xe hoặc danh sách nhân sự.',
                'tags' => ['rag', 'chatbot', 'no-hallucination'],
                'metadata' => ['module' => 'chatbot', 'demo_questions' => ['Chatbot có được tự đoán số liệu không?']],
            ],
        ];

        foreach ($articles as $article) {
            KnowledgeArticle::query()->updateOrCreate(
                ['company_id' => null, 'category' => $article['category'], 'title' => $article['title']],
                [
                    'content' => $article['content'],
                    'tags' => $article['tags'],
                    'status' => 'published',
                    'source' => 'seed:knowledge-article',
                    'metadata' => $article['metadata'],
                    'embedding' => null,
                    'created_by' => $createdBy,
                    'tenant_priority' => 10,
                    'is_active' => true,
                ],
            );
        }
    }
}
