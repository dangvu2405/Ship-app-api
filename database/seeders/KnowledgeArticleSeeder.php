<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\KnowledgeArticle;
use Illuminate\Database\Seeder;

class KnowledgeArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [

            // ─── PAYROLL ─────────────────────────────────────────────────────────
            [
                'category' => 'PAYROLL',
                'title'    => 'Quy trình tính lương tài xế hàng tháng',
                'content'  => 'Lương thực lĩnh = Lương cơ bản × (ngày công thực tế / ngày công chuẩn 22) + Thưởng chuyến + Phụ cấp + Lương làm thêm giờ + Phụ cấp ca đêm + Lương ngày lễ - Khấu trừ vi phạm - Khấu trừ ngày nghỉ không lương - Thuế TNCN.

Ngày công chuẩn mặc định là 22 ngày/tháng. Nếu tài xế vào/ra giữa tháng, lương cơ bản được tính theo tỷ lệ (proration).

Trạng thái bảng lương: draft = đang soạn, locked = đã khoá (không sửa được), approved = đã duyệt chi.',
                'tags'     => ['lương', 'payroll', 'tính lương', 'proration'],
            ],
            [
                'category' => 'PAYROLL',
                'title'    => 'Thưởng chuyến và phụ cấp tài xế',
                'content'  => 'Thưởng chuyến (trip_bonus) được tính theo số chuyến hoàn thành và tổng km trong tháng, dựa trên bảng TripBonusRule của công ty.

Phụ cấp (allowance) bao gồm: phụ cấp đi đường, phụ cấp ăn uống, phụ cấp xăng xe.

Lương làm thêm giờ (overtime_pay) tính theo số giờ OT thực tế × hệ số OT (thường 1.5×).

Phụ cấp ca đêm (night_shift_allowance) áp dụng cho ca làm việc từ 22:00–06:00.',
                'tags'     => ['thưởng', 'phụ cấp', 'overtime', 'ca đêm'],
            ],
            [
                'category' => 'PAYROLL',
                'title'    => 'Các khoản khấu trừ trong bảng lương',
                'content'  => 'Các khoản khấu trừ bao gồm:
1. Khấu trừ vi phạm (violation_deduction): tổng tiền phạt từ các vi phạm đã confirmed trong tháng.
2. Khấu trừ ngày nghỉ không lương (leave_unpaid_deduction): số ngày nghỉ không lương × lương ngày.
3. Thuế TNCN (tax): khấu trừ thuế thu nhập cá nhân.
4. Chi phí nhiên liệu vượt định mức (fuel_cost): nếu tài xế dùng nhiên liệu vượt hạn mức.

Nếu tổng khấu trừ > lương, hệ thống cảnh báo để kiểm tra lại.',
                'tags'     => ['khấu trừ', 'vi phạm', 'thuế', 'nghỉ không lương'],
            ],

            // ─── TRACKING ────────────────────────────────────────────────────────
            [
                'category' => 'TRACKING',
                'title'    => 'Các trạng thái chuyến xe trong hệ thống',
                'content'  => 'Chuyến xe có các trạng thái sau:
- pending: Chuyến đã được tạo, chờ tài xế xác nhận hoặc chờ xuất phát.
- in_progress: Tài xế đang chạy, chuyến đang thực hiện.
- completed: Chuyến đã hoàn thành, hàng đã giao đến điểm đích.
- cancelled: Chuyến bị hủy (có thể do khách hàng hoặc lý do nội bộ).

Chuyến completed mới được tính vào thưởng km và bảng lương. Chuyến cancelled không được tính.',
                'tags'     => ['trạng thái', 'chuyến xe', 'pending', 'in_progress', 'completed'],
            ],
            [
                'category' => 'TRACKING',
                'title'    => 'Quy trình tạo và theo dõi chuyến xe',
                'content'  => 'Tạo chuyến: Điều phối viên tạo chuyến mới với thông tin: điểm đi (start_point), điểm đến (end_point), tài xế (driver_id), xe (vehicle_id), khách hàng (customer_id), quãng đường (distance_km), thời gian dự kiến.

Phân công tài xế: Tài xế phải ở trạng thái available và không có chuyến in_progress khác.

Theo dõi: Chuyến được theo dõi qua mã chuyến (code). Điều phối viên có thể xem danh sách chuyến theo ngày, tài xế, hoặc trạng thái.

Hoàn thành chuyến: Cập nhật trạng thái thành completed và ghi nhận end_time.',
                'tags'     => ['tạo chuyến', 'phân công', 'điều phối', 'theo dõi'],
            ],

            // ─── COMPLIANCE ──────────────────────────────────────────────────────
            [
                'category' => 'COMPLIANCE',
                'title'    => 'Giấy tờ và chứng chỉ bắt buộc của tài xế',
                'content'  => 'Tài xế phải có và duy trì còn hạn các giấy tờ sau:
1. Bằng lái xe (license_no + license_class + expired_date): Phải phù hợp với loại xe được phân công.
2. Bảo hiểm lái xe (driver_insurance_no + driver_insurance_expired_date): Bảo hiểm tai nạn nghề nghiệp.
3. Giấy khám sức khoẻ (health_certificate_no + health_certificate_expired_date): Cần gia hạn định kỳ.

Hệ thống tự động cảnh báo khi giấy tờ còn dưới 30 ngày hết hạn.',
                'tags'     => ['chứng chỉ', 'bằng lái', 'bảo hiểm', 'sức khoẻ'],
            ],
            [
                'category' => 'COMPLIANCE',
                'title'    => 'Hạn chế phân công khi giấy tờ hết hạn',
                'content'  => 'Khi bằng lái xe hết hạn: Tài xế bị hạn chế phân công chuyến mới. Điều phối viên nhận cảnh báo khi cố gắng assign chuyến cho tài xế này.

Khi bảo hiểm hết hạn: Cần gia hạn trước khi tài xế được phân công chuyến tiếp.

Khi giấy sức khoẻ hết hạn: Tương tự, cần có giấy mới trước khi tiếp tục làm việc.

Ngưỡng cảnh báo sớm: Hệ thống cảnh báo trước 30 ngày. Khi còn < 7 ngày, cảnh báo mức nghiêm trọng.',
                'tags'     => ['hạn chế', 'phân công', 'hết hạn', 'cảnh báo'],
            ],
            [
                'category' => 'COMPLIANCE',
                'title'    => 'Quy trình gia hạn chứng chỉ tài xế',
                'content'  => 'Bước 1: Tài xế hoặc HR nộp hồ sơ gia hạn trước khi hết hạn ít nhất 15 ngày.
Bước 2: Sau khi có giấy tờ mới, cập nhật ngày hết hạn mới trong hồ sơ tài xế trên hệ thống.
Bước 3: Đính kèm ảnh giấy tờ mới (license_image_url hoặc các trường tương ứng).
Bước 4: Hệ thống tự động mở lại quyền phân công sau khi ngày hết hạn được cập nhật hợp lệ.',
                'tags'     => ['gia hạn', 'cập nhật', 'chứng chỉ', 'hồ sơ'],
            ],

            // ─── FUEL ────────────────────────────────────────────────────────────
            [
                'category' => 'FUEL',
                'title'    => 'Chi phí nhiên liệu và định mức xe',
                'content'  => 'Chi phí nhiên liệu được ghi nhận qua VehicleExpense với type = "fuel". Mỗi bản ghi lưu: ngày chi (expense_date), xe (vehicle_id), tài xế (driver_id), số tiền (amount), ghi chú.

Định mức nhiên liệu được xác định theo loại xe và quãng đường chuyến. Nếu chi phí thực tế vượt định mức, phần vượt có thể bị khấu trừ vào lương tài xế.

Tổng chi phí nhiên liệu tháng được tổng hợp vào trường fuel_cost trong PayrollLine.',
                'tags'     => ['nhiên liệu', 'định mức', 'xăng', 'fuel'],
            ],
            [
                'category' => 'FUEL',
                'title'    => 'Đối soát nhiên liệu hàng tháng',
                'content'  => 'Quy trình đối soát:
1. Tổng hợp chi phí nhiên liệu thực tế từ VehicleExpense theo từng xe/tài xế trong tháng.
2. So sánh với định mức tính từ tổng quãng đường chạy (total_distance_km từ PayrollLine).
3. Nếu vượt định mức > 10%: đánh dấu cần xem xét, thông báo cho quản lý.
4. Ghi nhận kết quả đối soát vào bảng lương (trường fuel_cost trong PayrollLine).',
                'tags'     => ['đối soát', 'nhiên liệu', 'vượt định mức', 'tháng'],
            ],

            // ─── GENERAL ─────────────────────────────────────────────────────────
            [
                'category' => 'GENERAL',
                'title'    => 'Tổng quan hệ thống quản trị vận tải Company Ship',
                'content'  => 'Company Ship là hệ thống quản trị vận tải toàn diện gồm các module:
- Quản lý chuyến xe: Tạo, phân công, theo dõi chuyến, quản lý lộ trình.
- Quản lý tài xế: Hồ sơ, giấy tờ, phân công, lịch làm việc.
- Bảng lương: Tính toán tự động, duyệt và xuất lương hàng tháng.
- Nhiên liệu: Theo dõi chi phí, đối soát định mức.
- Tuân thủ: Kiểm soát giấy tờ, cảnh báo hết hạn.
- Nhân sự: Quản lý nghỉ phép, OT, vi phạm, chấm công.',
                'tags'     => ['hệ thống', 'tổng quan', 'module', 'company ship'],
            ],
            [
                'category' => 'GENERAL',
                'title'    => 'Quy trình xử lý vi phạm tài xế',
                'content'  => 'Vi phạm tài xế bao gồm các loại: speeding (vượt tốc độ), route_deviation (lệch tuyến), fuel_misuse (gian lận nhiên liệu), behavior (hành vi không phù hợp), accident (tai nạn), other.

Quy trình:
1. Ghi nhận vi phạm với mô tả và số tiền phạt đề xuất.
2. Trạng thái ban đầu: pending.
3. Quản lý xác nhận: confirmed → tiền phạt được trừ vào lương tháng đó.
4. Tài xế khiếu nại: disputed → cần xem xét thêm.
5. Miễn phạt: waived → không trừ lương.',
                'tags'     => ['vi phạm', 'phạt', 'khiếu nại', 'xử lý'],
            ],
            [
                'category' => 'GENERAL',
                'title'    => 'Quản lý nghỉ phép và đơn xin nghỉ',
                'content'  => 'Các loại nghỉ phép: Nghỉ phép năm (annual leave), nghỉ ốm (sick leave), nghỉ thai sản, nghỉ không lương, và các loại khác theo quy định công ty.

Quy trình xin nghỉ:
1. Tài xế/nhân viên tạo đơn xin nghỉ (LeaveRequest) với ngày bắt đầu, kết thúc và lý do.
2. Quản lý duyệt: approved/rejected.
3. Nếu approved: hệ thống trừ số ngày vào LeaveBalance, cập nhật bảng lương.
4. Nghỉ không lương: được khấu trừ vào lương theo công thức lương ngày × số ngày nghỉ.',
                'tags'     => ['nghỉ phép', 'leave', 'đơn xin nghỉ', 'phép năm'],
            ],
        ];

        foreach ($articles as $article) {
            KnowledgeArticle::firstOrCreate(
                ['title' => $article['title']],
                array_merge($article, ['is_active' => true, 'company_id' => null])
            );
        }
    }
}
