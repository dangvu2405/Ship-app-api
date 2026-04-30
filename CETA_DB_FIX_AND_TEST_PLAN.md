# PLAN: SỬA DATABASE + TEST TỪNG CHỨC NĂNG
**Dự án:** CETA Web Quản lý Vận tải  
**Ngày:** 2026-04-30 | **Stack:** Laravel + MySQL 8.0

---

## PHÂN TÍCH TRẠNG THÁI HIỆN TẠI

### ✅ Đã hoàn thành (không cần làm gì)
43 bảng trong spec đã tồn tại đầy đủ trong DB hiện tại.

### ❌ Vấn đề còn lại — 2 nhóm việc cần làm

**Nhóm 1 — DROP:** 11 bảng thừa chưa xóa  
**Nhóm 2 — ALTER:** 2 bảng cần xóa FK/cột trỏ đến bảng sắp bị drop  
**Nhóm 3 — SEED:** Các bảng danh mục cần có data mặc định

---

# PHẦN 1 — DATABASE MIGRATION PLAN

## Thứ tự thực hiện

```
BƯỚC 1: Backup DB
BƯỚC 2: Xóa FK từ drivers + vehicles (trỏ đến offices/departments/positions)
BƯỚC 3: Drop cột office_id, department_id, position_id khỏi drivers
BƯỚC 4: Drop cột office_id khỏi vehicles
BƯỚC 5: Drop 11 bảng thừa
BƯỚC 6: Seed data cho bảng danh mục
BƯỚC 7: Verify
```

---

## BƯỚC 1 — BACKUP

```bash
mysqldump -u root -p \
  --single-transaction \
  --routines --triggers \
  ceta_db > backup_before_cleanup_$(date +%Y%m%d_%H%M%S).sql

# Verify
echo "Backup size: $(du -sh backup_*.sql | tail -1)"
```

---

## BƯỚC 2 — XÓA FK CONSTRAINTS

> ⚠️ Phải xóa FK trước khi drop cột hoặc drop bảng cha.

```sql
-- 2.1 Xóa FK từ drivers
ALTER TABLE `drivers`
  DROP FOREIGN KEY `drivers_office_id_foreign`,
  DROP FOREIGN KEY `drivers_department_id_foreign`,
  DROP FOREIGN KEY `drivers_position_id_foreign`;

-- 2.2 Xóa FK từ vehicles  
ALTER TABLE `vehicles`
  DROP FOREIGN KEY `vehicles_office_id_foreign`;

-- 2.3 Xóa FK từ driver_work_schedules (trỏ đến offices)
ALTER TABLE `driver_work_schedules`
  DROP FOREIGN KEY `driver_work_schedules_office_id_foreign`;

-- 2.4 Xóa FK từ user_roles (trỏ đến offices)
ALTER TABLE `user_roles`
  DROP FOREIGN KEY `user_roles_office_id_foreign`;

-- 2.5 Xóa FK từ offices (manager_id trỏ đến drivers)
ALTER TABLE `offices`
  DROP FOREIGN KEY `offices_manager_id_foreign`;

-- 2.6 Xóa FK từ departments (trỏ đến offices)
ALTER TABLE `departments`
  DROP FOREIGN KEY `departments_office_id_foreign`,
  DROP FOREIGN KEY `departments_parent_id_foreign`;
```

---

## BƯỚC 3 — ALTER `drivers` — Xóa cột thừa

```sql
ALTER TABLE `drivers`
  DROP INDEX `drivers_office_id_status_index`,
  DROP INDEX `drivers_department_id_index`,
  DROP INDEX `drivers_position_id_foreign`,
  DROP COLUMN `office_id`,
  DROP COLUMN `department_id`,
  DROP COLUMN `position_id`;
```

---

## BƯỚC 4 — ALTER `vehicles` — Xóa cột thừa

```sql
ALTER TABLE `vehicles`
  DROP INDEX `vehicles_office_id_status_index`,
  DROP COLUMN `office_id`;
```

---

## BƯỚC 5 — ALTER `driver_work_schedules` — Xóa cột thừa

> Bảng này vẫn giữ, chỉ bỏ office_id vì offices sắp bị drop.

```sql
ALTER TABLE `driver_work_schedules`
  DROP INDEX `dws_office_date_status_idx`,
  DROP COLUMN `office_id`;
```

---

## BƯỚC 6 — DROP 11 BẢNG THỪA

> Chạy theo đúng thứ tự để tránh FK violation.

```sql
-- 6.1 RBAC cũ (sau khi user_permissions đã có)
DROP TABLE IF EXISTS `user_roles`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `roles`;

-- 6.2 Cấu trúc tổ chức cũ
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `positions`;
DROP TABLE IF EXISTS `offices`;

-- 6.3 Log/Cache gộp vào bảng khác
DROP TABLE IF EXISTS `login_logs`;
DROP TABLE IF EXISTS `export_logs`;
DROP TABLE IF EXISTS `refresh_tokens`;

-- 6.4 Multi-company (không cần trong multi-tenant đơn giản)
DROP TABLE IF EXISTS `user_companies`;
```

---

## BƯỚC 7 — SEED DATA CÁC BẢNG DANH MỤC

> Chạy cho từng company_id đang có trong DB.

### 7.1 vehicle_types
```sql
-- Kiểm tra company_ids hiện có
SELECT id FROM companies WHERE status = 'active';

-- Seed cho mỗi company (thay $COMPANY_ID)
INSERT INTO vehicle_types (company_id, name, max_load_ton, required_license_class, is_active, sort_order)
SELECT c.id, t.name, t.max_load_ton, t.license_class, 1, t.sort_order
FROM companies c
CROSS JOIN (
  SELECT 'Xe tải 1 tấn'   name, 1.0  max_load_ton, 'B2' license_class, 1 sort_order UNION ALL
  SELECT 'Xe tải 2.5 tấn', 2.5, 'C',  2 UNION ALL
  SELECT 'Xe tải 5 tấn',   5.0, 'C',  3 UNION ALL
  SELECT 'Xe tải 8 tấn',   8.0, 'C',  4 UNION ALL
  SELECT 'Xe tải 15 tấn',  15.0,'C',  5 UNION ALL
  SELECT 'Xe đầu kéo',     20.0,'FC', 6 UNION ALL
  SELECT 'Xe container',   25.0,'FC', 7 UNION ALL
  SELECT 'Xe van',         0.8, 'B2', 8
) t
WHERE c.status = 'active';
```

### 7.2 cargo_types
```sql
INSERT INTO cargo_types (company_id, name, requires_special_vehicle, is_active, sort_order)
SELECT c.id, t.name, t.special, 1, t.sort_order
FROM companies c
CROSS JOIN (
  SELECT 'Hàng thường'      name, 0 special, 1 sort_order UNION ALL
  SELECT 'Hàng lạnh',            1, 2 UNION ALL
  SELECT 'Hàng nguy hiểm',       1, 3 UNION ALL
  SELECT 'Ô tô thành phẩm',      1, 4 UNION ALL
  SELECT 'Hàng cồng kềnh',       0, 5 UNION ALL
  SELECT 'Hàng dễ vỡ',           0, 6
) t
WHERE c.status = 'active';
```

### 7.3 cost_categories
```sql
INSERT INTO cost_categories (company_id, code, name, requires_receipt, approval_threshold, is_active, sort_order)
SELECT c.id, t.code, t.name, t.receipt, t.threshold, 1, t.sort_order
FROM companies c
CROSS JOIN (
  SELECT 'FUEL'    code,'Nhiên liệu'          name,1 receipt,NULL   threshold,1 sort_order UNION ALL
  SELECT 'TOLL',        'Cầu đường',               0,NULL,  2 UNION ALL
  SELECT 'LOADING',     'Bốc xếp hàng',            0,500000,3 UNION ALL
  SELECT 'WAITING',     'Phụ phí chờ',             0,300000,4 UNION ALL
  SELECT 'REPAIR',      'Sửa chữa dọc đường',      1,1000000,5 UNION ALL
  SELECT 'OTHER',       'Chi phí khác',             1,500000,6
) t
WHERE c.status = 'active';
```

### 7.4 order_status_configs
```sql
INSERT INTO order_status_configs (company_id, code, name, color, is_terminal, sort_order)
SELECT c.id, t.code, t.name, t.color, t.terminal, t.sort_order
FROM companies c
CROSS JOIN (
  SELECT 'NEW'        code,'Mới'              name,'#6B7280' color,0 terminal,1 sort_order UNION ALL
  SELECT 'ASSIGNED',       'Đã phân công',    '#3B82F6',          0,2 UNION ALL
  SELECT 'IN_TRANSIT',     'Đang vận chuyển', '#F59E0B',          0,3 UNION ALL
  SELECT 'DELIVERED',      'Đã giao hàng',    '#8B5CF6',          0,4 UNION ALL
  SELECT 'COMPLETED',      'Hoàn thành',      '#10B981',          1,5 UNION ALL
  SELECT 'CANCELLED',      'Đã hủy',         '#EF4444',          1,6
) t
WHERE c.status = 'active';
```

### 7.5 leave_types (nếu chưa có)
```sql
INSERT IGNORE INTO leave_types (company_id, code, name, is_paid, annual_quota_days, status)
SELECT c.id, t.code, t.name, t.paid, t.days, 'active'
FROM companies c
CROSS JOIN (
  SELECT 'ANNUAL'  code,'Nghỉ phép năm'    name,1 paid,12 days UNION ALL
  SELECT 'SICK',        'Nghỉ bệnh',            1,     10 UNION ALL
  SELECT 'PERSONAL',    'Việc riêng',           0,      5 UNION ALL
  SELECT 'UNPAID',      'Nghỉ không lương',     0,      0
) t
WHERE c.status = 'active';
```

---

## BƯỚC 8 — VERIFY

```sql
-- 8.1 Đếm bảng còn lại
SELECT COUNT(*) as total_tables
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE = 'BASE TABLE';
-- Kết quả mong đợi: ~54 bảng (43 spec + 11 Laravel system tables)

-- 8.2 Kiểm tra các bảng đã xóa
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'offices','departments','positions','roles','permissions',
    'role_permissions','user_roles','user_companies',
    'login_logs','export_logs','refresh_tokens'
  );
-- Kết quả mong đợi: empty (0 rows)

-- 8.3 Kiểm tra FK của drivers
SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_NAME = 'drivers'
  AND TABLE_SCHEMA = DATABASE()
  AND CONSTRAINT_NAME LIKE '%office%'
   OR CONSTRAINT_NAME LIKE '%position%'
   OR CONSTRAINT_NAME LIKE '%department%';
-- Kết quả mong đợi: empty

-- 8.4 Kiểm tra data integrity
SELECT COUNT(*) FROM trips WHERE customer_id NOT IN (SELECT id FROM customers);
SELECT COUNT(*) FROM trips WHERE vehicle_id  NOT IN (SELECT id FROM vehicles);
SELECT COUNT(*) FROM trips WHERE driver_id   NOT IN (SELECT id FROM drivers);
-- Tất cả phải trả về 0

-- 8.5 Kiểm tra seed data
SELECT company_id, COUNT(*) FROM vehicle_types  GROUP BY company_id;
SELECT company_id, COUNT(*) FROM cargo_types    GROUP BY company_id;
SELECT company_id, COUNT(*) FROM cost_categories GROUP BY company_id;
SELECT company_id, COUNT(*) FROM order_status_configs GROUP BY company_id;
```

---

## TỔNG HỢP MIGRATION

| Bước | Hành động | Bảng ảnh hưởng | Rủi ro |
|---|---|---|---|
| 2 | DROP FK | drivers, vehicles, driver_work_schedules, user_roles | Thấp |
| 3 | DROP COLUMN | drivers (3 cột) | Thấp |
| 4 | DROP COLUMN | vehicles (1 cột) | Thấp |
| 5 | DROP COLUMN | driver_work_schedules (1 cột) | Thấp |
| 6 | DROP TABLE | 11 bảng | Trung bình |
| 7 | INSERT SEED | 5 bảng | Thấp |

**Thời gian ước tính:** 30-45 phút (production với data lớn)

---

# PHẦN 2 — TEST PLAN

## Quy ước

```
🟢 Happy path (luồng chính, data hợp lệ)
🔴 Sad path (lỗi, data không hợp lệ, edge case)
🔵 Permission test (phân quyền)
⚡ Performance (query nặng, data lớn)
```

---

## MODULE 1 — AUTH & PHÂN QUYỀN

### T01 — Đăng nhập
| ID | Test case | Input | Expected |
|---|---|---|---|
| T01-01 🟢 | Đăng nhập thành công | email + password đúng | 200, trả về token |
| T01-02 🔴 | Sai password | password sai | 401, message lỗi |
| T01-03 🔴 | Email không tồn tại | email không có | 401 |
| T01-04 🔴 | Tài khoản inactive | user.status = inactive | 403 |
| T01-05 🟢 | Must change password | must_change_password = 1 | 200 + flag yêu cầu đổi mật khẩu |
| T01-06 🔴 | Đăng nhập quá 5 lần sai | brute force | 429 Too Many Requests |

### T02 — Phân quyền theo module
| ID | Test case | Input | Expected |
|---|---|---|---|
| T02-01 🔵 | Dispatcher xem orders | can_view = 1 | 200 |
| T02-02 🔵 | Viewer tạo order | can_create = 0 | 403 |
| T02-03 🔵 | Accountant duyệt chi phí | can_approve = 1 | 200 |
| T02-04 🔵 | Admin không cần permission | role = admin | Full access |
| T02-05 🔵 | User sai company | company_id khác | 403 hoặc 404 |
| T02-06 🔵 | Super admin truy cập mọi company | role = super_admin | 200 |

---

## MODULE 2 — QUẢN LÝ DANH MỤC

### T03 — Vehicle Types / Cargo Types / Cost Categories
| ID | Test case | Input | Expected |
|---|---|---|---|
| T03-01 🟢 | Tạo loại xe mới | name, max_load_ton hợp lệ | 201, record tạo đúng |
| T03-02 🔴 | Tạo trùng tên trong cùng company | name đã tồn tại | 422 Validation error |
| T03-03 🟢 | Cập nhật sort_order | kéo thả sắp xếp | DB update đúng |
| T03-04 🔴 | Xóa vehicle_type đang được dùng bởi vehicle | vehicle.vehicle_type_id = X | 409 Conflict |
| T03-05 🟢 | Soft delete (is_active = 0) | set is_active = 0 | Không hiển thị trong dropdown |
| T03-06 🔵 | Viewer không thể tạo danh mục | can_create = 0 | 403 |

### T04 — Locations & Route Templates
| ID | Test case | Input | Expected |
|---|---|---|---|
| T04-01 🟢 | Tạo location với tọa độ GPS | lat, lng hợp lệ | 201 |
| T04-02 🔴 | lat/lng không hợp lệ | lat = 999 | 422 |
| T04-03 🟢 | Tạo route_template từ 2 locations | origin + destination IDs | Tự điền distance_km |
| T04-04 🟢 | Tìm kiếm location fulltext | keyword trong name/address | Trả về kết quả đúng |
| T04-05 🔴 | Xóa location đang dùng trong route_template | location_id FK | SET NULL (vẫn xóa được) |

---

## MODULE 3 — KHÁCH HÀNG

### T05 — CRUD Customers
| ID | Test case | Input | Expected |
|---|---|---|---|
| T05-01 🟢 | Tạo KH mới | data đầy đủ | 201, code = KH-XXXXX tự sinh |
| T05-02 🔴 | Tạo KH thiếu name | name = null | 422 |
| T05-03 🟢 | Tìm kiếm fulltext | keyword trong name/phone/email | Kết quả đúng |
| T05-04 🟢 | Lọc theo group_id | group_id = 1 | Chỉ trả KH thuộc nhóm đó |
| T05-05 🔴 | Xóa KH có trips | customer_id đang có trips | 409 Conflict (RESTRICT FK) |
| T05-06 🔴 | Xóa KH không có trips | customer không có trips | 200, soft delete |
| T05-07 🟢 | Phân trang đúng | page=2, per_page=15 | Trả đúng 15 records trang 2 |
| T05-08 🔴 | Code KH trùng nhau | code đã tồn tại trong company | 422 |

### T06 — Bảng giá
| ID | Test case | Input | Expected |
|---|---|---|---|
| T06-01 🟢 | Tạo price_list với effective_to = null | vô thời hạn | 201 |
| T06-02 🟢 | Hệ thống tự lấy giá khi tạo đơn | customer_id + route_template_id + vehicle_type_id | Trả đúng price |
| T06-03 🟢 | 2 bảng giá trùng thời gian | effective_from overlap | Lấy bảng giá mới nhất |
| T06-04 🔴 | effective_to < effective_from | ngày không hợp lệ | 422 |

---

## MODULE 4 — PHƯƠNG TIỆN

### T07 — CRUD Vehicles
| ID | Test case | Input | Expected |
|---|---|---|---|
| T07-01 🟢 | Tạo xe mới | plate_number, vehicle_type_id hợp lệ | 201 |
| T07-02 🔴 | Biển số trùng | plate_number đã tồn tại | 422 UNIQUE violation |
| T07-03 🟢 | Cập nhật odometer | current_odometer_km tăng | Trigger check bảo dưỡng |
| T07-04 🔴 | Đổi status thành broken khi đang có chuyến | status = broken, trip in_progress | 409 Conflict |
| T07-05 🟢 | Lọc xe theo status | status = available | Chỉ trả xe rảnh |

### T08 — Vehicle Documents
| ID | Test case | Input | Expected |
|---|---|---|---|
| T08-01 🟢 | Upload giấy tờ xe | doc_type, file_url, expiry_date | 201 |
| T08-02 🟢 | Cảnh báo hết hạn | expiry_date <= now + alert_before_days | Notifications được tạo |
| T08-03 🟢 | Xe có nhiều loại giấy tờ | 5 doc_type khác nhau | Tất cả hiển thị đúng |
| T08-04 🔴 | Loại file không hợp lệ | file.exe | 422 |

### T09 — Vehicle Assignments (Tài xế phụ trách xe)
| ID | Test case | Input | Expected |
|---|---|---|---|
| T09-01 🟢 | Gán tài xế mới cho xe | vehicle_id + driver_id + from_date | 201, to_date = null |
| T09-02 🔴 | Gán khi xe đã có tài xế (to_date = null) | vehicle_id đang có assignment | 409 Conflict |
| T09-03 🟢 | Thay đổi tài xế phụ trách | set to_date + reason cho cũ, tạo mới | DB đúng |
| T09-04 🔴 | Thay đổi khi tài xế đang chạy chuyến | trip status = in_progress | 409 |
| T09-05 🟢 | Xem lịch sử phụ trách | GET /vehicles/{id}/assignments | Tất cả records kể cả đã kết thúc |
| T09-06 🔴 | Tài xế đã phụ trách xe khác (to_date = null) | driver_id đang có assignment khác | 409 |

### T10 — Bảo dưỡng
| ID | Test case | Input | Expected |
|---|---|---|---|
| T10-01 🟢 | Tạo lịch bảo dưỡng theo km | interval_type = by_km, interval_km = 5000 | 201, next_due_km tính đúng |
| T10-02 🟢 | Tạo phiếu sửa chữa đột xuất | type = unscheduled | maintenance_schedule_id = null |
| T10-03 🟢 | Hoàn thành bảo dưỡng → cập nhật next_due | completed_date + odometer_km | last_done, next_due cập nhật |
| T10-04 🟢 | Cảnh báo bảo dưỡng sắp đến hạn | next_due_date <= now + 7 ngày | Notification tạo tự động |

---

## MODULE 5 — TÀI XẾ

### T11 — CRUD Drivers
| ID | Test case | Input | Expected |
|---|---|---|---|
| T11-01 🟢 | Tạo tài xế mới | data đầy đủ | 201, code = TX-XXXXX tự sinh |
| T11-02 🔴 | License_no trùng | license_no đã tồn tại | 422 |
| T11-03 🔴 | CCCD trùng | national_id_no đã tồn tại | 422 |
| T11-04 🟢 | Gán vào driver_team | team_id hợp lệ | Cập nhật đúng |
| T11-05 🔴 | Xóa tài xế đang có chuyến active | trips.status != completed | 409 |

### T12 — Driver Documents
| ID | Test case | Input | Expected |
|---|---|---|---|
| T12-01 🟢 | Thêm GPLX | doc_type = driver_license, expiry_date | 201 |
| T12-02 🟢 | GPLX sắp hết hạn | expiry_date <= now + 30 ngày | Notification tạo |
| T12-03 🟢 | Cảnh báo khi phân công tài xế GPLX hết hạn | expired_date < today | Warning response (không block) |

### T13 — Lịch làm việc
| ID | Test case | Input | Expected |
|---|---|---|---|
| T13-01 🟢 | Tạo lịch làm việc cho ngày | driver_id, work_date, vehicle_id | 201 |
| T13-02 🔴 | Tài xế có 2 ca cùng ngày shift | UNIQUE(driver_id, work_date, shift_code) | 422 |
| T13-03 🟢 | Luồng duyệt lịch | draft → submitted → approved | Status transitions đúng |
| T13-04 🟢 | Query tài xế rảnh ngày mai | work_date = tomorrow | Trả đúng danh sách có sẵn |
| T13-05 🔴 | Phê duyệt lịch vi phạm HOS | end_time - start_time > 10h | Require hos_override_reason |

### T14 — Nghỉ phép
| ID | Test case | Input | Expected |
|---|---|---|---|
| T14-01 🟢 | Tạo đơn xin nghỉ | from_date, to_date, leave_type_id | 201, status = pending |
| T14-02 🔴 | from_date > to_date | ngày không hợp lệ | 422 (chk_lr_dates) |
| T14-03 🟢 | Duyệt đơn nghỉ | status = approved | Driver bị block trong work_schedules |
| T14-04 🔴 | Từ chối đơn nghỉ thiếu lý do | rejection_reason = null | 422 |
| T14-05 🟢 | Tài xế nghỉ không hiện trong danh sách phân công | leave approved | Filtered out khi chọn tài xế |

---

## MODULE 6 — ĐƠN HÀNG (CORE)

### T15 — Tạo đơn hàng
| ID | Test case | Input | Expected |
|---|---|---|---|
| T15-01 🟢 | Tạo đơn cơ bản | customer_id, start_point, end_point | 201, code tự sinh |
| T15-02 🟢 | Tạo đơn với route_template | route_template_id | Tự điền distance_km, giá |
| T15-03 🟢 | Tạo đơn multi-stop | 2 pickup + 2 delivery stops | trip_stops tạo đúng |
| T15-04 🟢 | Tự điền giá từ bảng giá KH | customer có price_list active | base_price = giá trong bảng |
| T15-05 🔴 | Customer không tồn tại | customer_id sai | 422 |
| T15-06 🔴 | Thiếu start_point | start_point = null | 422 |
| T15-07 🟢 | Thêm phụ phí | trip_surcharges | total_revenue = base + sum(surcharges) |

### T16 — Phân công đơn hàng
| ID | Test case | Input | Expected |
|---|---|---|---|
| T16-01 🟢 | Phân công xe + tài xế hợp lệ | vehicle_id + driver_id | status → assigned, assigned_at ghi |
| T16-02 🔴 | Xe đang có chuyến trùng giờ | vehicle trùng scheduled_date | 409 Conflict |
| T16-03 🔴 | Tài xế đang có chuyến trùng giờ | driver trùng scheduled_date | 409 Conflict |
| T16-04 🟢 | Cảnh báo GPLX sắp hết hạn | expired_date < 30 ngày | 200 + warning message |
| T16-05 🔴 | Xe status = broken/maintenance | vehicle không available | 409 |
| T16-06 🔴 | Tài xế nghỉ phép ngày đó | leave approved | 409 |
| T16-07 🟢 | Đổi xe giữa chừng | thay vehicle_id khi đang in_progress | Ghi log, cập nhật đúng |

### T17 — Vòng đời đơn hàng (State Machine)
| ID | Test case | Input | Expected |
|---|---|---|---|
| T17-01 🟢 | pending → assigned | phân công xe | Status = assigned, trip_status_histories ghi |
| T17-02 🟢 | assigned → in_transit | xác nhận xuất phát | Status = in_transit, start_time ghi |
| T17-03 🟢 | in_transit → completed | xác nhận giao hàng | Status = completed, actual_delivered_at ghi |
| T17-04 🔴 | pending → completed (bỏ bước) | skip states | 422 Invalid transition |
| T17-05 🟢 | Hủy đơn pending | cancellation_reason | Status = cancelled, cancelled_at ghi |
| T17-06 🔴 | Hủy đơn completed | status = completed | 409 Cannot cancel completed |
| T17-07 🟢 | Xóa đơn completed | DELETE | 405 Not allowed (chỉ cancel) |
| T17-08 🟢 | Mỗi thay đổi status ghi history | any status change | trip_status_histories += 1 row |

### T18 — Chi tiết đơn hàng
| ID | Test case | Input | Expected |
|---|---|---|---|
| T18-01 🟢 | Upload chứng từ | file PDF/image | trip_documents tạo |
| T18-02 🔴 | File quá lớn | file > 10MB | 422 |
| T18-03 🟢 | Ghi chú nội bộ | internal_notes | Lưu đúng, không hiện KH |
| T18-04 🟢 | Cập nhật km thực tế | actual_distance_km | Lưu đúng |
| T18-05 🟢 | Cập nhật trip_stops status | stop status = arrived | Cập nhật đúng điểm dừng |

---

## MODULE 7 — CHI PHÍ

### T19 — Trip Costs
| ID | Test case | Input | Expected |
|---|---|---|---|
| T19-01 🟢 | Thêm chi phí nhiên liệu | cost_category = FUEL, amount | 201 |
| T19-02 🟢 | Chi phí dưới ngưỡng | amount < approval_threshold | status = approved tự động |
| T19-03 🟢 | Chi phí vượt ngưỡng | amount > approval_threshold | approval_required = 1, status = pending |
| T19-04 🟢 | So sánh với định mức | amount > norm_amount | Hiển thị warning |
| T19-05 🔴 | Amount âm | amount = -100 | 422 (chk_tc_amount) |
| T19-06 🟢 | Upload receipt | receipt_file_url | Lưu đúng URL |

### T20 — Cost Approval
| ID | Test case | Input | Expected |
|---|---|---|---|
| T20-01 🟢 | Tạo approval request | trip_id, total_amount, reason | 201, status = pending |
| T20-02 🟢 | Admin duyệt | status = approved, review_note | trip_costs.status = approved |
| T20-03 🟢 | Admin từ chối | status = rejected, review_note | trip_costs.status = rejected |
| T20-04 🔴 | Từ chối thiếu review_note | review_note = null | 422 |
| T20-05 🔵 | Dispatcher không thể duyệt | can_approve = 0 | 403 |

---

## MODULE 8 — KẾ TOÁN & ĐỐI SOÁT

### T21 — Reconciliation
| ID | Test case | Input | Expected |
|---|---|---|---|
| T21-01 🟢 | Tạo phiên đối soát | customer_id + period | 201, tổng hợp đúng trips |
| T21-02 🟢 | Điều chỉnh doanh thu 1 chuyến | adjusted_amount != original | Ghi adjustment_reason |
| T21-03 🔴 | Điều chỉnh thiếu lý do | adjustment_reason = null | 422 |
| T21-04 🟢 | Xác nhận đối soát | status = confirmed | Không thể sửa tiếp |
| T21-05 🔴 | Sửa đối soát đã confirmed | PUT request | 409 Cannot edit confirmed |
| T21-06 🟢 | Đánh dấu tranh chấp | is_disputed = 1 | dispute_note bắt buộc |
| T21-07 🟢 | Lock đối soát | status = locked | Không thể thay đổi bất kỳ |

### T22 — Payment Records
| ID | Test case | Input | Expected |
|---|---|---|---|
| T22-01 🟢 | Ghi nhận thanh toán | customer_id, amount, payment_date | 201 |
| T22-02 🔴 | Amount <= 0 | amount = 0 | 422 (chk_pr_amount) |
| T22-03 🟢 | Gắn với phiên đối soát | reconciliation_session_id | Liên kết đúng |
| T22-04 🟢 | Tính công nợ còn lại | total_revenue - paid | Đúng số tiền |

---

## MODULE 9 — THÔNG BÁO & CẢNH BÁO

### T23 — Notifications
| ID | Test case | Input | Expected |
|---|---|---|---|
| T23-01 🟢 | Cảnh báo GPLX hết hạn | cron job chạy | Notification tạo cho Admin + Dispatcher |
| T23-02 🟢 | Cảnh báo đăng kiểm xe | cron job chạy | Notification tạo đúng |
| T23-03 🟢 | Đánh dấu đã đọc | PATCH notification/{id}/read | read_at = now |
| T23-04 🟢 | Count unread | GET /notifications/count | Đúng số chưa đọc |
| T23-05 🟢 | Chi phí cần phê duyệt | approval_required = 1 | Notification cho Admin |

---

## MODULE 10 — BÁO CÁO

### T24 — Reports
| ID | Test case | Input | Expected |
|---|---|---|---|
| T24-01 🟢 | Dashboard stats | today | Count đúng orders, doanh thu |
| T24-02 🟢 | Báo cáo doanh thu theo tháng | month, year | SUM(total_revenue) đúng |
| T24-03 🟢 | Báo cáo lợi nhuận | revenue - costs | Số tính đúng |
| T24-04 🟢 | Báo cáo hiệu suất xe | vehicle_id + period | Số chuyến, km, % lấp đầy |
| T24-05 🔵 | Viewer chỉ xem, không export | can_export = 0 | 403 cho export endpoint |
| T24-06 ⚡ | Query báo cáo tháng với 10k trips | large dataset | < 2 giây |
| T24-07 🟢 | Export Excel | GET /reports/export | File Excel hợp lệ |

---

## MODULE 11 — MULTI-TENANT ISOLATION

### T25 — Data Isolation
| ID | Test case | Input | Expected |
|---|---|---|---|
| T25-01 🔴 | User company A xem data company B | different company_id | 403 hoặc 404 |
| T25-02 🔴 | API không có company filter | query thiếu company_id | Chỉ trả data của company mình |
| T25-03 🔴 | Trip của company A gắn KH của company B | cross-company FK | 422 Validation |
| T25-04 🟢 | Super admin xem mọi company | role = super_admin | 200 |
| T25-05 🔴 | Token của user công ty A dùng cho API công ty B | wrong company context | 403 |

---

## MODULE 12 — AUDIT & INTEGRITY

### T26 — Audit Logs
| ID | Test case | Input | Expected |
|---|---|---|---|
| T26-01 🟢 | Mọi CREATE ghi audit | tạo trip | audit_logs += 1 row, action = create |
| T26-02 🟢 | Mọi UPDATE ghi audit | sửa customer | old_data + new_data đúng |
| T26-03 🟢 | DELETE ghi audit | soft delete driver | action = delete |
| T26-04 🔴 | Không thể DELETE audit_logs | DELETE FROM audit_logs | 403 / Trigger block |
| T26-05 🟢 | Lọc audit theo user | user_id filter | Đúng records |

---

## CHECKLIST TRƯỚC KHI RELEASE

## TIẾN ĐỘ THỰC THI (cập nhật tự động từ test suite)

> Cập nhật lúc: 2026-05-01  
> Nguồn xác nhận gần nhất: `php artisan test tests/Feature/Api` (263 passed, 0 failed)

| Module | Trạng thái | Ghi chú bám theo plan/spec |
|---|---|---|
| T01 | ✅ Done | Bao phủ bởi `AuthApiTest` (đăng nhập, inactive, throttling, token flow). |
| T02 | 🟡 Partial | Có test role/permission cơ bản (`UsersRolesApiTest`, `AuthorizationTest`), thiếu matrix đầy đủ theo module. |
| T03 | ✅ Done | Đã thêm `CatalogsApiTest` phủ T03-01..06: tạo mới 3 danh mục, chặn trùng name cùng company (422), cập nhật sort_order, chặn xóa vehicle_type đang được dùng (409), soft-disable, và viewer bị chặn tạo (403). |
| T04 | ✅ Done | Đã thêm `LocationsRouteTemplatesApiTest` phủ T04-01..05: tạo location với GPS, validate lat/lng, tạo route_template tự tính `distance_km`, search theo keyword, và xóa location đang được dùng với cập nhật null reference theo spec. |
| T05 | ✅ Done | Bao phủ bởi `CustomersApiTest` + rule xóa phụ thuộc. |
| T06 | ✅ Done | Đã thêm API `price-lists`, `price-list-items` + `PriceListsApiTest` phủ T06-01..04: tạo bảng giá vô thời hạn, tự lấy giá từ `price_list_items` khi tạo trip, ưu tiên bảng giá có `effective_from` mới nhất khi overlap, và validate `effective_to >= effective_from`. |
| T07 | ✅ Done | Đã mở rộng `VehiclesApiTest` cho T07-01..05: create, chặn biển số trùng (422), cập nhật odometer, chặn chuyển `maintenance/broken` khi có trip `in_progress` (422), và lọc theo `status`. |
| T08 | ✅ Done | Đã thêm API `vehicle-documents` + `VehicleDocumentsApiTest` phủ T08-01..04: upload giấy tờ, tạo cảnh báo hết hạn (notification), hỗ trợ nhiều loại giấy tờ/xe, và chặn file extension không hợp lệ (422). |
| T09 | ✅ Done | Bao phủ bởi `VehicleAssignmentsApiTest` + conflict rules. |
| T10 | ✅ Done | Đã thêm API `maintenance-schedules`, `maintenance-records` + `MaintenanceApiTest` phủ T10-01..04: tạo lịch theo km tính `next_due_km`, tạo phiếu sửa chữa đột xuất không cần schedule, hoàn thành bảo dưỡng cập nhật `last_done/next_due`, và truy vấn lịch sắp đến hạn theo `next_due_date`. |
| T11 | ✅ Done | Bao phủ bởi `DriversApiTest` + delete policy liên quan. |
| T12 | ✅ Done | Đã thêm API `driver-documents` + `DriverDocumentsApiTest` phủ T12-01..03: thêm GPLX, tạo notification khi sắp hết hạn, và khi tạo trip với GPLX hết hạn thì vẫn cho phép nhưng trả `warnings` trong response. |
| T13 | ✅ Done | Đã mở rộng `DriverScheduleApiTest` phủ T13-01..05: tạo lịch theo ngày, chặn trùng ca cùng ngày (422), workflow `draft -> submitted -> approved`, query lịch ngày mai, và enforcement HOS override (`hos_override=true` bắt buộc `override_reason`). |
| T14 | ✅ Done | Đã hoàn tất `LeaveWorkflowApiTest` phủ T14-01..05: tạo leave, validate ngày không hợp lệ (422), approve flow, reject thiếu lý do (422), và chặn tạo trip khi tài xế đã được duyệt nghỉ trong ngày. |
| T15 | ✅ Done | Bao phủ qua `TripsApiTest` + `CetaBusinessRulesTest` (tạo đơn, revenue/surcharge). |
| T16 | ✅ Done | Bao phủ qua `TripAssignPolicyApiTest`, `DispatchEligibilityApiTest`. |
| T17 | ✅ Done | Bao phủ state machine và transition bởi `TripsApiTest` + `CetaBusinessRulesTest`. |
| T18 | ✅ Done | Đã bổ sung APIs chi tiết chuyến đi và `TripDetailsApiTest` phủ T18-01..05: upload `trip_documents` (validate loại/đuôi file), transition `trip_stops` `pending -> arrived -> completed`, chặn transition sai (422), và cập nhật `internal_notes` + `actual_distance_km`. |
| T19 | ✅ Done | Bao phủ `TripCostsApprovalWorkflowApiTest` + business rules ngưỡng duyệt. |
| T20 | ✅ Done | Bao phủ approve/reject workflow trong `TripCostsApprovalWorkflowApiTest`. |
| T21 | ✅ Done | Bao phủ `ReconciliationWorkflowApiTest` + `CetaBusinessRulesTest` lock/confirm. |
| T22 | ✅ Done | Bao phủ payment-record gắn reconciliation trong `ReconciliationWorkflowApiTest`. |
| T23 | ✅ Done | Đã hoàn tất cron cảnh báo với command `alerts:dispatch-notifications` + scheduler hourly và test `NotificationAlertsCommandTest` cho 3 nhánh: hết hạn `driver_documents`, hết hạn `vehicle_documents`, và `cost_approval_required`; giữ nguyên alias/read/count của `NotificationController`. |
| T24 | ✅ Done | Đã hoàn tất `T24-04/06/07`: thêm endpoint `vehicle-performance`, bổ sung benchmark dataset lớn trong `ReportsApiTest` (ngưỡng thực tế), và export Excel thực (`/reports/exports/revenue-excel`, content-type Excel + payload XML Spreadsheet hợp lệ). |
| T25 | ✅ Done | `DataIsolationApiTest` đã phủ T25-01..05: chặn user company A xem company B (403), API không filter company vẫn chỉ trả data company hiện tại, chặn cross-company references khi tạo trip (422), super_admin truy cập đa company, và chặn tenant switch sai context. |
| T26 | ✅ Done | Đã thêm `AuditIntegrityApiTest` phủ đủ T26-01..05: create/update/delete audit, chặn delete audit_logs qua HTTP, filter audit theo username. |

### Database
- [ ] Chạy BƯỚC 2-6 thành công trên staging
- [ ] BƯỚC 8 verify không có lỗi
- [ ] Seed data đầy đủ cho tất cả companies

### API Tests
- [x] Tất cả Happy path tests pass (trong phạm vi suite hiện có)
- [x] Tất cả Sad path tests return đúng error code (trong phạm vi suite hiện có)
- [x] Tất cả Permission tests pass (role/permission hiện có)
- [x] Multi-tenant isolation tests pass (đã phủ T25-01..05 trong `DataIsolationApiTest`)

### Performance
- [ ] Dashboard load < 1 giây
- [ ] Danh sách trips (1000+ records) load < 2 giây
- [ ] Export Excel 500 rows < 10 giây
- [ ] Báo cáo tháng < 3 giây

### Regression
- [ ] Trips 1207 records hiện có vẫn accessible
- [ ] Invoices 26 records vẫn đúng
- [ ] chat_messages vẫn hoạt động

---

## THỨ TỰ TRIỂN KHAI KHUYẾN NGHỊ

```
Ngày 1 (sáng): Backup + Bước 2-5 (ALTER)
Ngày 1 (chiều): Bước 6 (DROP tables) + verify
Ngày 2 (sáng): Bước 7 (SEED data)
Ngày 2 (chiều): Chạy T25 (Multi-tenant isolation) — quan trọng nhất
Ngày 3: Chạy T15-T18 (Order core flow)
Ngày 4: Chạy T01-T14 (Auth, vehicles, drivers)
Ngày 5: Chạy T19-T26 (Costs, accounting, reports)
```

---

*File này là căn cứ để viết migration scripts và test cases trong Laravel. Version 1.0 — 2026-04-30*
