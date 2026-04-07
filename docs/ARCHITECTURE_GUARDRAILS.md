# Architecture Guardrails (API)

## Mục tiêu
Giữ codebase gọn, dễ bảo trì, đúng chuẩn Service-oriented cho Laravel API.

## Quy ước hiện tại

1. **Controller mỏng**
   - Chỉ nhận request, gọi service, trả response JSON envelope.
   - Không xử lý nghiệp vụ phức tạp trong controller.

2. **Validation tách riêng**
   - Dùng `FormRequest` cho mọi input cần validate.
   - Không dùng inline validation (`$request->validate`, `Validator::make`) trong API controllers.

3. **Service theo trách nhiệm**
   - `AuthService`: login/register/logout/refresh.
   - `ReportService`: dashboard/payroll summary.
   - `PayrollService`: generate + tính toán payroll.
   - `PayrollWorkflowService`: update/approve/lock/delete + state guard.
   - `PayrollQueryService`: export payload + my salary query.

4. **Workflow payroll chuẩn**
   - State flow: `draft -> approved -> locked`.
   - Chặn transition sai và chặn sửa/xóa khi `locked`.

5. **Kiểm thử kiến trúc bắt buộc**
   - `tests/Arch/ApiControllersArchitectureTest.php`
   - `tests/Arch/ApiControllersValidationArchitectureTest.php`

## Lệnh verify nhanh

- `php artisan test --testsuite=Arch`
- `php artisan test tests/Feature/Api/AuthApiTest.php`
- `php artisan test tests/Feature/Api/PayrollApiTest.php`
- `php artisan test tests/Feature/Api/ReportsApiTest.php`

## Tiêu chí review khi mở rộng module mới

- Có `FormRequest` chưa?
- Có service cho nghiệp vụ chính chưa?
- Controller còn logic query/transaction quá nặng không?
- Có test Feature cho happy path + permission + invalid input chưa?
- Nếu có state machine: đã có test transition fail/success chưa?
