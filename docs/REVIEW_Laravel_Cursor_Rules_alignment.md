# Review đối chiếu `ship-app-api` với *Xây dựng quy tắc Cursor Laravel nâng cao*

Tài liệu tham chiếu: [Xây dựng quy tắc Cursor Laravel nâng cao.md](./Xây%20dựng%20quy%20tắc%20Cursor%20Laravel%20nâng%20cao.md)

## Tóm tắt

| Tiêu chí (rule doc) | Trạng thái | Ghi chú |
|---------------------|------------|---------|
| Controller mỏng → Service/Action | Một phần | `PayrollController` ủy quyền `PayrollService`; phần lớn CRUD còn logic trực tiếp Model + `HasIndexQuery`. |
| Form Request cho HTTP | Tốt | CRUD + auth + `SyncPermissionsRequest`. |
| Form Request + `validated()` | Đã siết | `AuthController` dùng `LoginRequest` / `RegisterRequest`; `RoleController::syncPermissions` dùng `SyncPermissionsRequest`. |
| DTO / không truyền array thô | Chưa áp dụng | Dữ liệu sau validate vẫn là `array`; chưa có lớp DTO readonly. |
| Repository / Action invokable | Chưa có | Không có `app/Repositories`, `app/Actions`. |
| `declare(strict_types=1)` | API controllers | Toàn bộ `App\Http\Controllers\Api\*` (trừ `BaseController` đã có từ trước) đã có `strict_types`; arch test `toUseStrictTypes()` giữ chuẩn. |
| N+1 / `with()` | Một phần | Nhiều `index` đã `with(...)`; cần rà soát khi thêm quan hệ. |
| Cache / Queue cho tác vụ nặng | Một phần | `ReportsController` dùng cache; export payroll đồng bộ — chưa chuẩn hóa job queue. |
| Kiểm thử | PHPUnit + Pest arch | `php artisan test` chạy Feature/Unit PHPUnit và suite `tests/Arch` (Pest `arch()`). Script `composer pest` gọi `./vendor/bin/pest`. |
| Bảo mật mass assignment | Ổn | Model + `validated()` trên Form Request. |

## Chi tiết theo mục rule

### 1. Kiến trúc Service–Action–Repository

- **Điểm mạnh:** `App\Services\PayrollService` gom logic tạo bảng lương; controller gọi service.
- **Khoảng trống:** Không có tầng Action/Repository; controller vẫn biết nhiều về Eloquent. Hướng cải tiến: tách action nhỏ (vd. `ApprovePayrollAction`) hoặc service theo domain khi luồng phức tạp.

### 2. Form Request & DTO

- **Điểm mạnh:** Chuẩn hóa Form Request cho hầu hết API resource.
- **Đã xử lý:** `AuthController` và `RoleController::syncPermissions` như bảng trên.

### 3. `strict_types` & kiểu trả về

- **Đã xử lý:** Mọi class trong `App\Http\Controllers\Api` có `declare(strict_types=1)`; `AuthController` dùng `JsonResponse` cho các action chính.

### 4. Hiệu năng

- Tiếp tục rule: mọi `index` có quan hệ hiển thị phải `with()` hoặc chỉ select cần thiết.
- Export / báo cáo lớn: cân nhắc `ShouldQueue` + Horizon (rule doc).

### 5. Kiểm thử

- Feature API: `tests/Feature/Api/*`, `tests/Feature/Services/*`.
- **Pest:** `pestphp/pest`, `pest-plugin-laravel`, `pest-plugin-arch`; file `tests/Pest.php`; suite **Arch** trong `phpunit.xml` với `tests/Arch/ApiControllersArchitectureTest.php` (kiểm tra extend `BaseController` + `toUseStrictTypes()` cho namespace API).

### 6. Bảo mật

- Tránh `$request->all()` khi tạo model; ưu tiên `validated()`.
- `APP_DEBUG=false` trên production; CORS/Sanctum qua env.

### 7. Route / middleware (đã chỉnh)

- **`auth:sanctum` (không admin):** `POST /api/auth/logout`, `POST /api/auth/refresh`, `GET /api/user`, `GET /api/payrolls/my-salary` — mọi user đã đăng nhập dùng được.
- **`auth:sanctum` + `role:admin`:** đăng ký user, CRUD resource, báo cáo, quản lý payroll (trừ my-salary), v.v.

## Việc đã triển khai (theo backlog review)

- Route tách như mục 7; `my-salary` cho nhân viên không cần admin.
- `strict_types` trên toàn bộ API controllers; arch test bảo vệ.
- Pest + plugin Laravel/Arch; `phpunit.xml` thêm testsuite Arch; `composer run pest`.

## Việc nên làm tiếp

1. Giới thiệu DTO (hoặc `spatie/laravel-data`) cho luồng payroll / invoice phức tạp.
2. Queue cho export PDF/Excel và email.
3. Viết thêm test Pest cho luồng mới (hoặc migrate dần Feature sang Pest).
4. Xóa hoặc hoàn thiện `ExampleController` nếu không dùng production.

---
*Cập nhật theo review đối chiếu với docs quy tắc Cursor Laravel nâng cao.*
