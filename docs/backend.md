# Backend Cheatsheet (Company Ship ERP)

Đây là tài liệu bỏ túi (Cheatsheet) nhanh dành cho Backend Developer bảo trì và phát triển tính năng mới cho dự án. Codebase phải luôn sạch sẽ và tuân thủ tuyệt đối chuẩn Service-Oriented (Senior Laravel).

---

## 1. Thiết kế Kiến trúc (Tuyệt đối tuân thủ)

Luồng đi của một API Endpoint chuẩn trong dự án:

1. **Route (`routes/api.php`)** -> Định nghĩa route với Resource, gán middleware tương ứng (`auth:sanctum` và Role).
2. **FormRequest (`app/Http/Requests/...`)** -> Nơi duy nhất để Authorize và Validate dữ liệu.
3. **Controller (`app/Http/Controllers/Api/...`)** -> Inject Service vào Constructor. Controller nhận `$request->validated()`, truyền qua cho Service xử lý, nhận kết quả và bọc lại qua Envelope (`success`, `message`). KHÔNG CÓ LOGIC NGHIỆP VỤ NÀO NẰM LẠI Ở ĐÂY.
4. **Service (`app/Services/...`)** -> Tính toán, Handle State Machine, Gọi Transaction với Database.
5. **Model (`app/Models/...`)** -> Gắn Relationships (`belongsTo`, `hasMany`), cấu hình `$fillable` và định nghĩa Query Scope.

---

## 2. Hệ sinh thái Services đang có sẵn

Khi tạo logic mới, xem xét xem bạn có thể tái sử dụng Method ở đâu:

- **`AuthService`**: `login`, `register`, `logout`, `refresh`. Trả về user details + array của permissions.
- **`PayrollService`**: Cốt lõi của hệ thống! Chứa hàm `generatePayroll(companyId, month, year)`. Nếu thay đổi cấu trúc bảng lương, sửa ở đây. Phải gọi thông qua `DB::transaction()`.
- **`PayrollWorkflowService`**: Quản lý State Machine cho các Node (`draft` -> `approved` -> `locked`). Cấm thay đổi status ngược chiều. Throw lỗi Validation nếu user vi phạm.
- **`PayrollQueryService`**: Chuyên dùng Read (Tách riêng CQRS cơ bản), ví dụ như `mySalary` hoặc Xuất JSON raw cho Excel.
- **`ReportService`**: Hàm generate số tổng cho trang Dashboard (Nhân viên, Số Xe, Số Chuyến, Tổng Lương).

---

## 3. Quy chuẩn Khởi tạo Database Migration

Khi bạn tạo `php artisan make:model Xyz -m`, Migration cần thoả mãn:

```php
Schema::create('snake_case_plural_names', function (Blueprint $table) {
    $table->id(); // Bắt buộc ID bigint

    // ... your columns
    $table->decimal('price', 15, 2)->default(0); // Chuẩn Data Tiền tệ
    $table->string('status')->default('draft'); // Dùng string trạng thái

    // Audit Trails
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
    
    $table->timestamps();
    $table->softDeletes(); // Cực kỳ quan trọng, xóa mềm tránh mất dữ liệu kê khai sau này
});
```
*Ghi nhớ:* Khai báo `use Illuminate\Database\Eloquent\SoftDeletes;` bên trong file `Model` và gán thuộc tính `$fillable` nhé!

---

## 4. Chạy & Test trong môi trường Docker

- **Migrate DB:** (Sau khi tạo Migration nhớ Migrate nha)
  ```bash
  docker compose exec app php artisan migrate
  # nếu sai be bét muốn clear DB: php artisan migrate:fresh --seed
  ```
- **Tinker (Quản trị DB trực tiếp bằng PHP CLI):**
  ```bash
  docker compose exec app php artisan tinker
  ```
- **Chạy Tests:**
  Mọi Push / PR mới cần chạy qua bộ Pest Tests, nhất là `Arch test`.
  ```bash
  # Test toàn bộ codebase
  docker compose exec app php artisan test
  # Test riêng quy định kiến trúc (Cực kỳ khắt khe)
  docker compose exec app php artisan test --testsuite=Arch
  ```

---

*Lưu ý (đặc biệt đối với AI coder):* Nhớ đọc kỹ Error Log ở bài test (Ví dụ log nói bạn đã vi phạm quy tắc dùng Database ở Controller thì qua Controller xoá ngay đoạn DB/Model và di chuyển vào Service nha!).
