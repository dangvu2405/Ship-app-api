# Database Data Dictionary (Ship App API)

**Purpose:** từ điển dữ liệu để BA/QA/Frontend map field nhất quán với backend.  
**Scope:** bảng nghiệp vụ chính + bảng mở rộng payroll/profile.

> Ghi chú: chi tiết ERD đầy đủ vẫn ở `database_design.md`. File này ưu tiên field thực tiễn hay dùng ở API/report.

---

## 1) `users`

| Column | Type | Nullable | Description |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `username` | string | no | Tên đăng nhập duy nhất |
| `email` | string | no | Email đăng nhập duy nhất |
| `password` | string | no | Mật khẩu hash |
| `avatar_url` | string | yes | Ảnh đại diện tài khoản |
| `employee_id` | bigint FK | yes | Liên kết hồ sơ nhân viên |
| `status` | enum(active,inactive) | no | Trạng thái tài khoản |
| `last_login_at` | timestamp | yes | Lần đăng nhập gần nhất |
| `emergency_contact_name` | string | yes | Người liên hệ khẩn cấp |
| `emergency_contact_phone` | string(20) | yes | SĐT liên hệ khẩn cấp |
| `residential_address` | string | yes | Địa chỉ cư trú |
| `created_at/updated_at/deleted_at` | timestamp | yes | Audit hệ thống |

---

## 2) `employees`

| Column | Type | Nullable | Description |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `code` | string(50) unique | no | Mã nhân viên |
| `name` | string | no | Họ tên |
| `email` | string unique | yes | Email công việc |
| `phone` | string(20) | yes | SĐT |
| `dob` | date | yes | Ngày sinh |
| `gender` | enum(male,female,other) | yes | Giới tính |
| `address` | text | yes | Địa chỉ |
| `avatar_url` | string | yes | Ảnh chân dung hồ sơ |
| `national_id_no` | string(30) | yes | Số CCCD/CMND |
| `national_id_issue_date` | date | yes | Ngày cấp giấy tờ |
| `national_id_issue_place` | string | yes | Nơi cấp giấy tờ |
| `social_insurance_no` | string(30) | yes | Mã BHXH |
| `health_insurance_no` | string(30) | yes | Mã BHYT |
| `insurance_registered_at` | date | yes | Ngày đăng ký bảo hiểm |
| `office_id` | bigint FK | no | Văn phòng làm việc |
| `department_id` | bigint FK | yes | Phòng ban |
| `position_id` | bigint FK | no | Chức danh |
| `type` | enum(office,driver) | no | Loại nhân sự |
| `status` | enum(active,inactive,resigned) | no | Trạng thái làm việc |
| `join_date` | date | yes | Ngày vào làm |
| `resign_date` | date | yes | Ngày nghỉ việc |
| `bank_name` | string | yes | Ngân hàng nhận lương |
| `bank_account_no` | string(50) | yes | STK |
| `bank_account_name` | string | yes | Chủ tài khoản |
| `created_at/updated_at/deleted_at` | timestamp | yes | Audit hệ thống |

---

## 3) `drivers`

| Column | Type | Nullable | Description |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `employee_id` | bigint FK unique | no | Liên kết nhân viên loại driver |
| `license_no` | string(50) | no | Số GPLX |
| `license_image_url` | string | yes | Ảnh GPLX |
| `identity_image_url` | string | yes | Ảnh giấy tờ định danh |
| `driver_insurance_no` | string(30) | yes | Mã bảo hiểm nghề nghiệp |
| `driver_insurance_expired_date` | date | yes | Hạn bảo hiểm nghề nghiệp |
| `health_certificate_no` | string(30) | yes | Mã giấy khám sức khỏe |
| `health_certificate_expired_date` | date | yes | Hạn giấy khám sức khỏe |
| `license_class` | string(20) | yes | Hạng bằng |
| `expired_date` | date | yes | Hạn GPLX |
| `available_status` | enum(available,busy,offline) | no | Trạng thái sẵn sàng |
| `created_at/updated_at/deleted_at` | timestamp | yes | Audit hệ thống |

---

## 4) `companies`, `offices`, `departments`, `positions`

## 4.1 `companies`
| Column | Type | Nullable | Description |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `code` | string unique | no | Mã công ty |
| `name` | string | no | Tên công ty |
| `tax_code` | string | yes | Mã số thuế |
| `address` | text | yes | Địa chỉ |
| `phone` | string | yes | SĐT |
| `email` | string | yes | Email |
| `status` | enum(active,inactive) | no | Trạng thái |

## 4.2 `offices`
| Column | Type | Nullable | Description |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `company_id` | bigint FK | no | Thuộc công ty |
| `code` | string | no | Mã văn phòng |
| `name` | string | no | Tên văn phòng |
| `address` | text | yes | Địa chỉ |
| `manager_id` | bigint FK | yes | Quản lý văn phòng (employee) |

## 4.3 `departments`
| Column | Type | Nullable | Description |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `office_id` | bigint FK | no | Thuộc văn phòng |
| `parent_id` | bigint FK | yes | Phòng ban cha |
| `code` | string | no | Mã phòng ban |
| `name` | string | no | Tên phòng ban |

## 4.4 `positions`
| Column | Type | Nullable | Description |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `code` | string unique | no | Mã chức danh |
| `name` | string | no | Tên chức danh |
| `base_salary` | decimal(15,2) | no | Lương cơ bản |
| `level` | int | yes | Cấp bậc |

---

## 5) `vehicles`, `vehicle_assignments`, `vehicle_expenses`

## 5.1 `vehicles`
| Column | Type | Nullable | Description |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `office_id` | bigint FK | no | Văn phòng quản lý xe |
| `plate_number` | string unique | no | Biển số |
| `type` | enum(truck,van,car,motorcycle) | no | Loại xe |
| `brand`/`model` | string | yes | Hãng/đời xe |
| `year` | year | yes | Năm sản xuất |
| `capacity` | int | yes | Tải trọng/sức chứa |
| `status` | enum(active,maintenance,inactive) | no | Trạng thái xe |

## 5.2 `vehicle_assignments`
| Column | Type | Nullable | Description |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `vehicle_id` | bigint FK | no | Xe được phân |
| `driver_id` | bigint FK(employees) | no | Tài xế được phân |
| `from_date` | date | no | Bắt đầu phân công |
| `to_date` | date | yes | Kết thúc phân công |

## 5.3 `vehicle_expenses`
| Column | Type | Nullable | Description |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `vehicle_id` | bigint FK | no | Xe phát sinh chi phí |
| `driver_id` | bigint FK(employees) | yes | Tài xế liên quan |
| `type` | enum | no | Loại chi phí (fuel/...) |
| `amount` | decimal(15,2) | no | Số tiền |
| `expense_date` | date | no | Ngày phát sinh |
| `note` | text | yes | Ghi chú |

---

## 6) `customers`, `trips`, `invoices`

## 6.1 `customers`
| Column | Type | Nullable | Description |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `code` | string unique | no | Mã khách hàng |
| `name` | string | no | Tên khách hàng |
| `contact_name` | string | yes | Người liên hệ |
| `phone`/`email` | string | yes | Liên hệ |
| `address` | text | yes | Địa chỉ |
| `status` | enum(active,inactive) | no | Trạng thái |

## 6.2 `trips`
| Column | Type | Nullable | Description |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `code` | string unique | no | Mã chuyến |
| `customer_id` | bigint FK | no | Khách hàng |
| `driver_id` | bigint FK(employees) | no | Tài xế |
| `vehicle_id` | bigint FK | no | Xe |
| `start_point`/`end_point` | string | no | Điểm đi/đến |
| `distance_km` | decimal(10,2) | no | Quãng đường |
| `start_time`/`end_time` | datetime | yes | Thời gian |
| `price` | decimal(15,2) | no | Giá chuyến |
| `status` | enum(pending,in_progress,completed,cancelled) | no | Trạng thái |

## 6.3 `invoices`
| Column | Type | Nullable | Description |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `code` | string unique | no | Mã hóa đơn |
| `trip_id` | bigint FK | yes | Chuyến liên quan |
| `customer_id` | bigint FK | no | Khách hàng |
| `subtotal` | decimal(15,2) | no | Tiền trước VAT |
| `vat_rate` | decimal(5,2) | no | % VAT |
| `vat_amount` | decimal(15,2) | no | Tiền VAT |
| `total_amount` | decimal(15,2) | no | Tổng tiền |
| `status` | enum(draft,issued,paid,cancelled) | no | Trạng thái |
| `issued_at`/`paid_at` | timestamp | yes | Mốc phát hành/thanh toán |

---

## 7) `attendances`, `allowances`, `deductions`, pivot

## 7.1 `attendances`
| Column | Type | Nullable | Description |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `employee_id` | bigint FK | no | Nhân viên |
| `date` | date | no | Ngày chấm công |
| `check_in`/`check_out` | time | yes | Giờ vào/ra |
| `work_hours` | decimal | yes | Tổng giờ làm |
| `overtime_hours` | decimal | yes | Giờ tăng ca |
| `status` | enum | no | Trạng thái đi làm |
| `UNIQUE(employee_id,date)` | constraint | - | Chống trùng công |

## 7.2 `allowances` / `deductions`
- Master dữ liệu phụ cấp và khấu trừ mặc định.
- Cột quan trọng: `code`, `name`, `default_amount` (allowance), `taxable`.

## 7.3 `employee_allowances` / `employee_deductions`
- Pivot theo nhân viên.
- Cột: `employee_id`, `allowance_id|deduction_id`, `amount`.

---

## 8) Payroll Core + Phase 1

## 8.1 `payrolls`
| Column | Type | Nullable | Description |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `company_id` | bigint FK | no | Công ty trả lương |
| `payroll_period_id` | bigint FK | yes | Kỳ lương chuẩn (phase 1) |
| `month`/`year` | int | no | Kỳ theo tháng/năm (legacy + đang dùng) |
| `status` | enum(draft,approved,paid,locked) | no | Trạng thái bảng lương |
| `locked_at` | timestamp | yes | Mốc khóa |
| `calculated_at`/`calculated_by` | ts/bigint | yes | Mốc tính lương |
| `approved_at`/`approved_by` | ts/bigint | yes | Mốc duyệt |
| `paid_at` | timestamp | yes | Mốc thanh toán |
| `notes` | text | yes | Ghi chú |
| `created_by/updated_by/deleted_by` | bigint FK | yes | Audit người thao tác |

## 8.2 `payroll_details`
| Column | Type | Nullable | Description |
|---|---|---:|---|
| `id` | bigint | no | PK |
| `payroll_id` | bigint FK | no | Thuộc bảng lương |
| `employee_id` | bigint FK | no | Nhân viên |
| `base_salary` | decimal(15,2) | no | Lương cơ bản |
| `working_days` | int | no | Công làm |
| `overtime` | decimal(15,2) | no | Tiền tăng ca |
| `bonus` | decimal(15,2) | no | Thưởng |
| `allowance` | decimal(15,2) | no | Phụ cấp |
| `deduction` | decimal(15,2) | no | Khấu trừ |
| `fuel_cost` | decimal(15,2) | no | Chi phí xăng/dầu |
| `tax` | decimal(15,2) | no | Thuế |
| `net_salary` | decimal(15,2) | no | Lương thực nhận |
| `meta_json` | json | yes | Metadata tính lương |
| `created_by/updated_by/deleted_by` | bigint FK | yes | Audit người thao tác |

## 8.3 `payroll_adjustments`
- Điều chỉnh cộng/trừ thủ công cho từng `payroll_detail`.
- Cột chính: `payroll_detail_id`, `type(addition/deduction)`, `reason`, `amount`.

## 8.4 Phase 1 tables

### `payroll_periods`
- Quản lý kỳ lương chuẩn theo công ty.
- Cột chính: `company_id`, `code`, `period_type`, `start_date`, `end_date`, `cutoff_date`, `pay_date`, `status`, audit by-user.

### `employee_salary_configs`
- Lịch sử cấu hình lương theo hiệu lực nhân viên.
- Cột chính: `employee_id`, `effective_from`, `effective_to`, `base_salary`, `currency`, `pay_frequency`.

### `attendance_summaries`
- Tổng hợp công theo kỳ lương.
- Cột chính: `payroll_period_id`, `employee_id`, `working_days`, `actual_days`, `overtime_hours`, `status`, `approved_by`, audit by-user.

---

## 9) Logging/Audit/Cache tables

| Table | Purpose |
|---|---|
| `login_logs` | Lịch sử đăng nhập |
| `audit_logs` | Nhật ký thao tác hệ thống |
| `export_logs` | Log xuất dữ liệu/report |
| `report_caches` | Cache dữ liệu báo cáo |
| `refresh_tokens` / `personal_access_tokens` | Token auth |

---

## 10) Mapping nhanh cho frontend/report

- Profile card người dùng: lấy từ `users` + `employees` (+ `drivers` nếu `employee.type=driver`).
- Báo cáo hồ sơ bảo hiểm: ưu tiên `employees.social_insurance_no`, `employees.health_insurance_no`, `drivers.driver_insurance_no`.
- Báo cáo lương tháng: `payrolls` + `payroll_details` (+ `companies`, `employees`).

---

## 11) Ghi chú thay đổi schema trong tương lai

- Mọi thay đổi qua migration, không sửa trực tiếp DB production.
- Khi thêm FK dễ sai thứ tự, tạo migration phụ `add_*_foreign_key.php`.
- Sau khi sửa schema: cập nhật `$fillable`, `$casts`, FormRequest, docs.

