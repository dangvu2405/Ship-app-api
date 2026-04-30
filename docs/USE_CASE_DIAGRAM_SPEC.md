# PHẦN 1 — DATABASE
 
## 1.1 Sơ đồ nhóm bảng
 
```
PLATFORM              DANH MỤC              KHÁCH HÀNG
──────────────        ──────────────        ──────────────
companies             vehicle_types         customer_groups
users                 cargo_types           customers
user_permissions      locations             price_lists
audit_logs            route_templates       price_list_items
                      cost_categories
                      order_status_configs
 
PHƯƠNG TIỆN           TÀI XẾ               LỊCH LÀM VIỆC
──────────────        ──────────────        ──────────────
vehicles              driver_teams          leave_types
vehicle_documents     drivers               leave_requests
vehicle_assignments   driver_documents      driver_work_schedules
spare_parts
maintenance_schedules
maintenance_records
 
ĐƠN HÀNG              CHI PHÍ              KẾ TOÁN
──────────────        ──────────────        ──────────────
trips                 trip_costs            reconciliation_sessions
trip_stops            cost_approval_        reconciliation_items
trip_surcharges         requests            payment_records
trip_documents
trip_status_histories
 
HÓA ĐƠN              THÔNG BÁO             CHAT / AI
──────────────        ──────────────        ──────────────
invoices              notifications         chat_messages
invoice_status_                             knowledge_articles
  histories                                 rag_index
                                            report_caches
```
 
---
 
## 1.2 Chi tiết từng bảng
 
### [P1] `companies` — Công ty / Tenant
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| code | VARCHAR(50) | NO | — | Mã công ty. UNIQUE |
| name | VARCHAR(255) | NO | — | Tên công ty |
| tax_code | VARCHAR(50) | YES | NULL | Mã số thuế |
| address | TEXT | YES | NULL | Địa chỉ |
| phone | VARCHAR(20) | YES | NULL | |
| email | VARCHAR(255) | YES | NULL | |
| status | ENUM | NO | active | active / inactive |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | Soft delete |
 
---
 
### [P2] `users` — Tài khoản người dùng
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| username | VARCHAR(255) | NO | — | UNIQUE |
| email | VARCHAR(255) | NO | — | UNIQUE |
| full_name | VARCHAR(200) | YES | NULL | Họ và tên |
| phone | VARCHAR(20) | YES | NULL | |
| password | VARCHAR(255) | NO | — | bcrypt |
| avatar_url | VARCHAR(255) | YES | NULL | |
| role | ENUM | NO | dispatcher | super_admin / admin / dispatcher / accountant / viewer |
| status | ENUM | NO | active | active / inactive |
| must_change_password | TINYINT(1) | NO | 0 | |
| last_login_at | TIMESTAMP | YES | NULL | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
---
 
### [P3] `user_permissions` — Phân quyền theo module
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | → companies |
| user_id | BIGINT FK | NO | — | → users |
| module | VARCHAR(50) | NO | — | orders / vehicles / drivers / accounting / reports / settings |
| can_view | TINYINT(1) | NO | 0 | |
| can_create | TINYINT(1) | NO | 0 | |
| can_edit | TINYINT(1) | NO | 0 | |
| can_delete | TINYINT(1) | NO | 0 | |
| can_approve | TINYINT(1) | NO | 0 | |
| can_export | TINYINT(1) | NO | 0 | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
 
**UNIQUE:** (company_id, user_id, module)
 
---
 
### [P4] `audit_logs` — Lịch sử thao tác
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| user_id | BIGINT FK | YES | NULL | → users (NULL = system) |
| company_id | BIGINT FK | YES | NULL | → companies |
| action | VARCHAR(100) | NO | — | create/update/delete/login/export/approve |
| table_name | VARCHAR(100) | NO | — | Tên bảng bị tác động |
| record_id | BIGINT | YES | NULL | ID bản ghi bị tác động |
| old_data | JSON | YES | NULL | Giá trị trước |
| new_data | JSON | YES | NULL | Giá trị sau |
| ip_address | VARCHAR(45) | YES | NULL | |
| user_agent | VARCHAR(512) | YES | NULL | |
| created_at | TIMESTAMP | YES | NULL | |
 
> Không có updated_at. Không UPDATE, không DELETE bảng này.
 
---
 
### [D1] `vehicle_types` — Loại phương tiện
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | → companies |
| name | VARCHAR(100) | NO | — | Xe tải 5T, Đầu kéo... |
| max_load_ton | DECIMAL(6,2) | YES | NULL | Tải trọng tối đa |
| volume_m3 | DECIMAL(8,2) | YES | NULL | Thể tích thùng |
| required_license_class | VARCHAR(10) | YES | NULL | B2/C/D/E/FC |
| description | TEXT | YES | NULL | |
| is_active | TINYINT(1) | NO | 1 | |
| sort_order | INT | NO | 0 | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
---
 
### [D2] `cargo_types` — Loại hàng hóa
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | → companies |
| name | VARCHAR(100) | NO | — | Hàng thường, Hàng lạnh... |
| requires_special_vehicle | TINYINT(1) | NO | 0 | Cần xe chuyên dụng |
| special_requirements | TEXT | YES | NULL | Yêu cầu đặc biệt |
| is_active | TINYINT(1) | NO | 1 | |
| sort_order | INT | NO | 0 | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
---
 
### [D3] `locations` — Điểm giao/nhận hàng
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | → companies |
| name | VARCHAR(200) | NO | — | Kho Bình Dương, Cảng Cát Lái... |
| address | TEXT | NO | — | Địa chỉ đầy đủ |
| province | VARCHAR(100) | YES | NULL | Tỉnh/Thành phố |
| district | VARCHAR(100) | YES | NULL | Quận/Huyện |
| lat | DECIMAL(10,8) | YES | NULL | Vĩ độ |
| lng | DECIMAL(11,8) | YES | NULL | Kinh độ |
| contact_name | VARCHAR(200) | YES | NULL | Người LH tại điểm |
| contact_phone | VARCHAR(20) | YES | NULL | |
| open_time | TIME | YES | NULL | Giờ mở cửa |
| close_time | TIME | YES | NULL | Giờ đóng cửa |
| notes | TEXT | YES | NULL | Cổng B, gặp anh Nam... |
| customer_id | BIGINT FK | YES | NULL | → customers (kho cố định) |
| is_active | TINYINT(1) | NO | 1 | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
---
 
### [D4] `route_templates` — Tuyến đường mẫu
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | → companies |
| name | VARCHAR(200) | NO | — | HCM → Bình Dương |
| origin_location_id | BIGINT FK | YES | NULL | → locations |
| destination_location_id | BIGINT FK | YES | NULL | → locations |
| distance_km | DECIMAL(8,2) | YES | NULL | |
| estimated_hours | DECIMAL(4,1) | YES | NULL | Giờ dự kiến |
| default_price | DECIMAL(15,2) | YES | NULL | Giá mặc định |
| fuel_norm_liter | DECIMAL(6,2) | YES | NULL | Định mức nhiên liệu |
| toll_norm | DECIMAL(12,2) | YES | NULL | Định mức cầu đường |
| notes | TEXT | YES | NULL | |
| is_active | TINYINT(1) | NO | 1 | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
---
 
### [D5] `cost_categories` — Loại chi phí
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | → companies |
| code | VARCHAR(50) | NO | — | FUEL/TOLL/LOADING/WAITING/REPAIR/OTHER |
| name | VARCHAR(100) | NO | — | Nhiên liệu, Cầu đường... |
| requires_receipt | TINYINT(1) | NO | 0 | Bắt buộc upload chứng từ |
| approval_threshold | DECIMAL(15,2) | YES | NULL | NULL = không cần duyệt |
| is_active | TINYINT(1) | NO | 1 | |
| sort_order | INT | NO | 0 | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
**UNIQUE:** (company_id, code)
 
---
 
### [D6] `order_status_configs` — Cấu hình trạng thái đơn hàng
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | → companies |
| code | VARCHAR(50) | NO | — | NEW/ASSIGNED/IN_TRANSIT/DELIVERED/COMPLETED/CANCELLED |
| name | VARCHAR(100) | NO | — | Tên hiển thị |
| color | VARCHAR(7) | YES | NULL | Màu hex: #3B82F6 |
| is_terminal | TINYINT(1) | NO | 0 | Trạng thái kết thúc |
| sort_order | INT | NO | 0 | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
 
**UNIQUE:** (company_id, code)
 
---
 
### [C1] `customer_groups` — Nhóm khách hàng
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | → companies |
| name | VARCHAR(100) | NO | — | VIP, Thường xuyên, Mới |
| description | TEXT | YES | NULL | |
| assigned_dispatcher_id | BIGINT FK | YES | NULL | → users |
| is_active | TINYINT(1) | NO | 1 | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
---
 
### [C2] `customers` — Khách hàng
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | → companies |
| code | VARCHAR(50) | NO | — | KH-00001. UNIQUE per company |
| type | ENUM | NO | individual | company / individual |
| company_name | VARCHAR(300) | YES | NULL | Tên công ty (khi type=company) |
| name | VARCHAR(255) | NO | — | Tên người liên hệ chính |
| full_name | VARCHAR(200) | YES | NULL | (alias cho name) |
| extra_contact_name | VARCHAR(200) | YES | NULL | Người LH thứ 2 |
| extra_contact_phone | VARCHAR(20) | YES | NULL | |
| tax_code | VARCHAR(50) | YES | NULL | Mã số thuế |
| phone | VARCHAR(20) | YES | NULL | |
| email | VARCHAR(255) | YES | NULL | |
| address | TEXT | YES | NULL | |
| group_id | BIGINT FK | YES | NULL | → customer_groups |
| assigned_dispatcher_id | BIGINT FK | YES | NULL | → users |
| credit_limit | DECIMAL(15,2) | YES | NULL | NULL = không giới hạn |
| payment_terms_days | INT UNSIGNED | YES | NULL | Số ngày được nợ |
| contract_file_url | VARCHAR(500) | YES | NULL | |
| contract_start_date | DATE | YES | NULL | |
| contract_end_date | DATE | YES | NULL | |
| notes | TEXT | YES | NULL | |
| is_active | TINYINT(1) | NO | 1 | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
---
 
### [C3] `price_lists` — Bảng giá theo KH
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | → companies |
| customer_id | BIGINT FK | NO | — | → customers |
| name | VARCHAR(200) | NO | — | Bảng giá Q1/2026 |
| effective_from | DATE | NO | — | Ngày có hiệu lực |
| effective_to | DATE | YES | NULL | NULL = vô thời hạn |
| is_active | TINYINT(1) | NO | 1 | |
| notes | TEXT | YES | NULL | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
---
 
### [C4] `price_list_items` — Chi tiết mức giá
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | → companies |
| price_list_id | BIGINT FK | NO | — | → price_lists |
| route_template_id | BIGINT FK | YES | NULL | → route_templates (NULL = mọi tuyến) |
| vehicle_type_id | BIGINT FK | YES | NULL | → vehicle_types (NULL = mọi loại xe) |
| cargo_type_id | BIGINT FK | YES | NULL | → cargo_types (NULL = mọi loại hàng) |
| price | DECIMAL(15,2) | NO | — | Đơn giá |
| price_unit | ENUM | NO | per_trip | per_trip / per_km / per_ton |
| notes | TEXT | YES | NULL | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
 
---
 
### [V1] `vehicles` — Xe
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | → companies |
| plate_number | VARCHAR(20) | NO | — | Biển số. UNIQUE |
| vehicle_type_id | BIGINT FK | YES | NULL | → vehicle_types |
| type | ENUM | NO | truck | truck/van/car/motorcycle (legacy) |
| brand | VARCHAR(100) | YES | NULL | |
| model | VARCHAR(100) | YES | NULL | |
| year | YEAR | YES | NULL | Năm sản xuất |
| capacity | INT | YES | NULL | (legacy) |
| max_load_ton | DECIMAL(6,2) | YES | NULL | Tải trọng tối đa |
| volume_m3 | DECIMAL(8,2) | YES | NULL | Thể tích thùng |
| fuel_type | ENUM | YES | NULL | gasoline/diesel/electric/hybrid |
| fuel_consumption | DECIMAL(5,2) | YES | NULL | Lít/100km |
| current_odometer_km | DECIMAL(10,2) | YES | NULL | Km đồng hồ |
| status | ENUM | NO | active | active/maintenance/inactive/broken |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
---
 
### [V2] `vehicle_documents` — Giấy tờ & ảnh xe
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | → companies |
| vehicle_id | BIGINT FK | NO | — | → vehicles |
| doc_type | ENUM | NO | — | registration/inspection/liability_insurance/vehicle_insurance/badge/photo/other |
| doc_name | VARCHAR(200) | NO | — | Đăng kiểm, Bảo hiểm TNDS... |
| doc_number | VARCHAR(100) | YES | NULL | Số giấy tờ |
| issued_date | DATE | YES | NULL | Ngày cấp |
| expiry_date | DATE | YES | NULL | Ngày hết hạn |
| issuer | VARCHAR(200) | YES | NULL | Cơ quan cấp |
| file_url | VARCHAR(500) | YES | NULL | URL file scan |
| alert_before_days | INT UNSIGNED | NO | 30 | Cảnh báo trước X ngày |
| notes | TEXT | YES | NULL | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
---
 
### [V3] `vehicle_assignments` — Lịch sử tài xế phụ trách xe
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| vehicle_id | BIGINT FK | NO | — | → vehicles |
| driver_id | BIGINT FK | NO | — | → drivers |
| company_id | BIGINT FK | NO | — | → companies |
| from_date | DATE | NO | — | Ngày bắt đầu phụ trách |
| to_date | DATE | YES | NULL | NULL = đang còn hiệu lực |
| release_reason | TEXT | YES | NULL | Lý do kết thúc |
| notes | TEXT | YES | NULL | |
| created_by | BIGINT FK | YES | NULL | → users |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
**Ràng buộc:** Tại mỗi thời điểm, 1 xe chỉ có 1 bản ghi `to_date IS NULL`
 
---
 
### [V4] `spare_parts` — Danh mục phụ tùng
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | → companies |
| name | VARCHAR(200) | NO | — | Dầu động cơ, Lốp xe... |
| unit | VARCHAR(50) | YES | NULL | lít, cái, bộ |
| notes | TEXT | YES | NULL | |
| is_active | TINYINT(1) | NO | 1 | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
 
---
 
### [V5] `maintenance_schedules` — Lịch bảo dưỡng định kỳ
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | → companies |
| vehicle_id | BIGINT FK | NO | — | → vehicles |
| spare_part_id | BIGINT FK | YES | NULL | → spare_parts |
| task_name | VARCHAR(200) | NO | — | Thay dầu động cơ... |
| interval_type | ENUM | NO | — | by_km / by_days / both |
| interval_km | INT UNSIGNED | YES | NULL | |
| interval_days | INT UNSIGNED | YES | NULL | |
| last_done_km | DECIMAL(10,2) | YES | NULL | |
| last_done_date | DATE | YES | NULL | |
| next_due_km | DECIMAL(10,2) | YES | NULL | |
| next_due_date | DATE | YES | NULL | |
| alert_before_km | INT UNSIGNED | YES | NULL | |
| alert_before_days | INT UNSIGNED | NO | 7 | |
| estimated_cost | DECIMAL(12,2) | YES | NULL | |
| notes | TEXT | YES | NULL | |
| is_active | TINYINT(1) | NO | 1 | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
---
 
### [V6] `maintenance_records` — Phiếu sửa chữa thực tế
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | → companies |
| vehicle_id | BIGINT FK | NO | — | → vehicles |
| maintenance_schedule_id | BIGINT FK | YES | NULL | NULL = đột xuất |
| type | ENUM | NO | — | scheduled / unscheduled |
| title | VARCHAR(200) | NO | — | |
| description | TEXT | YES | NULL | |
| odometer_km | DECIMAL(10,2) | YES | NULL | Km lúc đưa vào sửa |
| started_date | DATE | NO | — | |
| completed_date | DATE | YES | NULL | |
| garage_name | VARCHAR(200) | YES | NULL | |
| total_cost | DECIMAL(15,2) | YES | NULL | |
| invoice_number | VARCHAR(100) | YES | NULL | |
| file_url | VARCHAR(500) | YES | NULL | |
| status | ENUM | NO | open | open / in_progress / completed |
| notes | TEXT | YES | NULL | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
---
 
### [DR1] `driver_teams` — Đội tài xế
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | → companies |
| name | VARCHAR(100) | NO | — | Đội Bắc, Đội Nam... |
| manager_id | BIGINT FK | YES | NULL | → users |
| description | TEXT | YES | NULL | |
| is_active | TINYINT(1) | NO | 1 | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
---
 
### [DR2] `drivers` — Tài xế
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| user_id | BIGINT FK | YES | NULL | → users (tài khoản đăng nhập) |
| code | VARCHAR(50) | NO | — | TX-00001. UNIQUE |
| company_id | BIGINT FK | NO | — | → companies |
| team_id | BIGINT FK | YES | NULL | → driver_teams |
| name | VARCHAR(255) | NO | — | Họ và tên |
| email | VARCHAR(255) | YES | NULL | |
| phone | VARCHAR(20) | YES | NULL | |
| dob | DATE | YES | NULL | Ngày sinh |
| gender | ENUM | YES | NULL | male/female/other |
| address | TEXT | YES | NULL | |
| avatar_url | VARCHAR(255) | YES | NULL | |
| national_id_no | VARCHAR(30) | YES | NULL | CCCD/CMND |
| national_id_issue_date | DATE | YES | NULL | |
| national_id_issue_place | VARCHAR(255) | YES | NULL | |
| social_insurance_no | VARCHAR(30) | YES | NULL | |
| license_no | VARCHAR(50) | NO | — | Số GPLX |
| license_class | VARCHAR(20) | YES | NULL | B2/C/D/E/FC |
| expired_date | DATE | YES | NULL | Ngày hết hạn GPLX |
| license_alert_days | SMALLINT UNSIGNED | NO | 30 | Cảnh báo trước X ngày |
| license_image_url | VARCHAR(255) | YES | NULL | |
| health_certificate_no | VARCHAR(30) | YES | NULL | |
| health_certificate_expired_date | DATE | YES | NULL | |
| available_status | ENUM | NO | available | available/busy/offline |
| status | ENUM | NO | active | active/inactive/resigned |
| join_date | DATE | YES | NULL | |
| resign_date | DATE | YES | NULL | |
| annual_leave_days | SMALLINT UNSIGNED | NO | 12 | |
| bank_name | VARCHAR(255) | YES | NULL | |
| bank_account_no | VARCHAR(50) | YES | NULL | |
| bank_account_name | VARCHAR(255) | YES | NULL | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
---
 
### [DR3] `driver_documents` — Giấy tờ tài xế
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | → companies |
| driver_id | BIGINT FK | NO | — | → drivers |
| doc_type | ENUM | NO | — | driver_license/international_license/health_certificate/skill_certificate/id_card/other |
| doc_name | VARCHAR(200) | NO | — | |
| doc_number | VARCHAR(100) | YES | NULL | |
| issued_date | DATE | YES | NULL | |
| expiry_date | DATE | YES | NULL | |
| issuer | VARCHAR(200) | YES | NULL | |
| file_url | VARCHAR(500) | YES | NULL | |
| alert_before_days | INT UNSIGNED | NO | 30 | |
| notes | TEXT | YES | NULL | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
---
 
### [L1] `leave_types` — Loại nghỉ phép *(EXISTS)*
Giữ nguyên. Đã có company_id, code, name, is_paid, annual_quota_days.
 
---
 
### [L2] `leave_requests` — Đơn xin nghỉ *(ALTER — thêm company_id)*
| Cột thêm | Kiểu | Null | Mô tả |
|---|---|---|---|
| company_id | BIGINT FK | YES | → companies (backfill từ drivers) |
 
---
 
### [L3] `driver_work_schedules` — Phân công xe-tài xế theo ngày *(EXISTS)*
Giữ nguyên. Đã có driver_id, vehicle_id, work_date, shift_code, start_time, end_time, status (draft/submitted/approved/locked).
 
---
 
### [O1] `trips` — Đơn hàng / Chuyến xe *(ALTER — thêm nhiều cột)*
| Cột gốc + bổ sung | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| code | VARCHAR(50) | NO | — | Mã đơn. UNIQUE |
| company_id | BIGINT FK | NO | — | → companies |
| customer_id | BIGINT FK | NO | — | → customers |
| contact_name | VARCHAR(200) | YES | NULL | **MỚI** Người LH |
| contact_phone | VARCHAR(20) | YES | NULL | **MỚI** |
| cargo_type_id | BIGINT FK | YES | NULL | **MỚI** → cargo_types |
| cargo_description | TEXT | YES | NULL | **MỚI** |
| cargo_quantity | DECIMAL(10,2) | YES | NULL | **MỚI** |
| cargo_unit | VARCHAR(50) | YES | NULL | **MỚI** tấn/kiện/thùng |
| cargo_weight_ton | DECIMAL(8,2) | YES | NULL | **MỚI** |
| cargo_notes | TEXT | YES | NULL | **MỚI** Dễ vỡ, hàng lạnh... |
| driver_id | BIGINT FK | NO | — | → drivers |
| vehicle_id | BIGINT FK | NO | — | → vehicles |
| dispatcher_id | BIGINT FK | YES | NULL | **MỚI** → users |
| assigned_at | DATETIME | YES | NULL | **MỚI** |
| route_template_id | BIGINT FK | YES | NULL | **MỚI** → route_templates |
| origin_location_id | BIGINT FK | YES | NULL | **MỚI** → locations |
| destination_location_id | BIGINT FK | YES | NULL | **MỚI** → locations |
| start_point | VARCHAR(255) | NO | — | Địa chỉ lấy hàng (text) |
| end_point | VARCHAR(255) | NO | — | Địa chỉ giao hàng (text) |
| received_date | DATE | YES | NULL | **MỚI** Ngày nhận đơn |
| scheduled_date | DATE | YES | NULL | **MỚI** Ngày vận chuyển dự kiến |
| scheduled_time_from | TIME | YES | NULL | **MỚI** |
| scheduled_time_to | TIME | YES | NULL | **MỚI** |
| distance_km | DECIMAL(10,2) | NO | 0 | Km dự kiến |
| actual_distance_km | DECIMAL(8,2) | YES | NULL | **MỚI** Km thực tế |
| start_time | DATETIME | YES | NULL | Giờ xuất phát thực tế |
| end_time | DATETIME | YES | NULL | Giờ kết thúc thực tế |
| actual_pickup_at | DATETIME | YES | NULL | **MỚI** Giờ lấy hàng thực tế |
| actual_delivered_at | DATETIME | YES | NULL | **MỚI** Giờ giao hàng thực tế |
| price | DECIMAL(15,2) | NO | 0 | Giá gốc (legacy) |
| base_price | DECIMAL(15,2) | NO | 0 | **MỚI** Đơn giá cước |
| surcharge_amount | DECIMAL(15,2) | NO | 0 | **MỚI** Tổng phụ phí |
| total_revenue | DECIMAL(15,2) | YES | NULL | **MỚI** = base_price + surcharge |
| payment_method | ENUM | YES | NULL | **MỚI** bank_transfer/cash/credit |
| payment_status | ENUM | NO | unpaid | **MỚI** unpaid/invoiced/paid |
| status | ENUM | NO | pending | pending/in_progress/completed/cancelled |
| cancellation_reason | TEXT | YES | NULL | **MỚI** |
| cancelled_at | DATETIME | YES | NULL | **MỚI** |
| cancelled_by | BIGINT FK | YES | NULL | **MỚI** → users |
| internal_notes | TEXT | YES | NULL | **MỚI** Ghi chú nội bộ |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
---
 
### [O2] `trip_stops` — Điểm dừng multi-stop
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | → companies |
| trip_id | BIGINT FK | NO | — | → trips |
| stop_type | ENUM | NO | — | pickup / delivery |
| sequence | SMALLINT UNSIGNED | NO | — | 1, 2, 3... |
| location_id | BIGINT FK | YES | NULL | → locations |
| address | TEXT | NO | — | |
| contact_name | VARCHAR(200) | YES | NULL | |
| contact_phone | VARCHAR(20) | YES | NULL | |
| scheduled_time | DATETIME | YES | NULL | |
| actual_time | DATETIME | YES | NULL | |
| status | ENUM | NO | pending | pending/arrived/completed |
| notes | TEXT | YES | NULL | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
 
**UNIQUE:** (trip_id, stop_type, sequence)
 
---
 
### [O3] `trip_surcharges` — Phụ phí đơn hàng
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | |
| trip_id | BIGINT FK | NO | — | → trips |
| name | VARCHAR(200) | NO | — | Phụ phí chờ hàng... |
| amount | DECIMAL(15,2) | NO | — | |
| notes | TEXT | YES | NULL | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
 
---
 
### [O4] `trip_documents` — Chứng từ đính kèm
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | |
| trip_id | BIGINT FK | NO | — | → trips |
| doc_type | ENUM | NO | — | dispatch_note/delivery_receipt/epod/invoice/other |
| doc_name | VARCHAR(200) | NO | — | |
| file_url | VARCHAR(500) | NO | — | |
| file_size_kb | INT UNSIGNED | YES | NULL | |
| uploaded_by | BIGINT FK | NO | — | → users |
| notes | TEXT | YES | NULL | |
| created_at | TIMESTAMP | YES | NULL | |
 
---
 
### [O5] `trip_status_histories` — Timeline trạng thái *(EXISTS)*
Giữ nguyên. Đã có trip_id, from_status, to_status, changed_by, changed_at, note.
 
---
 
### [CP1] `trip_costs` — Chi phí phát sinh theo chuyến
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | |
| trip_id | BIGINT FK | NO | — | → trips |
| cost_category_id | BIGINT FK | NO | — | → cost_categories |
| amount | DECIMAL(15,2) | NO | — | Số tiền thực tế |
| norm_amount | DECIMAL(15,2) | YES | NULL | Định mức so sánh |
| description | TEXT | YES | NULL | |
| receipt_file_url | VARCHAR(500) | YES | NULL | Ảnh hóa đơn/chứng từ |
| incurred_date | DATE | NO | — | Ngày phát sinh |
| status | ENUM | NO | pending | pending/approved/rejected |
| approval_required | TINYINT(1) | NO | 0 | Cần phê duyệt không |
| approved_by | BIGINT FK | YES | NULL | → users |
| approved_at | TIMESTAMP | YES | NULL | |
| approval_note | TEXT | YES | NULL | |
| notes | TEXT | YES | NULL | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
---
 
### [CP2] `cost_approval_requests` — Phê duyệt chi phí vượt định mức
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | |
| trip_id | BIGINT FK | NO | — | → trips |
| requested_by | BIGINT FK | NO | — | → users |
| total_amount | DECIMAL(15,2) | NO | — | Tổng tiền cần duyệt |
| reason | TEXT | NO | — | Lý do vượt định mức |
| status | ENUM | NO | pending | pending/approved/rejected |
| reviewed_by | BIGINT FK | YES | NULL | → users |
| reviewed_at | TIMESTAMP | YES | NULL | |
| review_note | TEXT | YES | NULL | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
 
---
 
### [KT1] `reconciliation_sessions` — Phiên đối soát
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | |
| customer_id | BIGINT FK | NO | — | → customers |
| period_from | DATE | NO | — | Từ ngày |
| period_to | DATE | NO | — | Đến ngày |
| total_trips | INT UNSIGNED | NO | 0 | |
| total_revenue | DECIMAL(15,2) | NO | 0 | |
| adjusted_amount | DECIMAL(15,2) | NO | 0 | |
| final_amount | DECIMAL(15,2) | NO | 0 | |
| status | ENUM | NO | draft | draft/confirmed/locked |
| confirmed_at | TIMESTAMP | YES | NULL | |
| confirmed_by | BIGINT FK | YES | NULL | → users |
| notes | TEXT | YES | NULL | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
 
---
 
### [KT2] `reconciliation_items` — Chi tiết từng chuyến trong đối soát
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | |
| session_id | BIGINT FK | NO | — | → reconciliation_sessions |
| trip_id | BIGINT FK | NO | — | → trips |
| original_amount | DECIMAL(15,2) | NO | — | Doanh thu gốc |
| adjusted_amount | DECIMAL(15,2) | YES | NULL | Sau điều chỉnh |
| adjustment_reason | TEXT | YES | NULL | |
| is_disputed | TINYINT(1) | NO | 0 | Có tranh chấp không |
| dispute_note | TEXT | YES | NULL | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
 
**UNIQUE:** (session_id, trip_id)
 
---
 
### [KT3] `payment_records` — Ghi nhận thanh toán
| Cột | Kiểu | Null | Default | Mô tả |
|---|---|---|---|---|
| id | BIGINT PK | NO | AUTO | |
| company_id | BIGINT FK | NO | — | |
| customer_id | BIGINT FK | NO | — | → customers |
| reconciliation_session_id | BIGINT FK | YES | NULL | → reconciliation_sessions |
| payment_date | DATE | NO | — | |
| amount | DECIMAL(15,2) | NO | — | |
| payment_method | ENUM | NO | — | bank_transfer/cash/check |
| bank_reference | VARCHAR(200) | YES | NULL | Mã giao dịch |
| receipt_url | VARCHAR(500) | YES | NULL | |
| notes | TEXT | YES | NULL | |
| created_at | TIMESTAMP | YES | NULL | |
| updated_at | TIMESTAMP | YES | NULL | |
| deleted_at | TIMESTAMP | YES | NULL | |
 
---
 
### Các bảng EXISTS không thay đổi
- **invoices** — Hóa đơn
- **invoice_status_histories** — Lịch sử trạng thái hóa đơn
- **notifications** — Thông báo in-app
- **chat_messages** — Lịch sử chat AI
- **knowledge_articles** — Tài liệu nghiệp vụ cho AI
- **rag_index** — Vector index cho AI search
- **report_caches** — Cache báo cáo
---