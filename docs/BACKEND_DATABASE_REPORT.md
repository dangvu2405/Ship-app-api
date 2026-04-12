# Báo cáo nội bộ — Database phía Backend (ship-app-api)

**Đối tượng:** team backend, DevOps, người review release có thay đổi schema.  
**Mục đích:** một trang tổng hợp **hiện trạng DB**, **luồng vận hành**, **rủi ro đã biết**, **tài liệu chi tiết** và **mục 7 — mô tả từng trường** (bám migration; bổ sung cho [DATABASE_DATA_DICTIONARY.md](./DATABASE_DATA_DICTIONARY.md)).

---

## 1) Tóm tắt điều hành

| Hạng mục | Nội dung |
|----------|-----------|
| **Quản lý schema** | Chỉ qua **Laravel migrations** trong `database/migrations/` (khoảng **67** file migration tính tại thời điểm lập báo cáo). |
| **ORM** | Eloquent; rule dự án: bảng nghiệp vụ có **`softDeletes()`**, cột tiền **`decimal(15,2)`**, audit **`created_by` / `updated_by` / `deleted_by`** khi cần. |
| **Engine hỗ trợ** | **MySQL** (Docker / production) và **SQLite** (test/local) — nên chạy `migrate:fresh` trên cả hai trước khi merge thay đổi lớn. |
| **Seed** | `DatabaseSeeder`, `AllTablesSeeder`, `RolesAndPermissionsSeeder`, `BulkDataSeeder` — dữ liệu mẫu không đi kèm migration. |

---

## 2) Phân nhóm bảng theo nghiệp vụ

### 2.1 Tổ chức & nhân sự
`companies`, `offices`, `departments`, `positions`, `employees`, `drivers`, `users`  
Hồ sơ mở rộng (avatar, BHXH/BHYT, ngân hàng, CMND…): migration `2026_04_09_160000_add_profile_and_insurance_fields_to_users_employees_drivers`.

### 2.2 RBAC & người dùng
`roles`, `permissions`, `user_roles`, `role_permissions`, `personal_access_tokens`, `refresh_tokens`  
Lưu ý: một số FK ban đầu được **tách migration** sau để tránh lỗi thứ tự tạo bảng (xem §4).

### 2.3 Đội xe & vận hành
`vehicles`, `vehicle_assignments`, `vehicle_expenses`, `customers`, `trips`, `trip_bonus_rules`, `invoices`  
Index hiệu năng (composite): migration `2026_04_15_104000_add_critical_indexes_for_scale` (`trips`, `vehicle_expenses`, …).

### 2.4 Chấm công & lương (core)
`attendances`, `allowances`, `deductions`, `employee_allowances`, `employee_deductions`, `payrolls`, `payroll_details`, `payroll_adjustments`  
Mở rộng payroll (phase 1): `payroll_periods`, `employee_salary_configs`, `attendance_summaries` + cột audit/period trên `payrolls` / `payroll_details`.

### 2.5 MUST HAVE (schema mới — API wiring có thể tách phase)
- **Nghỉ phép:** `leave_types`, `leave_requests`, `leave_balances`  
- **Tuân thủ lương / thuế / BH:** `tax_brackets`, `insurance_rates`, `payroll_earnings`, `payroll_deductions`, `payslips`  
- **Kế toán tối thiểu:** `chart_of_accounts`, `journal_entries`, `journal_entry_lines`  
- **Lịch sử trạng thái:** `payroll_status_histories`, `trip_status_histories`, `invoice_status_histories`  

Chi tiết cột/FK: [MUST_HAVE_MIGRATION_SPEC.md](./MUST_HAVE_MIGRATION_SPEC.md).

### 2.6 Ứng dụng & tích hợp
`notifications`, `chat_messages`, `lark_event_logs` (Lark), `login_logs`, `audit_logs`, `export_logs`, `report_caches`, `cache` / `jobs` (Laravel).

---

## 3) Kết nối & lệnh thường dùng (backend)

**Biến môi trường:** `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (xem `.env.example`).

```bash
# Áp schema
php artisan migrate

# Reset sạch + seed (chỉ môi trường dev)
php artisan migrate:fresh --seed

# Kiểm tra test suite (SQLite trong phpunit)
php artisan test
```

**Docker (MySQL):** khởi chạy service `db`, map port host (thường `127.0.0.1:3306`); app container chạy `php artisan migrate` / `migrate:fresh --seed` như trên.

---

## 4) Rủi ro đã gặp và cách xử lý trong repo

1. **Thứ tự FK:** một số bảng cũ từng tạo `foreignId()->constrained()` trước khi bảng tham chiếu tồn tại → đã chuyển sang cột `unsignedBigInteger` + migration **bổ sung FK** sau (pattern “defer FK”). Khi thêm bảng mới: luôn kiểm tra **thứ tự tên file migration** và dependency.  
2. **`migrate:fresh` trên MySQL:** bắt buộc chạy trước release migration lớn để phát hiện lỗi FK/index sớm.  
3. **Soft delete vs báo cáo:** query báo cáo tổng hợp cần `withTrashed()` hoặc filter rõ nếu nghiệp vụ cần số liệu lịch sử.

---

## 5) Tài liệu chi tiết (đọc thêm)

| Tài liệu | Dùng khi |
|----------|-----------|
| [DATABASE_DATA_DICTIONARY.md](./DATABASE_DATA_DICTIONARY.md) | Tra cứu **từng cột** (kiểu, ý nghĩa). |
| [DATABASE_DETAILED_SPEC.md](./DATABASE_DETAILED_SPEC.md) | **ERD/logic** theo module, FK, index. |
| [DATABASE_REPORT_AND_EDIT_GUIDE.md](./DATABASE_REPORT_AND_EDIT_GUIDE.md) | **Quy trình sửa DB an toàn**, checklist trước merge. |
| [MUST_HAVE_MIGRATION_SPEC.md](./MUST_HAVE_MIGRATION_SPEC.md) | Spec migration MUST HAVE + rollout. |
| [PAYROLL_MODULE_EXTENSION_DESIGN.md](./PAYROLL_MODULE_EXTENSION_DESIGN.md) | Thiết kế mở rộng payroll theo phase. |

---

## 6) Checklist trước khi merge thay đổi DB

1. Migration có `down()` khớp (drop FK/index trước khi drop cột/bảng).  
2. Chạy **`php artisan migrate:fresh`** trên MySQL (Docker) và SQLite.  
3. Chạy **`php artisan test`**.  
4. Cập nhật **data dictionary / spec** nếu thêm bảng hoặc cột nghiệp vụ.  
5. Thông báo **frontend** nếu đổi tên field hoặc thêm bảng có contract API (xem `FRONTEND_MUST_HAVE_SCHEMA_HANDOFF.md` khi liên quan).

---

## 7) Phụ lục — Mô tả từng trường dữ liệu (chuẩn migration)

**Quy ước bảng:** `no` = NOT NULL, `yes` = nullable. `FK` = khóa ngoại. Enum liệt kê đúng giá trị trong schema hiện tại.

### 7.1 Bảng đã có bảng cột chi tiết trong Data Dictionary

Các bảng sau giữ mô tả đầy đủ tại [DATABASE_DATA_DICTIONARY.md](./DATABASE_DATA_DICTIONARY.md): `employees`, `companies`, `offices`, `departments`, `positions`, `vehicles`, `vehicle_assignments`, `trips`, `invoices` (một số cột — đối chiếu thêm **§7.4** nếu lệch migration), `payrolls` / `payroll_details` (bổ sung cột phase 1 — **§7.5**), `payroll_periods`, `employee_salary_configs`, `attendance_summaries`, và phần tóm tắt `attendances` / pivot lương.

### 7.2 `users` (gộp `0001_…_users`, `lark_user_id`, profile)

| Column | Type | Nullable | Mô tả |
|--------|------|----------|--------|
| `id` | bigint | no | PK |
| `username` | string | no | Đăng nhập, unique |
| `email` | string | no | Email, unique |
| `email_verified_at` | timestamp | yes | Xác minh email |
| `lark_user_id` | string | yes | User id Lark/Feishu, unique |
| `password` | string | no | Hash mật khẩu |
| `employee_id` | bigint FK → `employees` | yes | Liên kết hồ sơ NV |
| `status` | enum(`active`,`inactive`) | no | Trạng thái tài khoản |
| `last_login_at` | timestamp | yes | Đăng nhập cuối |
| `avatar_url` | string | yes | Ảnh đại diện |
| `emergency_contact_name` | string | yes | Liên hệ khẩn cấp |
| `emergency_contact_phone` | string(20) | yes | SĐT khẩn cấp |
| `residential_address` | string | yes | Địa chỉ cư trú |
| `remember_token` | string | yes | Laravel remember |
| `created_at` / `updated_at` | timestamp | yes | Audit |
| `deleted_at` | timestamp | yes | Soft delete |

### 7.3 `drivers` (gộp `create_drivers` + profile)

| Column | Type | Nullable | Mô tả |
|--------|------|----------|--------|
| `id` | bigint | no | PK |
| `employee_id` | bigint FK → `employees` | no | Một tài xế ↔ một NV, unique |
| `license_no` | string(50) | no | Số GPLX |
| `license_image_url` | string | yes | Ảnh GPLX |
| `identity_image_url` | string | yes | Ảnh định danh |
| `driver_insurance_no` | string(30) | yes | Số BH nghề |
| `driver_insurance_expired_date` | date | yes | Hạn BH nghề |
| `health_certificate_no` | string(30) | yes | Giấy khám SK |
| `health_certificate_expired_date` | date | yes | Hạn giấy khám |
| `license_class` | string(20) | yes | Hạng bằng |
| `expired_date` | date | yes | Hạn GPLX |
| `available_status` | enum(`available`,`busy`,`offline`) | no | Trạng thái sẵn sàng (lưu ý: khác label một số tài liệu FE nếu map sai) |
| `created_at` / `updated_at` | timestamp | yes | |
| `deleted_at` | timestamp | yes | Soft delete |

### 7.4 `customers` (đối chiếu migration — **không** có cột `code`)

| Column | Type | Nullable | Mô tả |
|--------|------|----------|--------|
| `id` | bigint | no | PK |
| `type` | enum(`individual`,`company`) | no | Loại KH |
| `name` | string | no | Tên |
| `tax_code` | string(50) | yes | MST |
| `phone` | string(20) | yes | SĐT |
| `email` | string | yes | Email |
| `address` | text | yes | Địa chỉ |
| `created_at` / `updated_at` | timestamp | yes | |
| `deleted_at` | timestamp | yes | Soft delete |

*(Dictionary cũ có thể nhắc `code` — nếu API/FE đang dùng `code`, cần migration bổ sung hoặc chỉnh tài liệu.)*

### 7.5 `payrolls` (gộp bảng gốc + `100003`)

| Column | Type | Nullable | Mô tả |
|--------|------|----------|--------|
| `id` | bigint | no | PK |
| `company_id` | bigint FK | no | Công ty |
| `payroll_period_id` | bigint FK → `payroll_periods` | yes | Kỳ lương chuẩn |
| `month` / `year` | int | no | Kỳ tháng/năm |
| `status` | enum(`draft`,`approved`,`paid`,`locked`) | no | Trạng thái bảng lương |
| `locked_at` | timestamp | yes | Khóa sổ |
| `calculated_at` | timestamp | yes | Thời điểm tính |
| `calculated_by` | bigint FK → `users` | yes | Người tính |
| `approved_at` | timestamp | yes | Thời điểm duyệt |
| `approved_by` | bigint FK → `users` | yes | Người duyệt |
| `paid_at` | timestamp | yes | Thời điểm chi trả |
| `notes` | text | yes | Ghi chú |
| `created_by` / `updated_by` / `deleted_by` | bigint FK → `users` | yes | Audit xóa |
| `created_at` / `updated_at` | timestamp | yes | |
| `deleted_at` | timestamp | yes | Soft delete |

### 7.6 `payroll_details` (+ migration `100004` audit)

Cột gốc: `payroll_id`, `employee_id`, `base_salary`, `working_days`, `overtime`, `bonus`, `allowance`, `deduction`, `fuel_cost`, `tax`, `net_salary`, `meta_json` (json, yes), timestamps, soft deletes.  
Migration `100004` thêm: `created_by`, `updated_by`, `deleted_by` (FK `users`, nullable).

### 7.7 RBAC & pivot

**`roles`:** `id`, `name` (unique), `description`, timestamps, `deleted_at`.

**`permissions`:** `id`, `code` (unique, 100), `name`, `description`, timestamps, `deleted_at`.

**`user_roles`:** `id`, `user_id` FK cascade, `role_id` FK cascade, timestamps, unique(`user_id`,`role_id`).

**`role_permissions`:** `id`, `role_id` (unsigned, FK bổ sung sau), `permission_id` FK cascade, timestamps, unique(`role_id`,`permission_id`).

### 7.8 Master phụ cấp / khấu trừ / pivot

**`allowances`:** `id`, `code` (unique 50), `name`, `default_amount` decimal(15,2) default 0, `taxable` bool default false, timestamps, soft deletes.

**`deductions`:** `id`, `code` (unique 50), `name`, timestamps, soft deletes.

**`employee_allowances`:** `id`, `employee_id` FK, `allowance_id` FK, `amount` decimal(15,2), timestamps, soft deletes, unique(`employee_id`,`allowance_id`).

**`employee_deductions`:** `id`, `employee_id` FK, `deduction_id` FK, `amount` decimal(15,2), timestamps, soft deletes, unique(`employee_id`,`deduction_id`).

**`payroll_adjustments`:** `id`, `payroll_detail_id` (unsigned, FK deferred), `type` enum(`addition`,`deduction`), `reason` text, `amount` decimal(15,2), timestamps, soft deletes.

**`trip_bonus_rules`:** `id`, `min_km` decimal(10,2) default 0, `max_km` decimal(10,2) yes, `bonus_per_km` decimal(10,2) default 0, timestamps, soft deletes.

### 7.9 `attendances`

| Column | Type | Nullable | Mô tả |
|--------|------|----------|--------|
| `id` | bigint | no | PK |
| `employee_id` | bigint FK | no | NV |
| `date` | date | no | Ngày công |
| `check_in` / `check_out` | time | yes | Giờ vào/ra |
| `work_hours` | decimal(5,2) | no | Giờ làm (default 0) |
| `overtime_hours` | decimal(5,2) | no | Tăng ca (default 0) |
| `status` | enum(`present`,`absent`,`late`,`half_day`,`leave`) | no | Trạng thái |
| `created_at` / `updated_at` | timestamp | yes | |
| `deleted_at` | timestamp | yes | Soft delete |

Unique(`employee_id`,`date`).

### 7.10 `vehicle_expenses`

| Column | Type | Nullable | Mô tả |
|--------|------|----------|--------|
| `id` | bigint | no | PK |
| `vehicle_id` | bigint FK | no | Xe |
| `driver_id` | bigint FK → `employees` | yes | Tài xế |
| `type` | enum(`fuel`,`maintenance`,`repair`,`toll`,`parking`,`other`) | no | Loại chi phí |
| `amount` | decimal(15,2) | no | Số tiền |
| `note` | text | yes | Ghi chú |
| `expense_date` | date | no | Ngày phát sinh |
| `created_at` / `updated_at` | timestamp | yes | |
| `deleted_at` | timestamp | yes | Soft delete |

### 7.11 Ứng dụng / tích hợp

**`notifications` (Laravel):** `id` UUID PK, `type` string, `notifiable_type` / `notifiable_id` (morph), `data` text (JSON), `read_at`, timestamps.

**`chat_messages`:** `id`, `user_id` FK cascade, `session_id` (64, index), `message` text, `response` longText yes, `context` json yes, `model` string(100) yes, `status` enum(`success`,`error`), `error_message` text yes, timestamps; index(`user_id`,`session_id`,`created_at`).

**`lark_event_logs`:** `id`, `event_id`, `request_id`, `event_type` (nullable, indexed), `signature_valid` bool, `replay_blocked` bool, `status` string default `received` (index), `headers` json yes, `payload` json yes, `error_message` text yes, `processed_at` timestamp yes (index), timestamps.

### 7.12 MUST HAVE — nghỉ phép

**`leave_types`:** `id`, `code` string(30) unique, `name` string(100), `is_paid` bool default true, `annual_quota_days` decimal(6,2) default 0, `allow_carry_forward` bool, `requires_attachment` bool, `status` string(20) default `active`, timestamps, `deleted_at`.

**`leave_requests`:** `id`, `employee_id` FK cascade, `leave_type_id` FK restrict, `from_date`/`to_date` date, `total_days` decimal(6,2), `reason` text yes, `status` string(20) default `draft`, `approved_by` FK users yes, `approved_at` yes, `attachment_urls` json yes, `created_by`/`updated_by`/`deleted_by` FK users yes, timestamps, `deleted_at`; index(`employee_id`,`status`), (`from_date`,`to_date`).

**`leave_balances`:** `id`, `employee_id` FK cascade, `leave_type_id` FK restrict, `year` smallint, `opening_days`/`earned_days`/`used_days`/`adjusted_days`/`closing_days` decimal(6,2) default 0, timestamps, unique(`employee_id`,`leave_type_id`,`year`).

### 7.13 MUST HAVE — thuế / BH / dòng lương / phiếu lương

**`tax_brackets`:** `id`, `effective_from` date, `effective_to` date yes, `level` int, `income_from` decimal(15,2) default 0, `income_to` decimal(15,2) yes, `tax_rate` decimal(5,2), `quick_deduction` decimal(15,2) default 0, `status` string(20) default `active`, timestamps; index(`effective_from`,`effective_to`); unique(`effective_from`,`level`).

**`insurance_rates`:** `id`, `effective_from`/`effective_to` date, `social_*` / `health_*` / `unemployment_*` employee+company rates decimal(5,2) default 0, `salary_cap_amount` decimal(15,2) yes, `status` string(20) default `active`, timestamps; index(`effective_from`,`effective_to`).

**`payroll_earnings`:** `id`, `payroll_detail_id` FK cascade, `type_code` string(50), `name` string(150), `amount` decimal(15,2) default 0, `taxable`/`insurable` bool, `source` string(30) default `system`, `meta_json` json yes, `created_by`/`updated_by`/`deleted_by` FK users yes, timestamps, `deleted_at`; index(`payroll_detail_id`,`type_code`).

**`payroll_deductions`:** giống earnings nhưng có `pre_tax` bool default false, không có `insurable`.

**`payslips`:** `id`, `payroll_id` FK cascade, `payroll_detail_id` FK cascade unique, `employee_id` FK cascade, `issue_number` string(50) unique, `issued_at` yes, `status` string(20) default `draft`, `snapshot_json` json **not null**, `issued_by` FK users yes, timestamps; index(`employee_id`,`issued_at`).

### 7.14 MUST HAVE — GL

**`chart_of_accounts`:** `id`, `code` string(30) unique, `name` string(150), `type` string(30), `parent_id` FK self nullOnDelete yes, `is_postable` bool default true, `status` string(20) default `active`, timestamps, `deleted_at`; index(`type`,`status`).

**`journal_entries`:** `id`, `entry_no` string(50) unique, `entry_date` date, `source_type` string(50) yes, `source_id` unsignedBigInteger yes, `status` string(20) default `draft`, `description` text yes, `posted_by` FK users yes, `posted_at` yes, timestamps; index(`source_type`,`source_id`), (`entry_date`,`status`).

**`journal_entry_lines`:** `id`, `journal_entry_id` FK cascade, `account_id` FK `chart_of_accounts` restrict, `debit`/`credit` decimal(15,2) default 0, `line_description` text yes, `line_no` unsigned int default 1, timestamps; index(`journal_entry_id`,`line_no`).

### 7.15 MUST HAVE — lịch sử trạng thái

Mỗi bảng: `id`, FK cha (`payroll_id` / `trip_id` / `invoice_id`) cascade, `from_status` string nullable, `to_status` string not null, `changed_by` FK users yes, `changed_at` timestamp not null, `note` text yes, timestamps; index(FK, `changed_at`). Độ dài `from_status`/`to_status`: payroll 20 ký tự; trip/invoice 30 ký tự.

---

*Báo cáo nội bộ — cập nhật khi có thêm khối migration lớn hoặc thay đổi chính sách schema dự án.*
