# Báo cáo Kết quả Kiểm thử (API Test Report)

**Ngày chạy report:** 2026-04-09
**Tổng quan:**
- Tổng số Test Cases: 116 (365 Assertions)
- Số Test Pass: 116
- Số Test Fail: 0 (ĐÃ FIX TOÀN BỘ lỗi cũ)
- Thời gian chạy: ~21.10s

---

## 1. Chi tiết các lỗi hiện tại (Failures)

### Lỗi 1: `Tests\Feature\Api\AttendancesApiTest > admin can create attendance` *(Đã Fix)*
- **Mã lỗi HTTP:** `500 Internal Server Error` (Kỳ vọng 201)
- **Log Error:** `Carbon\Exceptions\InvalidFormatException: The separation symbol could not be found... Trailing data`
- **Nguyên nhân cốt lõi:** Lỗi xử lý định dạng ngày giờ ở file `app/Services/AttendanceLateNotificationService.php`, dòng 26. Hàm `Carbon::createFromFormat('H:i:s', ...)` đang nhận vào một chuỗi dạng đầy đủ `2026-04-09 08:0...` (có chứa cả phần ngày và giây) thay vì format `H:i:s` thuần tuý.
- **Trạng thái:** Đã fix bằng cách sử dụng `Carbon::parse()` thay vì `createFromFormat` để tăng độ tương thích.

### Lỗi 2: `Tests\Feature\Api\TripsApiTest > admin can create trip` *(Đã Fix)*
- **Mã lỗi HTTP:** `422 Unprocessable Entity` (Kỳ vọng 201)
- **Log Error:** Validation failed: `"The selected driver id is invalid."`
- **Nguyên nhân cốt lõi:** Trong Test data truyền vào, trường `driver_id` lấy P.K của bảng drivers thay vì lấy `employee_id`. Rule validation đang xét foreign key trỏ sang bảng `employees`.
- **Trạng thái:** Đã fix test truyền đúng `$driver->employee_id`.

### Lỗi 3 & 4: `Tests\Feature\Api\TripsApiTest` (Update và Delete) *(Đã Fix)*
- **Mã lỗi:** SQL `QueryException`
- **Log Error:** `Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails` (`driver_id` chặn FK tại bảng `trips`).
- **Nguyên nhân cốt lõi:** Do truyền sai Reference Type giống lỗi số 2. Table trips bắt buộc `driver_id` foreign key tới `employees.id`. Môi trường test đang mock lỗi giá trị này.
- **Trạng thái:** Đã fix bằng cách sử dụng đúng ID tham chiếu trong Test.

### Lỗi 5: `Tests\Arch\ApiControllersValidationArchitectureTest` *(Đã Fix)*
- **Tên Test:** `API controllers avoid inline validation`
- **Nguyên nhân cốt lõi:** Controller `ChatController.php` đang sử dụng code `$request->validate(...)` trực tiếp trong phương thức thay vì FormRequest.
- **Trạng thái:** Đã fix bằng cách sử dụng `GetChatMessagesRequest` và `GetChatSessionsRequest` chuyên dụng đúng chuẩn System Guardrails.

---

## 2. Các Module Hoạt động tốt (Pass)

Các module nghiệp vụ chính đang pass 100% test cases:
- ✔️ **Quyền Truy cập (Auth/Authorization)**: Đăng nhập, token JWT/Sanctum, RBAC Roles, Phân quyền thao tác theo module.
- ✔️ **Vận hành (Operations / Fleet)**: Quản lý chi phí xe, Gán xe cho tài xế, Công ty, Khách hàng, Hoá đơn (Invoices).
- ✔️ **Dữ liệu tổ chức (Organization)**: Companies, Departments, Offices, Positions.
- ✔️ **Nhân sự (HR)**: Employees, Phụ cấp (Allowances), Khấu trừ (Deductions).
- ✔️ **Core Payroll (Lương)**: Quản lý Lifecycle bảng lương (`draft -> approved -> locked`), Xuất Excel, Tự xem lương (My salary), Dịch vụ tính toán Lương (`PayrollService` Unit tests).
- ✔️ **Dashboard & Báo cáo (Reports)**: Tổng quan biểu đồ và API trả dữ liệu tổng hợp.

---

## 3. Khuyến nghị và Đề xuất Hành động (Next Steps)

1. **Khắc phục lỗi ngày tháng:** Developer phụ trách module Điểm danh (`AttendanceLateNotificationService`) cần check lại định dạng payload từ Test để fix logic ngày bằng hàm `Carbon::parse()` thay vì ép cứng `createFromFormat('H:i:s')`.
2. **Khắc phục Test dữ liệu ảo của Trips:** Fix lại các Factory/Mock data ở file `TripsApiTest` để test case sinh ra một `Driver` hợp lệ (có employee_id tồn tại).
3. **Thanh lọc Code Controller:** Ai đó đã lỡ thêm logic validation trực tiếp vào một Controller API nào đó. Cần tìm file đó và refactor để nó đạt chuẩn Design Pattern như đã quy định.
