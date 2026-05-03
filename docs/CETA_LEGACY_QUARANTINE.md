# CETA Legacy Quarantine

Nguồn chuẩn hiện tại là `spec.md`. `database.md` chỉ dùng để nhận diện các domain legacy còn sót lại trong codebase.

Các file dưới đây đã được duyệt hard-delete và đã bị loại khỏi codebase. Tài liệu này giữ lại lịch sử để đối chiếu lý do xóa.

## Route Files Mồ Côi
- Đã xóa `routes/ceta_company.php`: còn RBAC cũ qua `roles`/`permissions` và invoice surface cũ.
- Đã xóa `routes/ceta_office.php`: còn office/org, attendance, overtime, violation, vehicle expense, schedule template legacy.
- Đã xóa `routes/ceta_platform.php`: còn platform extras/legacy aliases.

## Route Files Đã Gộp
- Đã xóa `routes/ceta_spec.php`: đã được gộp trực tiếp vào `routes/api.php` để chỉ còn một active API route file.

## Controllers Legacy Hoặc Không Còn Active
- Đã xóa các controller Payroll, RBAC cũ, office/org, attendance, overtime, violation, vehicle expense, schedule template, workforce/public holiday legacy.
- Đã xóa thêm các controller không còn active nhưng phụ thuộc trực tiếp vào file legacy đã xóa: `DriverController`, `DriverScheduleController`, `ReportsController`, `AiAdvisorController`, `User/UserController`.

## Request Classes Legacy Hoặc Không Còn Active
- Đã xóa các request trong các thư mục Payroll, PayrollAdjustment, Role, Office, Department, Position, Attendance, Overtime, Violation, VehicleExpense, WorkScheduleTemplate, TripBonusRule, Schedule, Workforce, PublicHoliday.
- Đã xóa `Report/PayrollSummaryRequest.php` và `Report/ExportPayrollReportRequest.php`.

## Models Legacy Cần Duyệt Trước Khi Xóa
- Đã xóa các model Payroll, RBAC cũ, office/org, attendance, overtime, violation, vehicle expense, schedule template, trip bonus, night shift, public holiday legacy.
- Đã xóa thêm các observer/job/service phụ thuộc trực tiếp vào các model trên.

## Active Surface Sau Chuẩn Hóa
- `routes/api.php`
- `routes/web.php`
- `routes/console.php`
- `app/Http/Controllers/Api/CetaSpecController.php`
- Auth, upload và chat endpoints còn được mount trực tiếp từ `routes/api.php`.
