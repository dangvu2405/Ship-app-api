# Database Detailed Spec (Ship App API)

**Project:** `ship-app-api`  
**Version:** 1.0  
**Last updated:** 2026-04-09

---

## 1) Mục tiêu tài liệu

Tài liệu này mô tả chi tiết database theo hướng thực thi:
- Bảng theo module.
- Cột quan trọng và ràng buộc chính.
- Khóa ngoại/index cần chú ý khi mở rộng.
- Hướng dẫn chỉnh sửa schema an toàn.

> ERD đầy đủ tất cả cột/quan hệ xem `database_design.md`.
> Data dictionary theo cột xem `docs/DATABASE_DATA_DICTIONARY.md`.

---

## 2) Quy ước database áp dụng

- Tên bảng/cột: `snake_case`.
- PK chuẩn: `id` (`bigint`).
- Soft delete cho bảng nghiệp vụ: `deleted_at`.
- Timestamps mặc định: `created_at`, `updated_at`.
- Tiền tệ: `decimal(15,2)`.
- Audit (nếu cần): `created_by`, `updated_by`, `deleted_by` (FK `users.id`, `nullOnDelete`).

---

## 3) Module Organization & HR

## 3.1 `companies`
- Mục đích: đơn vị pháp nhân/công ty.
- Cột chính: `code`, `name`, `tax_code`, `status`.
- Quan hệ:
  - `companies (1) -> (n) offices`
  - `companies (1) -> (n) payrolls`

## 3.2 `offices`
- Mục đích: chi nhánh/văn phòng thuộc công ty.
- Cột chính: `company_id`, `code`, `name`, `manager_id`.
- Quan hệ:
  - FK `company_id -> companies.id`
  - FK `manager_id -> employees.id` (migration tách riêng để tránh lỗi thứ tự FK).

## 3.3 `departments`
- Mục đích: phòng ban, hỗ trợ cây cha-con.
- Cột chính: `office_id`, `parent_id`, `code`, `name`.
- Quan hệ:
  - FK `office_id -> offices.id` (được gắn ở migration phụ).
  - FK `parent_id -> departments.id`.

## 3.4 `positions`
- Mục đích: chức danh và mức lương cơ bản.
- Cột chính: `code`, `name`, `base_salary`, `level`.

## 3.5 `employees`
- Mục đích: hồ sơ nhân sự (văn phòng + tài xế).
- Cột cơ bản:
  - `code`, `name`, `email`, `phone`, `dob`, `gender`, `address`
  - `office_id`, `department_id`, `position_id`
  - `type` (`office`/`driver`), `status`, `join_date`, `resign_date`
- Cột hồ sơ mở rộng mới:
  - `avatar_url`
  - `national_id_no`, `national_id_issue_date`, `national_id_issue_place`
  - `social_insurance_no`, `health_insurance_no`, `insurance_registered_at`
  - `bank_name`, `bank_account_no`, `bank_account_name`
- Index quan trọng:
  - `code` (unique), `social_insurance_no`, `health_insurance_no`, `national_id_no`

## 3.6 `drivers`
- Mục đích: thông tin nghiệp vụ tài xế (tách khỏi employee).
- Cột cơ bản: `employee_id`, `license_no`, `license_class`, `expired_date`, `available_status`.
- Cột hồ sơ mở rộng mới:
  - `license_image_url`, `identity_image_url`
  - `driver_insurance_no`, `driver_insurance_expired_date`
  - `health_certificate_no`, `health_certificate_expired_date`
- Quan hệ:
  - FK `employee_id -> employees.id` (gắn ở migration phụ để tránh lỗi thứ tự).

## 3.7 `users`
- Mục đích: tài khoản đăng nhập API.
- Cột cơ bản: `username`, `email`, `password`, `employee_id`, `status`, `last_login_at`.
- Cột hồ sơ mở rộng mới:
  - `avatar_url`
  - `emergency_contact_name`, `emergency_contact_phone`
  - `residential_address`
- Quan hệ:
  - FK `employee_id -> employees.id` (migration phụ để tương thích MySQL).

---

## 4) Module RBAC

## 4.1 `roles`, `permissions`
- `roles`: `name`, `description`
- `permissions`: `code`, `name`, `description`

## 4.2 Bảng pivot
- `user_roles`: map user-role
- `role_permissions`: map role-permission
  - FK `role_id -> roles.id` gắn ở migration phụ để tránh sai thứ tự tên file.

---

## 5) Module Fleet & Operations

## 5.1 Fleet
- `vehicles`: xe theo `office_id`, trạng thái vận hành.
- `vehicle_assignments`: map `vehicle_id` và `driver_id` theo `from_date`/`to_date`.
  - FK `vehicle_id` được tách migration phụ để tránh lỗi thứ tự với `vehicles`.
- `vehicle_expenses`: chi phí xe/tài xế theo ngày (fuel, maintenance...).

## 5.2 Operations
- `customers`
- `trips`: liên kết `customer_id`, `driver_id`, `vehicle_id`, trạng thái chuyến.
- `invoices`: liên kết hóa đơn theo `customer_id`, tùy chọn `trip_id`.
  - FK `trip_id` gắn migration phụ để tránh lỗi thứ tự với `trips`.

---

## 6) Module Attendance & Payroll

## 6.1 Attendance
- `attendances`: theo `employee_id` + `date`.
- Unique chính: `(employee_id, date)`.

## 6.2 Payroll core
- `payrolls`:
  - Core cũ: `company_id`, `month`, `year`, `status`, `locked_at`
  - Mở rộng phase 1:
    - `payroll_period_id`
    - `calculated_at`, `calculated_by`
    - `approved_at`, `approved_by`
    - `paid_at`, `notes`
    - `created_by`, `updated_by`, `deleted_by`
  - Unique quan trọng: `(company_id, month, year)`
- `payroll_details`:
  - Cột tổng hợp lương theo nhân viên.
  - Có thêm audit `created_by`, `updated_by`, `deleted_by`.
- `payroll_adjustments`:
  - FK `payroll_detail_id` gắn qua migration phụ để đảm bảo thứ tự.

## 6.3 Payroll phase 1 (mở rộng)
- `payroll_periods`
- `employee_salary_configs`
- `attendance_summaries`

Mục tiêu: chuẩn hóa kỳ lương, cấu hình lương theo hiệu lực, summary công làm input tính lương.

---

## 7) Logging / Infra tables

- Nghiệp vụ-log: `login_logs`, `audit_logs`, `export_logs`, `report_caches`
- Auth/token: `personal_access_tokens`, `refresh_tokens`, `password_reset_tokens`, `sessions`
- Framework: `jobs`, `cache`, ...

---

## 8) Tài liệu field chuẩn cho profile/bảo hiểm

Xem file:
- `docs/USER_PROFILE_REPORT_FORM_STANDARD.md`

File này đã liệt kê:
- Các trường còn thiếu trước đây.
- Các trường đã bổ sung.
- JSON form chuẩn xuất báo cáo hồ sơ.

---

## 9) Hướng dẫn chỉnh sửa schema (khuyến nghị bắt buộc)

1. **Không sửa trực tiếp DB bằng tay** (DBeaver/MySQL GUI) cho schema chính.
2. Tạo migration rõ mục đích:
   - `add_xxx_to_table`
   - `create_xxx_table`
3. Nếu FK có nguy cơ lỗi thứ tự theo tên file:
   - Tạo bảng trước, gắn FK ở migration tiếp theo (`add_*_foreign_key`).
4. Luôn cập nhật đồng bộ:
   - Model `$fillable`, `$casts`
   - FormRequest validation
   - Service/Controller dùng field mới
   - Docs liên quan
5. Verify:
   - `php artisan migrate`
   - `php artisan migrate:rollback` (kiểm tra `down()`)
   - `php artisan test`

---

## 10) Checklist trước khi merge thay đổi database

- [ ] Migration chạy được ở Docker app.
- [ ] Rollback chạy được.
- [ ] Không tạo FK vòng hoặc phụ thuộc sai thứ tự.
- [ ] Có index cho cột tìm kiếm/filter quan trọng.
- [ ] Đã cập nhật docs/spec.

---

## 11) Quick commands

```bash
docker compose up -d --build
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:status
docker compose exec app php artisan test
```

