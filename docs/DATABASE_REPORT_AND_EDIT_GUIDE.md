# Báo cáo Database và Hướng dẫn chỉnh sửa an toàn

**Dự án:** `ship-app-api`  
**Mục tiêu tài liệu:** giúp bạn biết nên chỉnh DB ở đâu, chỉnh thế nào, và tránh lỗi migration/FK.

---

## 1) Tài liệu DB hiện có (nên đọc theo thứ tự)

1. **`database_design.md`**  
   - ERD đầy đủ (rất chi tiết) của toàn bộ bảng/quan hệ.
2. **`docs/PROJECT_SPEC_CHI_TIET.md`**  
   - Bản đồ module + luồng nghiệp vụ tổng.
3. **`docs/PAYROLL_MODULE_EXTENSION_DESIGN.md`**  
   - Thiết kế mở rộng payroll theo phase.
4. **`docs/USER_PROFILE_REPORT_FORM_STANDARD.md`**  
   - Bộ trường hồ sơ user/employee/driver + form báo cáo chuẩn.

---

## 2) Hiện trạng schema quan trọng

### 2.1 Khối tổ chức & nhân sự
- `companies`, `offices`, `departments`, `positions`
- `employees`, `drivers`, `users`
- RBAC: `roles`, `permissions`, `user_roles`, `role_permissions`

### 2.2 Khối vận hành
- `vehicles`, `vehicle_assignments`, `vehicle_expenses`
- `customers`, `trips`, `invoices`

### 2.3 Khối payroll
- Core cũ: `payrolls`, `payroll_details`, `payroll_adjustments`, `attendances`, `allowances`, `deductions`
- Mở rộng phase 1: `payroll_periods`, `employee_salary_configs`, `attendance_summaries`

### 2.4 Bổ sung gần nhất cho hồ sơ đầy đủ
- Migration: `2026_04_09_160000_add_profile_and_insurance_fields_to_users_employees_drivers.php`
- Thêm ảnh + bảo hiểm cho `users`/`employees`/`drivers`

---

## 3) Những điểm cần chú ý trước khi sửa DB

### 3.1 Quy tắc chuẩn đang áp dụng
- Dùng migration, **không sửa schema bằng tay** trong DB GUI.
- Tên bảng/cột: `snake_case`, bảng số nhiều.
- Tiền tệ dùng `decimal(15,2)`.
- Bảng nghiệp vụ ưu tiên `softDeletes()`.
- Nếu tracking audit thì thêm `created_by`, `updated_by`, `deleted_by` (FK users, `nullOnDelete`).

### 3.2 Rủi ro từng gặp trong project
- Một số migration cũ cùng timestamp gây lỗi thứ tự FK trên MySQL.
- Cách xử lý đã áp dụng: tách FK sang migration phụ (`add_*_foreign_key.php`).

**=> Khi thêm FK mới:** ưu tiên tạo bảng trước, gắn FK ở migration sau nếu có rủi ro thứ tự.

---

## 4) Cách chỉnh sửa DB đúng quy trình

## Bước 1: Xác định phạm vi thay đổi
- Chỉ thêm cột?
- Thêm bảng mới?
- Đổi kiểu dữ liệu?
- Thêm/đổi FK?

## Bước 2: Tạo migration

Ví dụ:

```bash
php artisan make:migration add_xxx_to_employees_table
php artisan make:migration create_xxx_table
```

## Bước 3: Cập nhật code liên quan

Mỗi thay đổi DB phải đi kèm:
- `app/Models/*` (`$fillable`, `$casts`, relationship)
- `app/Http/Requests/*` (validate input mới)
- Service/Controller dùng field mới (nếu có)
- Docs liên quan (`PROJECT_SPEC_CHI_TIET`, `API_SPEC`, docs module)

## Bước 4: chạy migrate + test

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan test
docker compose exec app php artisan test --testsuite=Arch
```

## Bước 5: rollback thử trong local

```bash
docker compose exec app php artisan migrate:rollback
docker compose exec app php artisan migrate
```

Nếu rollback lỗi, sửa lại `down()` ngay trước khi push.

---

## 5) Nên chỉnh gì trong giai đoạn tiếp theo (khuyến nghị)

### 5.1 User/Employee/Driver
- Đã đủ bộ trường hồ sơ cơ bản + ảnh + bảo hiểm.
- Có thể bổ sung sau nếu cần:
  - ảnh CCCD mặt trước/sau tách riêng
  - `tax_identification_no`
  - thông tin hợp đồng lao động (`contract_type`, `contract_end_date`)

### 5.2 Payroll
- Ưu tiên phase tiếp theo:
  - `payroll_earnings`
  - `payroll_deductions`
  - `insurance_rates`
  - `tax_brackets`
- Đồng bộ API và test cho `payroll_periods`, `employee_salary_configs`, `attendance_summaries`.

### 5.3 Chất lượng dữ liệu
- Thêm unique/index theo truy vấn thực tế (ví dụ số bảo hiểm, số giấy tờ).
- Chuẩn hóa enum/status để tránh nhập sai khác biệt chính tả.

---

## 6) Checklist trước khi merge thay đổi DB

- [ ] Migration `up()` chạy được trên MySQL local.
- [ ] `down()` rollback được.
- [ ] Không tạo FK gây phụ thuộc vòng hoặc sai thứ tự migration.
- [ ] Model + FormRequest đã cập nhật đồng bộ.
- [ ] API docs/spec đã cập nhật.
- [ ] Test quan trọng chạy pass.

---

## 7) Lệnh nhanh bạn sẽ dùng nhiều

```bash
# lên container
docker compose up -d --build

# migrate
docker compose exec app php artisan migrate

# làm mới DB + seed
docker compose exec app php artisan migrate:fresh --seed

# xem trạng thái migration
docker compose exec app php artisan migrate:status
```

---

## Kết luận

Nếu mục tiêu của bạn là “chỉnh DB đúng chuẩn và ít lỗi production”, hãy bám theo quy trình ở mục 4 + checklist mục 6.  
Với dự án này, rủi ro lớn nhất là **thứ tự FK migration** và **quên cập nhật FormRequest/Model** khi thêm cột.
