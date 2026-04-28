# Nghiệp vụ chi tiết — Ship App API (Fleet Management)

> Hệ thống quản lý vận tải đa-tenant (multi-tenant). Mỗi công ty (`company_id`) là một tenant độc lập, dữ liệu hoàn toàn tách biệt.

---

## Mục lục

1. [Cơ cấu tổ chức](#1-cơ-cấu-tổ-chức)
2. [Quản lý tài xế](#2-quản-lý-tài-xế)
3. [Quản lý phương tiện](#3-quản-lý-phương-tiện)
4. [Chuyến đi & Khách hàng](#4-chuyến-đi--khách-hàng)
5. [Hóa đơn & Doanh thu](#5-hóa-đơn--doanh-thu)
6. [Lịch làm việc & Tăng ca](#6-lịch-làm-việc--tăng-ca)
7. [Nghỉ phép](#7-nghỉ-phép)
8. [Chấm công](#8-chấm-công)
9. [Tính lương](#9-tính-lương)
10. [Vi phạm & Kỷ luật](#10-vi-phạm--kỷ-luật)
11. [Kế toán nội bộ](#11-kế-toán-nội-bộ)
12. [Phân quyền](#12-phân-quyền)
13. [Hệ thống & Logging](#13-hệ-thống--logging)

---

## 1. Cơ cấu tổ chức

### `companies` — Công ty (Tenant)

Đơn vị gốc của toàn hệ thống. Mỗi công ty là một tenant riêng biệt.

| Cột | Nghiệp vụ |
|-----|-----------|
| `code` | Mã công ty duy nhất toàn hệ thống, dùng để tra cứu nhanh |
| `name` | Tên công ty hiển thị trên hóa đơn, báo cáo |
| `tax_code` | Mã số thuế — dùng khi xuất hóa đơn VAT |
| `address`, `phone`, `email` | Thông tin liên hệ pháp lý |
| `status` | `active` = đang hoạt động; `inactive` = tạm dừng (không thể đăng nhập) |

**Quy tắc nghiệp vụ:**
- Tất cả dữ liệu trong hệ thống đều gắn với `company_id`.
- Soft-delete: không xóa vĩnh viễn, chuyển `deleted_at` để giữ lịch sử hợp đồng.
- Khi `status = inactive`: người dùng thuộc công ty này không thể đăng nhập.

---

### `offices` — Chi nhánh / Văn phòng

Đơn vị địa lý của công ty (có thể nhiều tỉnh thành).

| Cột | Nghiệp vụ |
|-----|-----------|
| `company_id` | Thuộc công ty nào |
| `code` | Mã chi nhánh, unique trong phạm vi công ty |
| `name` | Tên chi nhánh |
| `address` | Địa chỉ thực tế |
| `manager_id` | FK → `drivers.id` — tài xế kiêm quản lý chi nhánh |

**Quy tắc nghiệp vụ:**
- Tài xế, phương tiện, lịch làm việc đều gắn với chi nhánh.
- Ứng dụng lịch làm việc hàng loạt (`OfficeScheduleApplication`) chạy theo từng chi nhánh.
- Soft-delete.

---

### `departments` — Phòng ban

Cơ cấu tổ chức bên trong chi nhánh.

| Cột | Nghiệp vụ |
|-----|-----------|
| `office_id` | Thuộc chi nhánh nào |
| `parent_id` | Phòng ban cha (hỗ trợ cây phân cấp) |
| `code` | Mã phòng ban, unique trong chi nhánh |
| `name` | Tên phòng ban |

**Quy tắc nghiệp vụ:**
- Cho phép cây phòng ban nhiều cấp (tổ → nhóm → phòng → ban).
- Tài xế gán vào phòng ban để phân nhóm báo cáo.
- Soft-delete.

---

### `positions` — Chức vụ / Vị trí công việc

Định nghĩa bậc lương và cấp bậc.

| Cột | Nghiệp vụ |
|-----|-----------|
| `company_id` | Chức vụ thuộc công ty (mỗi công ty tự định nghĩa) |
| `code` | Mã chức vụ — unique trong công ty |
| `name` | Tên chức vụ (Tài xế A, Tài xế B, Trưởng xe…) |
| `base_salary` | Mức lương cơ bản tương ứng chức vụ (decimal 15,2) |
| `level` | Cấp bậc số (1 = thấp nhất) — dùng phân quyền, báo cáo |

**Quy tắc nghiệp vụ:**
- `base_salary` là giá trị mặc định khi tính lương — có thể override bằng `employee_salary_configs`.
- Khi chức vụ thay đổi lương, chỉ ảnh hưởng các kỳ lương từ ngày áp dụng trở đi.
- Soft-delete.

---

### `user_companies` — Liên kết User ↔ Công ty

Cho phép một user truy cập nhiều công ty (kế toán ngoài, kiểm toán viên…).

| Cột | Nghiệp vụ |
|-----|-----------|
| `user_id` | Tài khoản đăng nhập |
| `company_id` | Công ty được truy cập |
| `is_default` | `true` = công ty mặc định khi đăng nhập |

**Quy tắc nghiệp vụ:**
- Mỗi user có tối đa 1 công ty `is_default = true`.
- Khi đăng nhập, hệ thống chọn context theo công ty mặc định.
- User có thể switch context sang công ty khác trong cùng phiên.

---

## 2. Quản lý tài xế

### `drivers` — Tài xế

Bảng trung tâm, tổng hợp thông tin nhân sự + nghiệp vụ vận tải.

**Nhóm thông tin cá nhân:**

| Cột | Nghiệp vụ |
|-----|-----------|
| `code` | Mã nhân viên — unique trong công ty |
| `name`, `email`, `phone` | Định danh cơ bản |
| `dob` | Ngày sinh — tính tuổi, bảo hiểm |
| `gender` | Giới tính |
| `address` | Địa chỉ thường trú |
| `avatar_url`, `identity_image_url` | Ảnh hồ sơ, ảnh CCCD |

**Nhóm CCCD / Giấy tờ:**

| Cột | Nghiệp vụ |
|-----|-----------|
| `national_id_no` | Số CCCD — UNIQUE (mỗi người chỉ 1 CCCD) |
| `national_id_issue_date` | Ngày cấp |
| `national_id_issue_place` | Nơi cấp |

**Nhóm bằng lái xe:**

| Cột | Nghiệp vụ |
|-----|-----------|
| `license_no` | Số GPLX — UNIQUE (không trùng giữa các tài xế) |
| `license_class` | Hạng bằng (B1, B2, C, D, E, F…) — ảnh hưởng loại xe được phép lái |
| `expired_date` | Ngày hết hạn GPLX — cảnh báo trước khi hết hạn |
| `license_image_url` | Scan bản GPLX |

**Nhóm bảo hiểm:**

| Cột | Nghiệp vụ |
|-----|-----------|
| `social_insurance_no` | Số BHXH |
| `health_insurance_no` | Số BHYT |
| `insurance_registered_at` | Ngày đăng ký bảo hiểm |
| `driver_insurance_no` | Bảo hiểm lái xe riêng |
| `driver_insurance_expired_date` | Hết hạn bảo hiểm lái xe — ảnh hưởng khi điều xe |
| `health_certificate_no` | Số giấy khám sức khỏe |
| `health_certificate_expired_date` | Hết hạn giấy khám |

**Nhóm ngân hàng (trả lương):**

| Cột | Nghiệp vụ |
|-----|-----------|
| `bank_name` | Tên ngân hàng |
| `bank_account_no` | Số tài khoản |
| `bank_account_name` | Tên chủ tài khoản (khớp tên trên ngân hàng) |

**Nhóm tổ chức & trạng thái:**

| Cột | Nghiệp vụ |
|-----|-----------|
| `company_id` | Thuộc công ty nào (auto-populate từ `office_id`) |
| `office_id` | Chi nhánh phụ trách |
| `department_id` | Phòng ban |
| `position_id` | Chức vụ (dùng làm lương cơ bản mặc định) |
| `status` | `active` / `inactive` / `resigned` |
| `available_status` | `available` / `busy` / `offline` — điều phối xe real-time |
| `join_date` | Ngày vào làm |
| `resign_date` | Ngày nghỉ việc (null = đang làm) |

**Quy tắc nghiệp vụ:**
- `available_status` cập nhật real-time khi tài xế nhận chuyến (`busy`) hoặc kết thúc chuyến (`available`).
- Dữ liệu nhạy cảm (phone, CCCD, số tài khoản) có thể mã hóa theo cấu hình (`ENCRYPT_PII_FIELDS`).
- Soft-delete: khi nghỉ việc, không xóa, set `status = resigned` và `resign_date`.
- `license_no` và `national_id_no` phải UNIQUE toàn hệ thống — không cho phép trùng.

---

## 3. Quản lý phương tiện

### `vehicles` — Phương tiện

| Cột | Nghiệp vụ |
|-----|-----------|
| `company_id` | Thuộc công ty (auto-populate từ `office_id`) |
| `office_id` | Chi nhánh quản lý xe |
| `plate_number` | Biển số xe — UNIQUE toàn hệ thống |
| `type` | Loại xe (truck, van, car, bus…) |
| `brand`, `model` | Hãng và dòng xe |
| `year` | Năm sản xuất |
| `capacity` | Trọng tải / số chỗ ngồi |
| `status` | `active` = đang sử dụng; `maintenance` = đang sửa; `inactive` = ngừng hoạt động |
| `image_front/back/side/other` | Ảnh xe 4 góc (đang được chuẩn hóa sang `vehicle_images`) |

**Quy tắc nghiệp vụ:**
- Xe `status = maintenance` không được điều phối cho chuyến mới.
- Khi thêm xe mới, `company_id` tự động lấy từ `office_id`.
- Soft-delete; lịch sử xe lưu qua `vehicle_assignments`, `vehicle_expenses`.

---

### `vehicle_assignments` — Phân công xe–tài xế

Ghi nhận tài xế nào được giao xe nào trong khoảng thời gian nào.

| Cột | Nghiệp vụ |
|-----|-----------|
| `vehicle_id` | Xe được phân công |
| `driver_id` | Tài xế được giao |
| `from_date` | Ngày bắt đầu giao xe |
| `to_date` | Ngày kết thúc (`null` = đang còn hiệu lực) |
| `company_id` | Auto-populate từ `driver_id` |

**Quy tắc nghiệp vụ:**
- CHECK constraint: `to_date IS NULL OR to_date >= from_date`.
- Một xe chỉ nên giao cho 1 tài xế tại một thời điểm (enforce ở application layer).
- Dùng để xác định trách nhiệm bảo dưỡng, chi phí nhiên liệu.
- Soft-delete.

---

### `vehicle_expenses` — Chi phí phương tiện

Ghi nhận mọi chi phí phát sinh cho xe.

| Cột | Nghiệp vụ |
|-----|-----------|
| `vehicle_id` | Xe phát sinh chi phí |
| `driver_id` | Tài xế báo cáo chi phí |
| `type` | `fuel` / `maintenance` / `repair` / `toll` / `parking` / `other` |
| `amount` | Số tiền (decimal 15,2) |
| `expense_date` | Ngày phát sinh |
| `note` | Ghi chú (hoá đơn, mô tả) |
| `company_id` | Auto-populate từ `driver_id`, fallback từ `vehicle_id` |

**Quy tắc nghiệp vụ:**
- Chi phí nhiên liệu vượt định mức có thể tạo `fuel_excess_deduction` trong bảng lương.
- Dùng để báo cáo chi phí vận hành theo xe, theo tài xế, theo kỳ.
- Soft-delete.

---

### `vehicle_images` — Ảnh phương tiện *(bảng mới)*

Chuẩn hóa ảnh xe thay vì 4 cột cứng trong `vehicles`.

| Cột | Nghiệp vụ |
|-----|-----------|
| `vehicle_id` | Thuộc xe nào |
| `image_type` | `front` / `back` / `side` / `interior` / `document` / `other` |
| `url` | Đường dẫn ảnh (S3, CDN…) |
| `sort_order` | Thứ tự hiển thị |

**Quy tắc nghiệp vụ:**
- Cho phép nhiều ảnh cùng loại (ví dụ: nhiều ảnh `document` — đăng kiểm, bảo hiểm xe).
- Linh hoạt hơn 4 cột cứng: dễ thêm loại ảnh mới không cần migration.

---

## 4. Chuyến đi & Khách hàng

### `customers` — Khách hàng

| Cột | Nghiệp vụ |
|-----|-----------|
| `company_id` | Khách hàng thuộc công ty vận tải nào |
| `type` | `individual` / `business` — ảnh hưởng loại hoá đơn |
| `name` | Tên khách hàng / doanh nghiệp |
| `tax_code` | MST — bắt buộc khi khách hàng là doanh nghiệp (xuất hoá đơn VAT) |
| `phone`, `email` | Liên hệ |
| `address` | Địa chỉ giao/nhận hàng |

**Quy tắc nghiệp vụ:**
- Soft-delete.
- Một khách hàng có thể có nhiều chuyến đi và nhiều hoá đơn.

---

### `trips` — Chuyến đi

Đơn vị nghiệp vụ cốt lõi — mỗi chuyến là một lần vận chuyển hàng/khách.

| Cột | Nghiệp vụ |
|-----|-----------|
| `company_id` | Thuộc công ty (auto-populate từ `driver_id` hoặc `vehicle_id`) |
| `code` | Mã chuyến — unique trong công ty |
| `customer_id` | Khách hàng đặt chuyến |
| `driver_id` | Tài xế thực hiện |
| `vehicle_id` | Xe sử dụng |
| `start_point`, `end_point` | Điểm xuất phát và điểm đến |
| `distance_km` | Quãng đường (decimal 10,2) — ảnh hưởng tính thưởng km |
| `start_time`, `end_time` | Thời gian thực tế bắt đầu/kết thúc |
| `price` | Doanh thu chuyến (decimal 15,2) — giá báo khách |
| `status` | Vòng đời chuyến (xem bên dưới) |

**Vòng đời chuyến đi:**
```
pending → assigned → in_progress → completed
                  ↘               ↗
                   cancelled
```

| Trạng thái | Ý nghĩa |
|-----------|---------|
| `pending` | Chờ phân công tài xế/xe |
| `assigned` | Đã có tài xế và xe, chờ xuất phát |
| `in_progress` | Đang trên đường |
| `completed` | Hoàn thành — khoá số km, thời gian |
| `cancelled` | Huỷ (trước hoặc trong khi thực hiện) |

**Quy tắc nghiệp vụ:**
- CHECK: `end_time IS NULL OR start_time IS NULL OR end_time >= start_time`.
- Khi `completed`: tự động tính thưởng theo `trip_bonus_rules` dựa trên `distance_km`.
- `status = completed` mới được tạo hoá đơn (`invoices`).
- Soft-delete; lịch sử trạng thái lưu vào `trip_status_histories`.

---

### `trip_status_histories` — Lịch sử trạng thái chuyến

| Cột | Nghiệp vụ |
|-----|-----------|
| `trip_id` | Chuyến đi |
| `from_status` | Trạng thái trước |
| `to_status` | Trạng thái sau |
| `changed_by` | User thực hiện thay đổi |
| `changed_at` | Thời điểm thay đổi |
| `note` | Ghi chú lý do (vd: lý do huỷ) |

Không xóa (immutable audit trail).

---

### `trip_bonus_rules` — Quy tắc thưởng chuyến

Định nghĩa bậc thưởng km cho tài xế.

| Cột | Nghiệp vụ |
|-----|-----------|
| `company_id` | Quy tắc của công ty nào |
| `min_km`, `max_km` | Khoảng km áp dụng (`max_km = null` = không giới hạn trên) |
| `bonus_per_km` | Tiền thưởng/km trong khoảng này |
| `effective_from`, `effective_to` | Hiệu lực từ ngày → đến ngày |

**Quy tắc nghiệp vụ:**
- CHECK: `max_km IS NULL OR max_km > min_km`.
- Nhiều bậc tồn tại song song (vd: 0–100km: 5.000đ/km; 101–200km: 7.000đ/km).
- Tính theo `distance_km` của chuyến và ngày chuyến hoàn thành để xác định rule hiệu lực.
- Soft-delete.

---

## 5. Hóa đơn & Doanh thu

### `invoices` — Hóa đơn

| Cột | Nghiệp vụ |
|-----|-----------|
| `company_id` | Công ty phát hành hoá đơn (P0 fix) |
| `code` | Số hoá đơn — unique |
| `trip_id` | Chuyến đi liên quan (`nullable` — có thể hoá đơn gộp) |
| `customer_id` | Khách hàng |
| `subtotal` | Chưa VAT |
| `vat_rate` | Tỷ lệ VAT (%) |
| `vat_amount` | `subtotal × vat_rate / 100` |
| `total_amount` | `subtotal + vat_amount` |
| `status` | Vòng đời hoá đơn (xem bên dưới) |
| `issued_at` | Ngày xuất hoá đơn cho khách |
| `paid_at` | Ngày ghi nhận thanh toán |

**Vòng đời hoá đơn:**
```
draft → issued → paid
     ↘         ↗
      cancelled
```

| Trạng thái | Ý nghĩa |
|-----------|---------|
| `draft` | Bản nháp, chưa gửi khách |
| `issued` | Đã phát hành (gửi khách) |
| `paid` | Đã thu tiền |
| `cancelled` | Huỷ (do sai thông tin, chuyến huỷ…) |

**Quy tắc nghiệp vụ:**
- CHECK constraints: `total_amount = subtotal + vat_amount`; `subtotal >= 0`.
- **Trigger bảo vệ**: hoá đơn `status = paid` không thể bị xóa (kể cả soft-delete).
- Trạng thái `paid` chỉ có thể đổi sang `cancelled` với lý do rõ ràng.
- Lịch sử trạng thái lưu vào `invoice_status_histories`.

---

### `invoice_status_histories` — Lịch sử trạng thái hoá đơn

Tương tự `trip_status_histories`. Immutable.

---

## 6. Lịch làm việc & Tăng ca

### `work_schedule_templates` — Mẫu ca làm việc

Định nghĩa các ca chuẩn để áp dụng hàng loạt.

| Cột | Nghiệp vụ |
|-----|-----------|
| `company_id` | Thuộc công ty |
| `name` | Tên mẫu (Ca sáng, Ca tối, Ca hành chính…) |
| `shift_code` | Mã ca (DAY, NIGHT, EVE…) |
| `start_time`, `end_time` | Giờ bắt đầu/kết thúc ca |
| `is_active` | Đang được sử dụng? |
| `description` | Ghi chú thêm |

**Quy tắc nghiệp vụ:**
- Mẫu ca là dữ liệu tham chiếu — không sửa mẫu đang được áp dụng.
- Dùng qua `OfficeScheduleApplication` để sinh lịch hàng loạt cho cả chi nhánh.
- Soft-delete.

---

### `office_schedule_applications` — Lịch sử áp dụng lịch theo chi nhánh

Ghi lại việc một mẫu ca được áp dụng hàng loạt cho chi nhánh.

| Cột | Nghiệp vụ |
|-----|-----------|
| `office_id` | Chi nhánh được áp dụng |
| `work_schedule_template_id` | Mẫu ca dùng |
| `start_date`, `end_date` | Khoảng thời gian áp dụng |
| `applied_by` | User thực hiện |
| `drivers_affected` | Số tài xế bị ảnh hưởng |
| `rows_created` | Số dòng `driver_work_schedules` được tạo |
| `meta` | Metadata thực thi |

Bảng này là audit trail — không sửa sau khi ghi.

---

### `driver_work_schedules` — Lịch làm việc tài xế (từng ngày)

Lịch làm việc cụ thể cho từng tài xế từng ngày.

| Cột | Nghiệp vụ |
|-----|-----------|
| `driver_id`, `company_id`, `office_id` | Định danh |
| `work_date` | Ngày làm việc |
| `shift_code` | Ca làm việc |
| `start_time`, `end_time` | Giờ ca cụ thể ngày đó |
| `vehicle_id` | Xe pre-assign (nullable) |
| `status` | Vòng đời lịch (xem bên dưới) |
| `hos_override_reason` | Lý do vượt quy định HOS (Hours of Service) |
| `submitted_by`, `submitted_at` | Ai nộp lịch, khi nào |
| `approved_by`, `approved_at` | Ai duyệt, khi nào |
| `locked_by`, `locked_at` | Ai khoá (finalize), khi nào |

**Vòng đời lịch:**
```
draft → submitted → approved → locked
```

| Trạng thái | Ý nghĩa |
|-----------|---------|
| `draft` | Mới tạo, chưa nộp |
| `submitted` | Tài xế/supervisor đã xác nhận |
| `approved` | HR/Quản lý đã duyệt |
| `locked` | Đã khoá — dùng làm căn cứ tính lương |

**Quy tắc nghiệp vụ:**
- Unique: `(driver_id, work_date, shift_code)` — mỗi tài xế mỗi ca chỉ 1 lịch/ngày.
- CHECK: `end_time > start_time` (ca đêm qua 0h xử lý ở app layer).
- Lịch `locked` là nguồn dữ liệu duy nhất để tính `working_days` trong bảng lương.
- `hos_override_reason` bắt buộc khi vi phạm quy định giờ lái xe (HOS regulations).
- Soft-delete.

---

### `overtime_requests` — Đề nghị tăng ca

| Cột | Nghiệp vụ |
|-----|-----------|
| `driver_id`, `company_id` | Định danh |
| `work_date` | Ngày tăng ca |
| `start_time`, `end_time` | Giờ tăng ca |
| `ot_hours` | Số giờ tăng ca thực tế (decimal 5,2) |
| `reason` | Lý do tăng ca |
| `status` | `pending` / `approved` / `rejected` |
| `requested_by` | User đề nghị (supervisor/tài xế) |
| `approved_by` | User phê duyệt |
| `rejection_reason` | Lý do từ chối (nếu rejected) |
| `payroll_id` | Kỳ lương áp dụng khoản tăng ca này |

**Quy tắc nghiệp vụ:**
- CHECK: `end_time > start_time` và `ot_hours > 0 AND ot_hours <= 24`.
- Chỉ tăng ca `approved` mới được đưa vào tính `overtime_pay` trong kỳ lương.
- Kết hợp với `night_shift_policies` để tính phụ cấp đêm.
- Soft-delete.

---

### `public_holidays` — Ngày lễ quốc gia

| Cột | Nghiệp vụ |
|-----|-----------|
| `country_code` | Quốc gia (mặc định `VN`) |
| `year`, `date` | Năm và ngày lễ cụ thể |
| `name` | Tên ngày lễ |
| `holiday_type` | `national` / `regional` / `custom` |
| `is_compensatory` | Ngày bù (`true`) hay ngày lễ chính (`false`) |
| `compensatory_for` | Ngày lễ gốc mà ngày này bù lại |

**Quy tắc nghiệp vụ:**
- Dùng để xác định ngày làm việc hưởng lương lễ (`public_holiday_pay`) gấp 3 theo luật.
- Không có soft-delete — dữ liệu tham chiếu cố định.
- Method `isHoliday(date, countryCode)` tra cứu real-time.

---

### `night_shift_policies` — Chính sách phụ cấp ca đêm

| Cột | Nghiệp vụ |
|-----|-----------|
| `company_id` | Chính sách riêng theo công ty |
| `start_hour`, `end_hour` | Khung giờ ban đêm (vd: 22h → 6h) |
| `differential_pct` | % phụ cấp thêm (vd: 20 = thêm 20%) |
| `effective_from`, `effective_to` | Hiệu lực từ/đến |
| `is_active` | Đang áp dụng? |

**Quy tắc nghiệp vụ:**
- Nhiều chính sách có thể tồn tại nhưng chỉ 1 được `is_active = true` tại một thời điểm.
- Dùng khi tính `night_shift_allowance` trong bảng lương.
- Soft-delete.

---

## 7. Nghỉ phép

### `leave_types` — Loại nghỉ phép

| Cột | Nghiệp vụ |
|-----|-----------|
| `company_id` | `null` = loại nghỉ phép mặc định hệ thống; có giá trị = riêng công ty |
| `code` | Mã loại (ANNUAL, SICK, MATERNITY…), unique trong (company_id, code) |
| `name` | Tên hiển thị |
| `is_paid` | `true` = nghỉ hưởng lương; `false` = nghỉ không lương |
| `annual_quota_days` | Số ngày được phép/năm |
| `allow_carry_forward` | Cho phép chuyển ngày còn thừa sang năm sau? |
| `requires_attachment` | Yêu cầu đính kèm giấy tờ (vd: giấy khám bệnh cho nghỉ ốm)? |
| `status` | `active` / `inactive` |

**Quy tắc nghiệp vụ:**
- Soft-delete; không xóa loại nghỉ đang được sử dụng.
- `is_paid = false` → ngày nghỉ trừ `leave_unpaid_deduction` trong bảng lương.

---

### `leave_requests` — Đơn xin nghỉ phép

| Cột | Nghiệp vụ |
|-----|-----------|
| `driver_id` | Tài xế xin nghỉ |
| `leave_type_id` | Loại nghỉ phép |
| `from_date`, `to_date` | Khoảng thời gian nghỉ |
| `total_days` | Số ngày (decimal, hỗ trợ nghỉ nửa ngày: 0.5) |
| `reason` | Lý do xin nghỉ |
| `status` | Vòng đời đơn (xem bên dưới) |
| `approved_by`, `approved_at` | Người duyệt, thời điểm |
| `rejection_reason` | Lý do từ chối |
| `attachment_urls` | Danh sách file đính kèm (JSON array) |

**Vòng đời đơn nghỉ:**
```
draft → pending → approved
                ↘ rejected
     (cancelled — bất kỳ lúc nào trước approved)
```

**Quy tắc nghiệp vụ:**
- CHECK: `to_date >= from_date`.
- Khi duyệt: kiểm tra `leave_balances` — `remaining_days >= total_days`.
- Phát hiện trùng lịch: scope `overlapping(from, to)` kiểm tra các đơn pending/approved cùng tài xế.
- Khi `approved` và `is_paid = true`: trừ `used_days` trong `leave_balances`.
- Soft-delete.

---

### `leave_balances` — Số dư ngày phép

| Cột | Nghiệp vụ |
|-----|-----------|
| `driver_id` | Tài xế |
| `leave_type_id` | Loại nghỉ phép |
| `year` | Năm tính số dư |
| `entitled_days` | Tổng ngày phép được hưởng trong năm |
| `used_days` | Đã nghỉ bao nhiêu ngày (cộng dồn khi đơn được duyệt) |
| `carried_forward_days` | Ngày phép chuyển từ năm trước (nếu `allow_carry_forward = true`) |

**Quy tắc nghiệp vụ:**
- Unique: `(driver_id, leave_type_id, year)`.
- Còn lại = `entitled_days + carried_forward_days - used_days`.
- CHECK: `entitled_days >= 0`, `used_days >= 0`, `used_days <= entitled_days + carried_forward_days`.
- Không soft-delete (số dư là dữ liệu kế toán).

---

## 8. Chấm công

### `attendances` — Chấm công hàng ngày

| Cột | Nghiệp vụ |
|-----|-----------|
| `driver_id` | Tài xế |
| `date` | Ngày chấm công |
| `check_in` | Giờ vào (TIME) |
| `check_out` | Giờ ra (TIME) |
| `work_hours` | Số giờ làm thực tế (decimal 5,2) — tính từ check_in/out hoặc nhập tay |
| `overtime_hours` | Giờ tăng ca (decimal 5,2) |
| `status` | Phân loại ngày làm việc |

| Status | Ý nghĩa |
|--------|---------|
| `present` | Đi làm đầy đủ |
| `absent` | Vắng không phép |
| `late` | Đi muộn |
| `half_day` | Làm nửa ngày |
| `leave` | Nghỉ phép hợp lệ |

**Quy tắc nghiệp vụ:**
- Unique: `(driver_id, date)` — mỗi tài xế chỉ 1 bản ghi chấm công/ngày.
- Không thêm CHECK `check_out >= check_in` vì cột là kiểu `TIME` — ca đêm qua 0h sẽ fail constraint.
- Nguồn dữ liệu cho `attendance_summaries` để tổng hợp kỳ lương.
- Soft-delete.

---

### `attendance_summaries` — Tổng hợp chấm công theo kỳ lương

| Cột | Nghiệp vụ |
|-----|-----------|
| `payroll_period_id` | Kỳ lương |
| `employee_id` | Tài xế (cột giữ tên employee_id sau merge) |
| `working_days` | Ngày công chuẩn trong kỳ |
| `actual_days` | Ngày thực tế đi làm |
| `leave_paid_days` | Ngày nghỉ có lương |
| `leave_unpaid_days` | Ngày nghỉ không lương |
| `overtime_hours` | Tổng giờ OT trong kỳ |
| `status` | `draft` (khi đang tính) |
| `approved_by`, `approved_at` | Ai xác nhận tổng hợp |
| `source` | `auto` (hệ thống tính) / `manual` (nhập tay) |
| `meta_json` | Dữ liệu chi tiết ngày từng ngày |

**Quy tắc nghiệp vụ:**
- Được tạo tự động khi chạy tính lương.
- `source = manual`: cho phép HR điều chỉnh trước khi khoá lương.
- Soft-delete.

---

## 9. Tính lương

### `payroll_periods` — Kỳ lương

Định nghĩa chu kỳ trả lương của từng công ty.

| Cột | Nghiệp vụ |
|-----|-----------|
| `company_id` | Công ty |
| `code` | Mã kỳ (vd: `2026-01`) |
| `period_type` | `monthly` / `semi-monthly` / `weekly` |
| `start_date`, `end_date` | Khoảng thời gian tính lương |
| `cutoff_date` | Ngày chốt chấm công — sau ngày này không được sửa attendance |
| `pay_date` | Ngày trả lương thực tế |
| `status` | `draft` → `open` → `closed` → `paid` |
| `timezone` | Múi giờ (mặc định Asia/Ho_Chi_Minh) |

**Quy tắc nghiệp vụ:**
- Unique: `(company_id, code)`.
- Kỳ lương là tham chiếu cho `overtime_requests`, `attendance_summaries`.
- Soft-delete.

---

### `payrolls` — Bảng lương (batch)

Bảng lương tháng của một công ty.

| Cột | Nghiệp vụ |
|-----|-----------|
| `company_id` | Công ty |
| `month`, `year` | Kỳ lương |
| `status` | Vòng đời (xem bên dưới) |
| `locked_at`, `locked_by` | Khoá để tính toán |
| `approved_at`, `approved_by` | HR/Giám đốc phê duyệt |
| `paid_at`, `paid_by` | Ghi nhận đã trả lương |
| `notes` | Ghi chú kỳ lương |
| `snapshot_json` | Snapshot cấu hình tại thời điểm tính (tỷ lệ bảo hiểm, thuế…) |

**Vòng đời bảng lương:**
```
draft → locked → approved → paid
```

| Trạng thái | Ý nghĩa | Có thể tính lại? |
|-----------|---------|-----------------|
| `draft` | Đang soạn thảo | Có |
| `locked` | Đã khoá để review | Không |
| `approved` | HR đã duyệt | Không |
| `paid` | Đã chuyển khoản | Không |

**Quy tắc nghiệp vụ:**
- Unique: `(company_id, month, year)`.
- `isFrozen()` = `status IN (locked, approved, paid)` — không cho tính lại.
- **Trigger**: không thể soft-delete khi `status IN (approved, locked, paid)`.
- Dùng pessimistic lock khi tạo/recalculate để tránh race condition.
- Soft-delete.

---

### `payroll_lines` — Chi tiết lương từng tài xế

Bảng lương phân rã đến từng dòng thu nhập/khấu trừ của tài xế.

**Nhóm thu nhập:**

| Cột | Nghiệp vụ |
|-----|-----------|
| `base_salary` | Lương cơ bản (từ position) |
| `trip_bonus` | Thưởng km — tính từ trips × trip_bonus_rules |
| `overtime_pay` | Lương tăng ca — từ overtime_requests approved |
| `night_shift_allowance` | Phụ cấp ca đêm — từ night_shift_policies |
| `public_holiday_pay` | Lương lễ (×3 lương ngày) |
| `allowance` | Phụ cấp khác (xăng, ăn trưa, điện thoại…) |

**Nhóm khấu trừ:**

| Cột | Nghiệp vụ |
|-----|-----------|
| `deduction` | Khấu trừ tổng hợp (BHXH, BHYT, BHTN) |
| `leave_unpaid_deduction` | Trừ ngày nghỉ không lương |
| `violation_deduction` | Tiền phạt vi phạm đã confirm |
| `fuel_excess_deduction` | Trừ vượt định mức xăng |
| `tax` | Thuế TNCN |

**Kết quả:**

| Cột | Nghiệp vụ |
|-----|-----------|
| `net_salary` | Thực lĩnh = Σ thu nhập − Σ khấu trừ − tax |

**Thống kê kỳ:**

| Cột | Nghiệp vụ |
|-----|-----------|
| `working_days` | Ngày công thực tế |
| `leave_days_paid`, `leave_days_unpaid` | Ngày nghỉ có/không lương |
| `overtime_hours` | Tổng giờ OT |
| `trips_completed_count` | Số chuyến hoàn thành |
| `total_distance_km` | Tổng km đã chạy |
| `meta_json` | Log quá trình tính (debug, audit) |

**Quy tắc nghiệp vụ:**
- Unique: `(payroll_id, driver_id)`.
- Soft-delete.

---

### `payroll_adjustments` — Điều chỉnh lương (retroactive)

Ghi nhận các điều chỉnh lương bổ sung hoặc khấu trừ sau khi tính lương.

| Cột | Nghiệp vụ |
|-----|-----------|
| `payroll_id` | Kỳ lương hiện tại (điều chỉnh vào kỳ nào) |
| `original_payroll_id` | Kỳ lương gốc bị điều chỉnh (nếu là retroactive) |
| `driver_id` | Tài xế |
| `type` | `addition` (cộng thêm) / `deduction` (trừ bớt) |
| `category` | `violation_refund` / `leave_restore` / `ot_late_approval` / `manual` |
| `amount` | Số tiền (CHECK: `amount > 0` — dấu suy từ `type`) |
| `reason` | Lý do điều chỉnh |
| `source_type`, `source_id` | Nguồn gốc điều chỉnh (violation_id, expense_id…) |
| `approved_by` | Người phê duyệt |

**Quy tắc nghiệp vụ:**
- CHECK: `amount > 0` — không nhập số âm (dấu xác định bởi `type`).
- `netAmount()` = `+amount` nếu addition; `-amount` nếu deduction.
- Dùng để xử lý OT được duyệt muộn, vi phạm bị lật án, nghỉ phép được restore.
- Soft-delete.

---

### `payroll_status_histories` — Lịch sử trạng thái bảng lương

Immutable audit trail, ghi nhận mỗi lần bảng lương đổi trạng thái.

---

### `employee_salary_configs` — Cấu hình lương riêng theo tài xế

Override `base_salary` từ `position` cho tài xế cụ thể.

| Cột | Nghiệp vụ |
|-----|-----------|
| `employee_id` | Tài xế |
| `effective_from`, `effective_to` | Hiệu lực từ/đến |
| `base_salary` | Lương cơ bản override |
| `currency` | Đơn vị tiền tệ |
| `pay_frequency` | Tần suất trả lương |

---

## 10. Vi phạm & Kỷ luật

### `violations` — Vi phạm

| Cột | Nghiệp vụ |
|-----|-----------|
| `driver_id`, `company_id` | Định danh |
| `trip_id` | Chuyến đi liên quan (nullable) |
| `type` | `speeding` / `route_deviation` / `fuel_misuse` / `behavior` / `accident` / `other` |
| `occurred_at` | Thời điểm vi phạm xảy ra |
| `reported_by` | Người ghi nhận vi phạm |
| `description` | Mô tả chi tiết |
| `penalty_amount` | Tiền phạt dự kiến (decimal 15,2) |
| `status` | Vòng đời vi phạm (xem bên dưới) |
| `confirmed_by`, `confirmed_at` | Ai xác nhận, khi nào |
| `waived_by`, `waived_at`, `waive_reason` | Ai miễn phạt, lý do |
| `evidence_urls` | Danh sách bằng chứng (ảnh, video — JSON array) |

**Vòng đời vi phạm:**
```
pending → confirmed → (deducted in payroll)
        ↘            ↘ waived (miễn phạt)
         disputed → resolved_upheld (giữ nguyên)
                  ↘ resolved_overturned (lật án → waived)
```

**Quy tắc nghiệp vụ:**
- Chỉ vi phạm `confirmed` mới tạo `violation_deduction` trong `payroll_lines`.
- Vi phạm bị lật án (`resolved_overturned`) → tạo `payroll_adjustment` category `violation_refund` để hoàn tiền.
- Soft-delete.

---

### `violation_disputes` — Khiếu nại vi phạm

| Cột | Nghiệp vụ |
|-----|-----------|
| `violation_id` | Vi phạm bị khiếu nại |
| `driver_id` | Tài xế khiếu nại |
| `reason` | Lý do phản bác |
| `evidence_urls` | Bằng chứng phản bác |
| `status` | `open` → `under_review` → `resolved_upheld` / `resolved_overturned` |
| `resolved_by`, `resolved_at` | Người xử lý |
| `resolution_note` | Kết luận |

**Quy tắc nghiệp vụ:**
- Mỗi vi phạm chỉ có 1 khiếu nại active tại một thời điểm.
- `resolved_upheld`: giữ nguyên phạt.
- `resolved_overturned`: huỷ phạt → hệ thống tự động tạo `payroll_adjustment` hoàn tiền nếu đã trừ lương.
- Soft-delete.

---

## 11. Kế toán nội bộ

### `chart_of_accounts` — Hệ thống tài khoản kế toán

| Cột | Nghiệp vụ |
|-----|-----------|
| `company_id` | `null` = tài khoản mặc định hệ thống; có giá trị = tài khoản riêng công ty |
| `code` | Mã tài khoản (unique trong phạm vi company) |
| `name` | Tên tài khoản |
| `type` | `asset` / `liability` / `equity` / `revenue` / `expense` |
| `parent_id` | Tài khoản cha (cây kế toán) |
| `is_postable` | `true` = có thể định khoản trực tiếp; `false` = chỉ là nhóm tài khoản |
| `status` | `active` / `inactive` |

**Quy tắc nghiệp vụ:**
- Soft-delete.
- Hệ thống có thể có tài khoản mặc định (`company_id = null`) dùng chung.
- Chỉ tài khoản `is_postable = true` và `status = active` mới được dùng trong bút toán.

---

### `journal_entries` — Bút toán kế toán

| Cột | Nghiệp vụ |
|-----|-----------|
| `company_id` | Công ty |
| `entry_no` | Số bút toán (unique) |
| `entry_date` | Ngày hạch toán |
| `source_type`, `source_id` | Nguồn gốc (invoice, payroll, expense…) — polymorphic |
| `status` | `draft` → `posted` → `cancelled` |
| `description` | Diễn giải bút toán |
| `posted_by`, `posted_at` | Ai duyệt và ghi sổ |

**Quy tắc nghiệp vụ:**
- Bút toán `posted` không thể xóa (**trigger bảo vệ**) — phải tạo bút toán đảo.
- Không có `deleted_at` (bảng kế toán không soft-delete).

---

### `journal_entry_lines` — Dòng định khoản

| Cột | Nghiệp vụ |
|-----|-----------|
| `journal_entry_id` | Thuộc bút toán nào |
| `account_id` | Tài khoản kế toán |
| `debit` | Nợ (decimal 15,2) |
| `credit` | Có (decimal 15,2) |
| `line_description` | Diễn giải dòng |
| `line_no` | Số thứ tự dòng trong bút toán |

**Quy tắc nghiệp vụ:**
- CHECK double-entry: `(debit = 0 AND credit > 0) OR (debit > 0 AND credit = 0)` — chính xác 1 trong 2 > 0 (P0 fix).
- Tổng `debit` = Tổng `credit` trong cùng 1 bút toán (enforce ở application layer).
- Không soft-delete.

---

### `tax_brackets` — Bậc thuế TNCN

| Cột | Nghiệp vụ |
|-----|-----------|
| `level` | Bậc thuế (1–7 theo biểu thuế lũy tiến Việt Nam) |
| `income_from`, `income_to` | Khoảng thu nhập chịu thuế |
| `tax_rate` | Tỷ lệ % (5, 10, 15, 20, 25, 30, 35) |
| `quick_deduction` | Số khấu trừ nhanh (theo phương pháp tính đơn giản) |
| `effective_from`, `effective_to` | Hiệu lực từ/đến |
| `status` | `active` / `inactive` |

Không soft-delete — dữ liệu pháp lý.

---

### `insurance_rates` — Tỷ lệ bảo hiểm bắt buộc

| Cột | Nghiệp vụ |
|-----|-----------|
| `social_employee_rate` | Tỷ lệ BHXH người lao động đóng (%) |
| `social_company_rate` | Tỷ lệ BHXH doanh nghiệp đóng (%) |
| `health_employee_rate` | BHYT người lao động |
| `health_company_rate` | BHYT doanh nghiệp |
| `unemployment_employee_rate` | BHTN người lao động |
| `unemployment_company_rate` | BHTN doanh nghiệp |
| `salary_cap_amount` | Mức lương tối đa để tính bảo hiểm |
| `effective_from`, `effective_to` | Hiệu lực |
| `status` | `active` / `inactive` |

Không soft-delete — dữ liệu pháp lý.

---

## 12. Phân quyền

### `roles` — Vai trò

| Cột | Nghiệp vụ |
|-----|-----------|
| `company_id` | `null` = vai trò hệ thống (super_admin); có giá trị = vai trò riêng công ty |
| `name` | Tên vai trò, unique trong phạm vi (company_id, name) |
| `description` | Mô tả |

Soft-delete.

---

### `permissions` — Quyền hạn

| Cột | Nghiệp vụ |
|-----|-----------|
| `code` | Mã quyền (`payroll.view`, `violation.approve`, `trip.create`…) |
| `name` | Tên hiển thị |
| `description` | Mô tả quyền |

Soft-delete.

---

### `user_roles` — Gán vai trò cho user

Many-to-many: user ↔ role. Không soft-delete.

---

### `role_permissions` — Gán quyền cho vai trò

Many-to-many: role ↔ permission. Không soft-delete.

---

**Luồng kiểm tra quyền:**
```
Request → User → user_roles → Role → role_permissions → Permission.code
```
- Super admin (role `admin`): bypass kiểm tra, có tất cả quyền.
- Các role khác: kiểm tra Permission.code cụ thể.

---

## 13. Hệ thống & Logging

### `users` — Tài khoản đăng nhập

| Cột | Nghiệp vụ |
|-----|-----------|
| `username`, `email` | Thông tin đăng nhập |
| `password` | Hash bcrypt/argon2 |
| `driver_id` | Link đến hồ sơ tài xế (1-1, nullable nếu là staff nội bộ) |
| `status` | `active` / `inactive` |
| `last_login_at` | Thời gian đăng nhập gần nhất |
| `social_provider`, `social_provider_id` | OAuth (Google, Facebook…) |

**Quy tắc nghiệp vụ:**
- Soft-delete; không xóa user đã có lịch sử audit.
- Một user có thể thuộc nhiều công ty qua `user_companies`.

---

### `refresh_tokens` — Token làm mới phiên đăng nhập

| Cột | Nghiệp vụ |
|-----|-----------|
| `user_id` | Tài khoản |
| `token` | Plain token (cần hash về lâu dài) |
| `token_hash` | SHA-256 của token (thêm ở P1) |
| `access_token_id` | Token truy cập kèm theo |
| `expires_at` | Hết hạn |
| `is_revoked` | Đã thu hồi? |
| `ip_address`, `user_agent` | Thông tin thiết bị |

---

### `login_logs` — Lịch sử đăng nhập/đăng xuất

| Cột | Nghiệp vụ |
|-----|-----------|
| `user_id` | Tài khoản |
| `ip` | IP đăng nhập |
| `device` | Thiết bị/trình duyệt |
| `login_at`, `logout_at` | Thời gian vào/ra (`logout_at = null` = còn đang online) |
| `status` | `active` / `logged_out` / `expired` |
| `action` | `login` / `logout` / `token_refresh` |
| `performed_by` | User thực hiện (vd: admin force logout) |

Immutable — không xóa, không sửa.

---

### `audit_logs` — Nhật ký thay đổi dữ liệu

| Cột | Nghiệp vụ |
|-----|-----------|
| `user_id`, `company_id` | Ai thay đổi, trong context nào |
| `action` | `create` / `update` / `delete` |
| `table_name`, `record_id` | Bảng và bản ghi bị thay đổi |
| `old_data`, `new_data` | Dữ liệu trước/sau (JSON) |
| `ip_address`, `request_id`, `user_agent` | Thông tin request |
| `resource` | Tên resource nghiệp vụ (vd: `Driver`) |
| `metadata` | Context bổ sung |

Immutable (SOC2 compliance) — không cập nhật, chỉ INSERT.

---

### `password_reset_tokens` — Token đặt lại mật khẩu

| Cột | Nghiệp vụ |
|-----|-----------|
| `email` (PK) | Email đăng ký |
| `token` | Token gửi qua email |
| `expires_at` | Hết hạn token |

---

### `sessions` — Phiên làm việc

Bảng Laravel session mặc định. Không sửa code.

---

### `personal_access_tokens` — API tokens (Sanctum)

Token truy cập API cho mobile app, integrations. Mặc định của Laravel Sanctum.

---

### `notifications` — Thông báo hệ thống

Bảng Laravel Notifications mặc định (polymorphic `notifiable`).

---

### `export_logs` — Nhật ký xuất dữ liệu

| Cột | Nghiệp vụ |
|-----|-----------|
| `user_id` | Ai xuất |
| `type` | Loại export (`payroll`, `driver`, `invoice`…) |
| `file_name`, `file_path` | File đã xuất |
| `record_count` | Số dòng dữ liệu |

Compliance — tracking dữ liệu nào đã được export ra bên ngoài.

---

### `chat_messages` — Lịch sử chat AI

| Cột | Nghiệp vụ |
|-----|-----------|
| `session_id` | Phiên hội thoại |
| `message` | Câu hỏi user |
| `response` | Trả lời của AI |
| `context` | Ngữ cảnh lọc (công ty, kỳ tháng…) |
| `model` | Model AI dùng |
| `status` | `success` / `error` |

---

### `knowledge_articles` — Kho tri thức cho AI

| Cột | Nghiệp vụ |
|-----|-----------|
| `company_id` | `null` = chung hệ thống; có giá trị = riêng công ty |
| `tenant_priority` | Độ ưu tiên bài viết riêng công ty (cao hơn = ưu tiên hơn) |
| `category` | Chủ đề bài viết |
| `title`, `content` | Nội dung |
| `tags` | Tags tìm kiếm (JSON array) |
| `is_active` | Đang dùng? |

**Quy tắc nghiệp vụ:**
- `visibleTo(companyId)`: trả về bài viết riêng công ty (sắp theo `tenant_priority`) + bài viết chung.
- Dùng làm context cho AI assistant trả lời câu hỏi nghiệp vụ.

---

### `rag_index` — Chỉ mục tìm kiếm ngữ nghĩa

| Cột | Nghiệp vụ |
|-----|-----------|
| `source_table` | Bảng gốc (`drivers`, `trips`, `payrolls`…) |
| `source_id` | ID bản ghi gốc |
| `company_id` | Tenant context |
| `content` | Mô tả tự nhiên bằng tiếng Việt |
| `embedding` | Vector 1024 chiều (model bge-m3) |
| `metadata` | Filter có cấu trúc (ngày, trạng thái, công ty…) |
| `source_updated_at` | Thời gian cập nhật nguồn |

**Quy tắc nghiệp vụ:**
- `embedding = null` khi chưa được index.
- Dùng cho semantic search: tìm kiếm theo ngữ nghĩa thay vì exact match.
- Cần strategy 2 bước: FULLTEXT shortlist → re-rank bằng vector ở app layer (MySQL chưa có native ANN).

---

### `report_caches` — Cache báo cáo

| Cột | Nghiệp vụ |
|-----|-----------|
| `type` | Loại báo cáo |
| `month`, `year` | Kỳ báo cáo |
| `data_json` | Dữ liệu cache |
| `expires_at` | Hết hạn cache |

Soft-delete; tự động invalidate khi dữ liệu liên quan thay đổi.

---

### Laravel Framework Tables

Các bảng `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` là bảng hạ tầng của Laravel — không can thiệp.

---

## Tổng hợp vòng đời nghiệp vụ cốt lõi

```
Khách hàng đặt chuyến
    → Trip (pending → in_progress → completed)
    → Invoice (draft → issued → paid)

Tài xế làm việc hàng ngày
    → DriverWorkSchedule (draft → locked)
    → Attendance (check_in/check_out)
    → OvertimeRequest (pending → approved)
    → LeaveRequest (pending → approved)

Cuối tháng tính lương
    → Payroll (draft → locked → approved → paid)
        ├── PayrollLine (1 dòng/tài xế)
        │   ├── base_salary (từ position)
        │   ├── trip_bonus (từ trips × bonus_rules)
        │   ├── overtime_pay (từ overtime_requests)
        │   ├── night_shift_allowance (từ night_shift_policies)
        │   ├── public_holiday_pay (từ public_holidays)
        │   ├── leave_unpaid_deduction (từ leave_requests)
        │   ├── violation_deduction (từ violations confirmed)
        │   ├── fuel_excess_deduction (từ vehicle_expenses)
        │   └── tax (từ tax_brackets + insurance_rates)
        └── PayrollAdjustment (điều chỉnh retroactive)

Vi phạm
    → Violation (pending → confirmed → deducted)
    → ViolationDispute (open → resolved)
        └── nếu overturned → PayrollAdjustment (violation_refund)

Kế toán
    → JournalEntry (draft → posted)
        └── JournalEntryLines (double-entry, XOR debit/credit)
```
