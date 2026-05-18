# Company Ship / CETA — Project Spec & Convention

> File này là **spec nguồn sự thật chính** của dự án. Khi thay đổi architecture, API contract, database, luồng nghiệp vụ, quyền hoặc UI pattern, cập nhật file này trước hoặc cùng commit code.
>
> Version: 2.0 | Cập nhật: 2026-05-11 | Stack hiện hành: Laravel 12 + PHP 8.2 + React 18 + Ant Design 5

---

## 0. Phạm vi và nguyên tắc dùng file

- `CONVENTION.md` tại root là spec chính cho cả frontend `ship-app/` và backend `ship-app-api/`.
- `ship-app-api-backup/` là snapshot/backup, không dùng làm nguồn sự thật khi thiết kế hoặc sửa code mới.
- Các spec cũ trong `ship-app/CONVENTION.md`, `ship-app-api/spec.md`, `ship-app-api/database.md` chỉ là tài liệu tham khảo lịch sử nếu nội dung lệch file này.
- Khi có mâu thuẫn giữa tài liệu và code hiện tại, ưu tiên kiểm tra code đang chạy rồi cập nhật lại file này.
- Mọi endpoint public và protected hiện dùng prefix `/api`, không dùng `/api/v1` trừ khi có migration API versioning rõ ràng.

---

## 1. Project Overview

### 1.1 Mục tiêu sản phẩm

Company Ship / CETA là hệ thống quản lý vận tải đường bộ cho doanh nghiệp logistics Việt Nam. Sản phẩm kết hợp TMS, fleet management, điều vận, CRM, kế toán vận hành, hóa đơn, nhân sự tài xế, bảng lương và trợ lý AI nội bộ.

Các nghiệp vụ chính:

- Quản lý đơn vận/chuyến xe từ yêu cầu vận chuyển đến hoàn tất giao hàng.
- Quản lý khách hàng, nhóm khách hàng, bảng giá, công nợ và thanh toán.
- Quản lý đội xe, loại xe, tài liệu xe, lịch bảo dưỡng, lịch phân xe/tài xế.
- Quản lý tài xế, hồ sơ, giấy phép, đội tài xế, lịch làm việc, nghỉ phép, tăng ca, vi phạm.
- Điều vận theo ngày qua dispatch board: chuyến chưa phân công, xe/tài xế khả dụng, xung đột lịch.
- Ghi nhận chi phí chuyến, duyệt chi phí vượt ngưỡng, đối soát và báo cáo lợi nhuận.
- Tạo hóa đơn, theo dõi trạng thái hóa đơn, trạng thái thanh toán.
- Tính lương tài xế dựa trên kỳ lương, dòng lương, phụ cấp, khấu trừ, tăng ca, nghỉ phép.
- Chat/AI nội bộ dựa trên dữ liệu nghiệp vụ, RAG index và knowledge articles.

### 1.2 Persona chính

| Persona | Mục tiêu | Module chính |
|---|---|---|
| `super_admin` / `admin` | Quản trị toàn hệ thống, tenant, user, cấu hình | Companies, Users, Settings, Reports |
| `dispatcher` | Điều vận chuyến, phân xe/tài xế, xử lý trạng thái | Trips, Orders Pool, Dispatch, Vehicles, Drivers |
| `accountant` | Chi phí, hóa đơn, đối soát, công nợ, báo cáo | Invoices, Accounting, Reconciliations, Payments |
| `viewer` | Xem dữ liệu, báo cáo, trạng thái vận hành | Dashboard, Reports |

### 1.3 Domain glossary

- **Company / Tenant**: doanh nghiệp sử dụng hệ thống. Hầu hết dữ liệu nghiệp vụ phải có `company_id`.
- **Office**: đơn vị vận hành thuộc company. Hiện có bảng `offices`, nhưng office-level scope trong `TenantContext` đang để mở.
- **Customer**: khách hàng đặt chuyến, có nhóm, bảng giá, công nợ, lịch sử thanh toán.
- **Transport Request**: yêu cầu vận chuyển đầu vào, có thể dẫn tới quotation/trip.
- **Trip**: entity trung tâm của vận hành. Gắn với customer, driver, vehicle, route, revenue, chi phí, chứng từ.
- **Dispatch Board**: màn điều vận theo ngày, gom trips, unassigned trips và summary.
- **Vehicle**: phương tiện, có loại xe, trạng thái, tài liệu, bảo dưỡng, chi phí và phân công.
- **Driver**: tài xế, đồng thời là hồ sơ nhân sự vận hành, có license, lịch làm việc, nghỉ phép, tăng ca, lương.
- **Reconciliation**: phiên đối soát doanh thu chuyến theo khách hàng/kỳ.
- **Invoice**: hóa đơn cho chuyến/khách hàng, có status và status history.
- **Payroll**: kỳ lương và các dòng lương tài xế.

---

## 2. Kiến trúc tổng quan

```text
company_ship/
├── CONVENTION.md          # Spec chính của dự án
├── ship-app/              # Frontend React admin dashboard
├── ship-app-api/          # Backend Laravel API
└── ship-app-api-backup/   # Backup/reference cũ, không phát triển trực tiếp
```

```text
Browser
  │
  │ React 18 + Vite + Refine + Ant Design
  │ Authorization: Bearer <token>
  │ X-Tenant-ID: <company_id>
  ▼
Laravel API /api
  │ Middleware: CORS, locale, API error envelope,
  │ auth:sanctum, tenant.context, track.actions
  ▼
Database
  │ Dev: SQLite
  │ Docker/prod target: MySQL 8.0
  ▼
Domain data, audit logs, jobs, notifications, RAG
```

### 2.1 Frontend architecture

- Runtime app: `ship-app/`.
- Build tool: Vite.
- UI: Ant Design v5 là primary, Tailwind/SCSS dùng hỗ trợ.
- Routing: `react-router-dom` qua Refine, route config trong `src/routes/appRouteConfig.tsx`.
- Server state: TanStack Query.
- Global state: Zustand persisted storage.
- API client: Axios instance `src/services/api.ts` với interceptor token, tenant header, 401 handling và toast lỗi.
- Data abstraction: Refine `dataProvider` + service classes theo domain.
- i18n: custom locales `src/locales/vi.ts` và `src/locales/en.ts`.

### 2.2 Backend architecture

- Runtime app: `ship-app-api/`.
- Framework thực tế: Laravel `12.58.0`.
- API auth: Laravel Sanctum personal access token + bảng `refresh_tokens`.
- Generic CRUD hiện hành: `App\Http\Controllers\Api\CetaSpecController`.
- Auth chuyên biệt: `AuthController` + `AuthService`.
- Upload/chat/shipping fee có controller/service riêng.
- Tenant context: `EnsureTenantContext` resolve `company_id` từ `X-Tenant-ID`, `X-Company-Id`, query `company_id`, hoặc fallback user assignment.
- API response envelope: `BaseController::successResponse()` và `HandleApiErrors`.
- Audit: `TrackUserActions` ghi mọi request API authenticated vào `audit_logs`; middleware audit read nhạy cảm đã có nhưng chưa gắn mặc định trong route group.

---

## 3. Tech Stack hiện hành

### 3.1 Backend

| Concern | Công nghệ |
|---|---|
| PHP | `^8.2` theo `composer.json` |
| Framework | Laravel `^12.0`, local đang là `12.58.0` |
| Auth | Laravel Sanctum `^4.3` + custom refresh token |
| DB dev | SQLite `database/database.sqlite` |
| DB Docker/prod target | MySQL 8.0 |
| API docs | `darkaonline/l5-swagger` |
| Postman export | `andreaselia/laravel-api-to-postman` |
| Formatter | Laravel Pint |
| Tests | PHPUnit 11 + Pest 3 |
| Queue/session/cache | Database driver mặc định local |
| Upload | Local/Cloudinary-ready qua `UploadService` |
| AI | Gemini/Groq config, Chat/RAG services |

### 3.2 Frontend

| Concern | Công nghệ |
|---|---|
| React | `18.3.1` |
| TypeScript | `5.5.4` strict |
| Vite | `^7.3.1` |
| UI | Ant Design `^5.29.3`, `@refinedev/antd` |
| Data framework | Refine core `^4.58.0` |
| Server state | TanStack Query `^5.97.0` |
| Client state | Zustand `^4.5.5` |
| HTTP | Axios `^1.7.7` |
| Charts | Recharts `^3.8.0` |
| Validation | Zod `^4.3.6` |
| Styling | Tailwind `^3.4.11` + SCSS |
| Icons | Ant Design Icons + lucide-react |
| Notifications | react-hot-toast |
| Node engine | `^20.19.0 || >=22.12.0` |

---

## 4. Local Development

### 4.1 Backend bằng Docker

```bash
cd ship-app-api
cp docker/env.docker.example .env
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

- API qua nginx: `http://localhost:8080/api`.
- MySQL trong Docker: host `db`, database `ship_db`, user `root`, password `root`.
- Nếu đổi host port: `API_HTTP_PORT=9080 docker compose up -d`.
- Chạy migration trong cùng môi trường với HTTP. Nếu HTTP dùng Docker nhưng migrate trên host SQLite, request sẽ thiếu bảng trong MySQL.

### 4.2 Backend bằng host local

```bash
cd ship-app-api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

### 4.3 Frontend

```bash
cd ship-app
npm install
cp .env.example .env
npm run dev
```

- Dev server mặc định: `http://localhost:3000`.
- Dev proxy `/api` trỏ tới `VITE_API_ORIGIN` hoặc `VITE_PROXY_TARGET`.
- `VITE_API_ORIGIN` là origin backend, không thêm `/api`.
- `VITE_API_BASE_URL` là override tuyệt đối tới `/api`, chỉ dùng khi thật cần.

### 4.4 Commands chuẩn

| App | Command | Ý nghĩa |
|---|---|---|
| Backend | `composer test` | Clear config + chạy Laravel tests |
| Backend | `composer lint` | Laravel Pint |
| Backend | `php artisan route:list --path=api` | Kiểm tra API routes |
| Backend | `php artisan migrate:status` | Kiểm tra migration |
| Frontend | `npm run dev` | Vite dev server |
| Frontend | `npm run build` | `tsc && vite build` |
| Frontend | `npm run lint` | ESLint max 0 warnings |
| Frontend | `npm run knip` | Unused files/dependencies |

---

## 5. Environment Variables

### 5.1 Frontend `.env`

```env
VITE_API_ORIGIN=http://localhost:8080
# VITE_API_BASE_URL=http://localhost:8080/api
# VITE_PROXY_TARGET=http://localhost:8080

VITE_AUTH_REFRESH_ENABLED=false
VITE_AUTH_FORGOT_PASSWORD_SEND_ENABLED=true
VITE_AUTH_FORGOT_PASSWORD_VERIFY_ENABLED=true
VITE_CHAT_ENABLED=false

VITE_AUTO_LOGIN=true
VITE_TEST_ACCOUNTS=true
VITE_DEMO_EMAIL=admin@abctransport.com
VITE_DEMO_PASSWORD=password

VITE_GOOGLE_OAUTH_CLIENT_ID=
VITE_ENABLE_VN_OPEN_API=true
VITE_APP_NAME=Ship ERP
VITE_APP_VERSION=1.0.0
```

### 5.2 Backend `.env`

```env
APP_NAME=CompanyShip
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080

DB_CONNECTION=sqlite
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=ship_db
# DB_USERNAME=root
# DB_PASSWORD=root

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1,127.0.0.1:8000
SANCTUM_TOKEN_EXPIRATION=60
FRONTEND_URL=http://localhost:3000
API_URI_PREFIX=api

SHOW_TEST_ACCOUNTS=false
SHIP_ENCRYPT_PII=false
GEMINI_API_KEY=
GROQ_API_KEY=
CLOUDINARY_URL=
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
SHIPPING_BASE_FEE=15000
SHIPPING_FEE_PER_KM=5000
```

Không commit `.env` có secret thật. `.env.example` phải đủ key để dev mới chạy được.

---

## 6. API Contract

### 6.1 Base URL và headers

- Base path chuẩn: `/api`.
- Frontend request authenticated phải gửi:

```http
Authorization: Bearer <sanctum-token>
X-Tenant-ID: <company_id>
Accept: application/json
Content-Type: application/json
```

- `X-Tenant-ID` là header chuẩn. `X-Company-Id` và query `company_id` chỉ là legacy/fallback.
- Upload dùng `multipart/form-data` với field `file`.

### 6.2 Response envelope

Success:

```json
{
  "success": true,
  "message": "OK",
  "data": {}
}
```

List paginated:

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "data": [],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 15,
      "total": 0
    }
  }
}
```

Error:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {}
}
```

### 6.3 Query convention

| Query | Ý nghĩa |
|---|---|
| `page` | Trang hiện tại |
| `per_page` | Số dòng/trang, backend clamp `1..100` |
| `keyword`, `q`, `search` | Tìm theo cột khả dụng như `name`, `code`, `email`, `phone`, `address`, `plate_number` |
| `sort_by` | Tên cột sort |
| `sort_order` | `asc` hoặc `desc` |
| Các query khác | Nếu trùng tên cột table thì apply exact filter |

### 6.4 Public endpoints

| Method | Endpoint | Ghi chú |
|---|---|---|
| `GET` | `/api` | Root metadata |
| `GET` | `/api/health` | Health check |
| `POST` | `/api/auth/login` | Login email/password, throttle `5,1` |
| `POST` | `/api/auth/social/login` | Google/Facebook/Apple |
| `POST` | `/api/auth/refresh-token` | Refresh bằng refresh token |
| `POST` | `/api/auth/forgot-password` | Gửi OTP reset |
| `POST` | `/api/auth/check-otp` | Verify OTP |
| `POST` | `/api/auth/reset-password` | Reset password sau OTP |
| `GET` | `/api/documentation` | Swagger UI |

### 6.5 Protected middleware group

Tất cả route nghiệp vụ nằm trong middleware:

```php
['auth:sanctum', 'tenant.context', 'track.actions']
```

Hệ quả:

- Không có token hợp lệ: `401`.
- Token hợp lệ nhưng tenant không truy cập được: `403`.
- Mọi request authenticated được ghi `audit_logs` bởi `TrackUserActions`.
- Query generic tự scope `company_id` nếu table có cột này.

### 6.6 Generic CRUD pattern

Các resource do `CetaSpecController` phục vụ theo pattern:

| Method | Pattern | Ý nghĩa |
|---|---|---|
| `GET` | `/{resource}` | List có paginate/filter/search/sort |
| `POST` | `/{resource}` | Create |
| `GET` | `/{resource}/{id}` | Detail |
| `PUT/PATCH` | `/{resource}/{id}` | Update |
| `DELETE` | `/{resource}/{id}` | Soft delete nếu table có `deleted_at`, hard delete nếu không |

Nested pattern:

| Method | Pattern |
|---|---|
| `GET/POST` | `/{parent}/{id}/{child}` |
| `PUT/PATCH/DELETE` | `/{parent}/{id}/{child}/{childId}` |

### 6.7 Action endpoint convention

Backend hiện đăng ký action bằng `PATCH`. Frontend service cũ còn một số method gọi `POST`; khi sửa module phải đồng bộ về `PATCH` hoặc đổi route có chủ đích ở cả hai phía.

| Domain | Endpoint |
|---|---|
| Trips | `PATCH /trips/{id}/assign`, `/start`, `/deliver`, `/complete`, `/cancel`, `/change-vehicle`, `/change-driver` |
| Trip stops | `PATCH /trips/{id}/stops/{childId}/arrive`, `/complete` |
| Vehicles | `PATCH /vehicles/{id}/status`, `/vehicles/{id}/assignments/release` |
| Drivers | `PATCH /drivers/{id}/status` |
| Leave | `PATCH /leave-requests/{id}/approve`, `/reject`, `/cancel` |
| Overtime | `PATCH /overtime/{id}/approve`, `/reject` |
| Invoices | `PATCH /invoices/{id}/issue`, `/mark-paid`, `/cancel` |
| Reconciliation | `PATCH /reconciliations/{id}/confirm` |
| Notifications | `PATCH /notifications/{id}/read`, `/notifications/read-all` |
| Users | `PATCH /users/{id}/status`, `POST /users/{id}/reset-password` |

---

## 7. Auth, Tenant và Permission

### 7.1 Login flow

1. Frontend gọi `POST /api/auth/login`.
2. Backend kiểm tra user active, verify password.
3. Backend issue Sanctum access token và custom refresh token.
4. Response gồm `user`, `tenants`, `token`, `refreshToken`.
5. Frontend lưu access token vào `auth-token:v1`, tenant vào `tenant-id:v1`.
6. Nếu user có nhiều tenants, frontend đi qua `/select-tenant`; nếu 1 tenant thì chọn tự động.

Lưu ý contract hiện tại:

- Backend trả `refreshToken` camelCase.
- Frontend type đang khai báo `refresh_token`.
- Khi bật `VITE_AUTH_REFRESH_ENABLED=true`, cần đồng bộ key refresh token hoặc mapping ở `auth.service.ts` để tránh refresh không hoạt động.

### 7.2 Tenant resolution

`EnsureTenantContext` resolve company theo thứ tự:

1. `X-Tenant-ID` hoặc `X-Company-Id` hoặc query `company_id`.
2. Admin/super_admin có thể chọn company bất kỳ tồn tại.
3. User thường phải có `user_companies` tương ứng nếu table tồn tại.
4. Fallback first assigned company.
5. Fallback từ `users.driver_id -> drivers.company_id`.
6. Nếu authenticated nhưng không resolve được tenant: set sentinel `-1`, query trả zero rows.

### 7.3 Role hiện hành

`users.role` được validate ở backend với các giá trị:

```text
super_admin, admin, dispatcher, accountant, viewer
```

Role middleware có logic cho `company_admin` và `office_admin`, nhưng `User::hasRole()` hiện chỉ đọc `users.role`. Nếu muốn bật scoped role thật, cần hoàn thiện model/relations và contract frontend.

### 7.4 Permission matrix

Table `user_permissions` có module/action:

```text
modules: orders, vehicles, drivers, accounting, reports, settings
actions: view, create, edit, delete, approve, export
```

`GET /auth/me` trả:

- `user`
- `company`
- `permissions`
- `tenants`

Hiện `MeResponseBuilder` grant all cho `admin`/`super_admin`; user thường trả blank matrix. Nếu muốn enforcement chi tiết theo `user_permissions`, cần update `permissionsMatrix()` và route middleware theo từng endpoint.

---

## 8. Database Spec

### 8.1 Quy ước database

- Bảng nghiệp vụ tenant-scoped phải có `company_id`.
- Dữ liệu có thể bị xóa bởi user nên ưu tiên `deleted_at` soft delete.
- Các bảng log/audit/status history không được update/delete từ UI.
- `created_at` và `updated_at` dùng chuẩn Laravel nếu bảng có lifecycle CRUD.
- Mã nghiệp vụ (`code`, `order_code`) không được update sau khi tạo; `CetaSpecController::preventCodeUpdate()` đang chặn `code` và `order_code`.
- Foreign key phải dùng `restrict` cho dữ liệu kế toán/vận hành nếu xóa gây mất lịch sử; dùng `set null` cho optional owner/actor.
- Không sửa migration đã chạy; tạo migration mới có `up()` và `down()`.
- Nếu migration có thể chạy trên nhiều trạng thái DB, dùng guard `Schema::hasTable()` / `Schema::hasColumn()`.

### 8.2 Table groups hiện hành

Database local hiện có 66 bảng:

| Nhóm | Bảng |
|---|---|
| Platform/Auth/Audit | `users`, `user_permissions`, `personal_access_tokens`, `refresh_tokens`, `password_reset_tokens`, `audit_logs` |
| Laravel infra | `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `migrations` |
| Tenant/Org | `companies`, `offices`, `departments`, `positions`, `employees` |
| Catalog | `vehicle_types`, `cargo_types`, `locations`, `route_templates`, `cost_categories`, `order_status_configs`, `spare_parts` |
| CRM/Pricing | `customer_groups`, `customers`, `price_lists`, `price_list_items`, `pricing_rules`, `quotations`, `quotation_pricing_items`, `quotation_approvals` |
| Fleet | `vehicles`, `vehicle_documents`, `vehicle_assignments`, `maintenance_schedules`, `maintenance_records` |
| Driver/Workforce | `drivers`, `driver_documents`, `driver_teams`, `driver_work_schedules`, `leave_types`, `leave_requests`, `overtime_requests` |
| Operations | `transport_requests`, `trips`, `trip_stops`, `trip_surcharges`, `trip_costs`, `trip_documents`, `trip_status_histories`, `cost_approval_requests` |
| Accounting/Billing | `reconciliation_sessions`, `reconciliation_items`, `payment_records`, `invoices`, `invoice_status_histories` |
| Payroll | `payrolls`, `payroll_lines`, `payroll_adjustments` |
| Notification/AI | `notifications`, `chat_messages`, `knowledge_articles`, `rag_index`, `report_caches` |

### 8.3 Table catalog

| Table | Mục đích | Key fields |
|---|---|---|
| `companies` | Tenant/company | `code`, `name`, `tax_code`, `status`, `deleted_at` |
| `users` | Tài khoản đăng nhập | `username`, `email`, `password`, `role`, `status`, `driver_id`, `last_login_at` |
| `user_permissions` | Permission matrix theo company/user/module | `company_id`, `user_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_approve`, `can_export` |
| `audit_logs` | Audit thao tác API | `user_id`, `company_id`, `action`, `table_name`, `record_id`, `resource`, `request_id`, `metadata` |
| `offices` | Chi nhánh/văn phòng | `company_id`, `code`, `name`, `manager_id` |
| `departments` | Phòng ban legacy/org | `office_id`, `parent_id`, `code`, `name` |
| `positions` | Chức danh/lương cơ bản legacy | `code`, `name`, `base_salary`, `level` |
| `employees` | Nhân sự legacy còn tồn tại DB | `code`, `name`, `office_id`, `department_id`, `position_id`, `status` |
| `vehicle_types` | Loại phương tiện | `company_id`, `name`, `max_load_ton`, `volume_m3`, `required_license_class`, `is_active` |
| `cargo_types` | Loại hàng | `company_id`, `name`, `requires_special_vehicle`, `special_requirements`, `is_active` |
| `locations` | Điểm nhận/giao/kho | `company_id`, `name`, `address`, `province`, `district`, `lat`, `lng`, `customer_id` |
| `route_templates` | Tuyến mẫu/bảng cự ly | `company_id`, `origin_location_id`, `destination_location_id`, `distance_km`, `default_price`, `fuel_norm_liter`, `toll_norm` |
| `cost_categories` | Loại chi phí chuyến | `company_id`, `code`, `name`, `requires_receipt`, `approval_threshold`, `is_active` |
| `order_status_configs` | Cấu hình trạng thái order/trip | `company_id`, `code`, `name`, `color`, `is_terminal`, `sort_order` |
| `spare_parts` | Vật tư/phụ tùng bảo dưỡng | `company_id`, `name`, `unit`, `is_active` |
| `customer_groups` | Nhóm khách hàng | `company_id`, `name`, `assigned_dispatcher_id`, `is_active` |
| `customers` | Khách hàng | `company_id`, `code`, `type`, `name`, `tax_code`, `phone`, `email`, `group_id`, `credit_limit`, `payment_terms_days`, `is_active` |
| `price_lists` | Bảng giá theo khách | `company_id`, `customer_id`, `name`, `effective_from`, `effective_to`, `is_active` |
| `price_list_items` | Dòng bảng giá | `company_id`, `price_list_id`, `route_template_id`, `vehicle_type_id`, `cargo_type_id`, `price`, `price_unit` |
| `pricing_rules` | Luật định giá/quotation | `company_id`, `office_id`, `customer_id`, `base_freight`, `rate_per_km`, `minimum_margin_percent`, `effective_from`, `effective_to` |
| `quotations` | Báo giá | `company_id`, `transport_request_id`, `pricing_rule_id`, `code`, `distance_km`, `selling_price`, `margin_percent`, `status` |
| `quotation_pricing_items` | Dòng giá trong báo giá | `quotation_id`, `type`, `code`, `label`, `amount`, `is_mandatory` |
| `quotation_approvals` | Lịch sử duyệt báo giá | `quotation_id`, `actor_id`, `action`, `reason`, `snapshot` |
| `vehicles` | Xe | `company_id`, `plate_number`, `vehicle_type_id`, `type`, `brand`, `model`, `capacity`, `status`, `current_odometer_km` |
| `vehicle_documents` | Hồ sơ xe | `company_id`, `vehicle_id`, `doc_type`, `doc_number`, `expiry_date`, `file_url`, `alert_before_days` |
| `vehicle_assignments` | Phân tài xế-xe theo kỳ | `company_id`, `vehicle_id`, `driver_id`, `from_date`, `to_date`, `release_reason`, `created_by` |
| `maintenance_schedules` | Lịch bảo dưỡng dự kiến | `company_id`, `vehicle_id`, `spare_part_id`, `interval_km`, `interval_days`, `next_due_km`, `next_due_date`, `is_active` |
| `maintenance_records` | Phiếu bảo dưỡng thực tế | `company_id`, `vehicle_id`, `maintenance_schedule_id`, `type`, `started_date`, `completed_date`, `total_cost`, `status` |
| `drivers` | Tài xế/hồ sơ vận hành | `company_id`, `code`, `name`, `license_no`, `license_class`, `expired_date`, `available_status`, `status`, `user_id`, `team_id` |
| `driver_documents` | Hồ sơ tài xế | `company_id`, `driver_id`, `doc_type`, `doc_number`, `expiry_date`, `file_url`, `alert_before_days` |
| `driver_teams` | Đội tài xế | `company_id`, `name`, `manager_id`, `is_active` |
| `driver_work_schedules` | Lịch làm việc tài xế | `company_id`, `driver_id`, `work_date`, `shift_code`, `start_time`, `end_time`, `vehicle_id`, `status`, `submitted_at`, `approved_at`, `locked_at` |
| `leave_types` | Loại nghỉ phép | `company_id`, `code`, `name`, `is_paid`, `annual_quota_days`, `requires_attachment`, `status` |
| `leave_requests` | Đơn nghỉ phép | `company_id`, `driver_id`, `leave_type_id`, `from_date`, `to_date`, `total_days`, `reason`, `status`, `approved_by`, `rejection_reason` |
| `overtime_requests` | Đơn tăng ca | `company_id`, `driver_id`, `work_date`, `start_time`, `end_time`, `ot_hours`, `status`, `approved_by`, `payroll_id` |
| `transport_requests` | Yêu cầu vận chuyển | `company_id`, `customer_id`, `created_by`, `code`, `pickup_location`, `delivery_location`, `cargo_type`, `requested_delivery_date`, `status` |
| `trips` | Chuyến/đơn vận trung tâm | `company_id`, `code`, `customer_id`, `driver_id`, `vehicle_id`, `status`, `scheduled_date`, `scheduled_time_from`, `scheduled_time_to`, `price`, `base_price`, `surcharge_amount`, `total_revenue`, `payment_status` |
| `trip_stops` | Điểm dừng của chuyến | `company_id`, `trip_id`, `stop_type`, `sequence`, `location_id`, `address`, `scheduled_time`, `actual_time`, `status` |
| `trip_surcharges` | Phụ phí chuyến | `company_id`, `trip_id`, `name`, `amount` |
| `trip_costs` | Chi phí chuyến | `company_id`, `trip_id`, `cost_category_id`, `amount`, `norm_amount`, `receipt_file_url`, `status`, `approval_required`, `approved_by` |
| `trip_documents` | Chứng từ chuyến | `company_id`, `trip_id`, `doc_type`, `doc_name`, `file_url`, `file_size_kb`, `uploaded_by` |
| `trip_status_histories` | Lịch sử đổi trạng thái chuyến | `trip_id`, `from_status`, `to_status`, `changed_by`, `changed_at`, `note` |
| `cost_approval_requests` | Phiếu duyệt chi phí vượt ngưỡng | `company_id`, `trip_id`, `requested_by`, `total_amount`, `reason`, `status`, `reviewed_by`, `reviewed_at` |
| `reconciliation_sessions` | Phiên đối soát | `company_id`, `customer_id`, `period_from`, `period_to`, `total_revenue`, `adjusted_amount`, `final_amount`, `status`, `confirmed_at` |
| `reconciliation_items` | Dòng đối soát | `company_id`, `session_id`, `trip_id`, `original_amount`, `adjusted_amount`, `is_disputed`, `dispute_note` |
| `payment_records` | Ghi nhận thanh toán | `company_id`, `customer_id`, `reconciliation_session_id`, `payment_date`, `amount`, `payment_method`, `bank_reference`, `receipt_url` |
| `invoices` | Hóa đơn | `company_id`, `code`, `trip_id`, `customer_id`, `subtotal`, `vat_rate`, `vat_amount`, `total_amount`, `status`, `issued_at`, `paid_at` |
| `invoice_status_histories` | Lịch sử trạng thái hóa đơn | `invoice_id`, `from_status`, `to_status`, `changed_by`, `changed_at`, `note` |
| `payrolls` | Kỳ lương | `company_id`, `month`, `year`, `status`, `locked_at`, `snapshot_json`, `approved_by`, `paid_at`, `paid_by` |
| `payroll_lines` | Dòng lương tài xế | `company_id`, `payroll_id`, `driver_id`, `base_salary`, `trip_bonus`, `allowance`, `deduction`, `overtime_pay`, `net_salary` |
| `payroll_adjustments` | Điều chỉnh lương | `company_id`, `payroll_id`, `original_payroll_id`, `driver_id`, `type`, `category`, `amount`, `reason`, `approved_by` |
| `notifications` | Thông báo Laravel notification-style | `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at` |
| `chat_messages` | Chat sessions/messages | `user_id`, `session_id`, `message`, `response`, `context`, `model`, `status`, `error_message` |
| `knowledge_articles` | Knowledge base cho AI/RAG | `company_id`, `category`, `title`, `content`, `tags`, `tenant_priority`, `is_active` |
| `rag_index` | Index truy hồi AI | `source_table`, `source_id`, `company_id`, `content`, `embedding`, `metadata`, `source_updated_at` |
| `report_caches` | Cache báo cáo | `type`, `month`, `year`, `data_json`, `expires_at` |

---

## 9. Business Flows

### 9.1 Trip lifecycle

Backend hiện enforce transition trong `CetaSpecController::assertTripTransition()`:

```text
pending -> assigned -> in_progress -> delivered -> completed
pending/in_progress -> cancelled
pending/in_progress -> change-vehicle/change-driver
```

Frontend hiện support bộ status rộng hơn:

```text
pending, assigned, driver_accepted, en_route_pickup, picked_up,
in_transit, arrived, delivered, completed, cancelled, delayed, emergency
```

Quy ước từ nay:

- Backend là nguồn sự thật runtime. Nếu muốn dùng status rộng hơn, phải migrate backend validation/action/status history trước, rồi cập nhật frontend.
- Không thêm status chỉ ở frontend.
- UI list có thể gom status thành bucket hiển thị: `new`, `assigned`, `in_transit`, `delivered`, `completed`, `cancelled`.

### 9.2 Trip assignment rules

Khi `assign`:

- Vehicle phải có `status = active`.
- Vehicle không được có trip khác cùng ngày đang `in_transit`.
- Driver không được có license hết hạn trong `driver_documents` với `document_type = license` nếu dữ liệu field này tồn tại.
- Trip action sai state trả `422`.

### 9.3 Customer deletion rule

Không xóa customer nếu còn trip active:

- Active nghĩa là trip có `status` khác `completed` và khác `cancelled`.
- Backend trả `422 Cannot delete customer with active trips`.

### 9.4 Vehicle assignment rule

Khi tạo `vehicle_assignments` mới:

- Backend tự đóng assignment đang active của cùng `vehicle_id` hoặc `driver_id` bằng `to_date = today`.
- `release_reason = Auto-closed before new active assignment`.

### 9.5 Cost approval rule

Khi tạo `trip_costs`:

- Nếu `amount > cost_categories.approval_threshold`, backend set `approval_required = true`, `status = pending`.
- Đồng thời tạo `cost_approval_requests` với `total_amount`, `reason`, `requested_by`.

### 9.6 Reconciliation lock rule

- Không update `reconciliation_items` nếu session tương ứng đã có `locked_at`.
- Confirm reconciliation dùng `PATCH /reconciliations/{id}/confirm`, status thành `confirmed` nếu table hỗ trợ.

### 9.7 Invoice flow

Runtime action:

```text
draft/current -> issue -> issued_at
issued/current -> mark-paid -> paid_at
current -> cancel
```

Frontend đang khai báo thêm `send-cqt`, `export-pdf`, `send-email`.
Runtime hiện có:
- `GET /invoices/{id}/pdf`: xuất PDF tối thiểu từ dữ liệu hóa đơn.
- `PATCH /invoices/{id}/email`: gửi email qua Laravel Mail tới email khách hàng, đính kèm PDF.
- `GET /invoices/{id}/cqt`: trả lỗi nghiệp vụ `422` nếu chưa cấu hình provider CQT thật; UI mặc định ẩn action CQT.

### 9.8 Workforce flow

- Work schedules CRUD qua `/driver-work-schedules` map tới `driver_work_schedules`.
- Actions: `submit`, `approve`, `reject`, `lock`, `override`, `hos-check`.
- Leave requests CRUD + `approve`, `reject`, `cancel`.
- Overtime CRUD + `approve`, `reject`.
- Payroll runtime dùng `POST /payrolls/generate`; action canonical dùng `PATCH /payrolls/{id}/approve` và `PATCH /payrolls/{id}/lock`. Backend vẫn giữ POST approve/lock tạm thời để tương thích client cũ.

### 9.9 Dispatch board

Các endpoint dispatch:

- `GET /dispatch/board?date=YYYY-MM-DD`
- `GET /dispatch/unassigned-trips?date=YYYY-MM-DD`
- `GET /dispatch/daily-summary?date=YYYY-MM-DD`

Backend hiện trả cùng payload:

```json
{
  "date": "YYYY-MM-DD",
  "trips": [],
  "unassigned_trips": [],
  "daily_summary": {
    "total_trips": 0,
    "unassigned": 0
  }
}
```

Frontend được phép build UI giàu hơn nhưng không giả định backend đã trả resource matrix nếu chưa implement.

---

## 10. Backend Resource Map

`CetaSpecController` map resource URL sang table:

| Resource | Table |
|---|---|
| `admin/companies`, `companies` | `companies` |
| `users` | `users` |
| `user-permissions` | `user_permissions` |
| `offices` | `offices` |
| `departments` | `departments` |
| `positions` | `positions` |
| `employees` | `employees` |
| `vehicle-types` | `vehicle_types` |
| `cargo-types` | `cargo_types` |
| `cost-categories` | `cost_categories` |
| `spare-parts` | `spare_parts` |
| `locations` | `locations` |
| `route-templates` | `route_templates` |
| `order-status-configs` | `order_status_configs` |
| `customers` | `customers` |
| `customer-groups` | `customer_groups` |
| `price-lists` | `price_lists` |
| `price-list-items` | `price_list_items` |
| `vehicles` | `vehicles` |
| `vehicle-documents` | `vehicle_documents` |
| `vehicle-assignments` | `vehicle_assignments` |
| `maintenance-schedules` | `maintenance_schedules` |
| `maintenance-records` | `maintenance_records` |
| `drivers` | `drivers` |
| `driver-documents` | `driver_documents` |
| `driver-teams` | `driver_teams` |
| `work-schedules` | `driver_work_schedules` |
| `leave-requests` | `leave_requests` |
| `leave-types` | `leave_types` |
| `overtime`, `overtimes` | `overtime_requests` |
| `transport-requests` | `transport_requests` |
| `trips` | `trips` |
| `trip-stops` | `trip_stops` |
| `trip-surcharges` | `trip_surcharges` |
| `trip-costs` | `trip_costs` |
| `trip-documents` | `trip_documents` |
| `cost-approvals` | `cost_approval_requests` |
| `reconciliations` | `reconciliation_sessions` |
| `reconciliation-items` | `reconciliation_items` |
| `payments` | `payment_records` |
| `invoices` | `invoices` |
| `invoice-status-histories` | `invoice_status_histories` |
| `notifications` | `notifications` |
| `report-caches` | `report_caches` |
| `payrolls` | `payrolls` |
| `payroll-driver-lines` | `payroll_lines` |

Nếu thêm resource mới:

1. Tạo/migrate table.
2. Thêm vào `CetaSpecController::TABLES`.
3. Thêm vào `CetaResourceRequest::TABLES`.
4. Thêm routes trong `routes/api.php`.
5. Thêm `ENDPOINTS` và service/hook frontend.
6. Thêm route/page/sidebar/i18n nếu có UI.

---

## 11. Frontend Spec & Convention

### 11.1 Structure

```text
ship-app/src/
├── components/        # common/form/table/ui/notifications
├── hooks/             # reusable React hooks
├── layouts/           # AppLayout, sidebar, header, user nav
├── lib/               # auth-session, query-client, safe-storage
├── locales/           # vi/en
├── pages/             # feature pages theo domain
├── providers/         # Refine providers, resources, theme
├── routes/            # path constants + route config
├── services/          # api.ts, endpoints.ts, domain services
├── stores/            # Zustand stores
├── styles/            # global SCSS/Tailwind bridge
├── types/             # domain/api/request/entity types
└── utils/             # format, validation, constants, status helpers
```

### 11.2 UI conventions

- Ant Design là component system chính.
- Page nghiệp vụ dùng cấu trúc: page header, filter bar, KPI/summary nếu cần, table/list, modal/drawer form.
- Table phải có `rowKey`, pagination, loading, empty state, action column rõ ràng.
- Form phải dùng `Form.Item name`, rules rõ, loading submit, disable double-submit.
- Destructive action phải có confirm dialog.
- Status hiển thị bằng `Tag` theo helper/status config, không hardcode màu rời rạc trong page.
- Không để text overflow trong button/card/table cell; dùng responsive layout và ellipsis/tooltip khi cần.
- i18n key phải có ở cả `vi.ts` và `en.ts` nếu text user-facing.

### 11.3 Data conventions

- Không gọi axios trực tiếp trong page nếu đã có service/hook.
- API path tập trung tại `src/services/endpoints.ts`.
- Domain service return raw API envelope hoặc unwrapped data nhất quán trong cùng service.
- List hooks phải dùng query keys ổn định, có params trong key.
- Khi đổi tenant, `queryClient.clear()` để tránh leak cache cross-tenant.
- Frontend resource name nên dùng kebab-case giống backend URL. Alias trong `dataProvider.tsx` chỉ dùng để tương thích legacy.

### 11.4 Auth/session storage

Keys:

```text
auth-token:v1
refresh-token:v1
tenant-id:v1
auth-storage:v1
app-storage:v1
```

- `rememberMe=true`: token lưu localStorage.
- `rememberMe=false`: token lưu sessionStorage.
- Tenant ID luôn lưu localStorage.
- 401 không refresh nếu `VITE_AUTH_REFRESH_ENABLED` không bật.

### 11.5 Frontend routes chính

| Domain | Route |
|---|---|
| Auth | `/login`, `/register`, `/forgot-password`, `/forgot-password/verify`, `/select-tenant` |
| Dashboard | `/dashboard` |
| Companies | `/admin/companies` |
| Orders/Trips | `/admin/orders`, `/admin/orders/pool` |
| Dispatch | `/admin/dispatch`, `/admin/dispatch/today` |
| Vehicles | `/admin/vehicles`, `/admin/vehicles/maintenance`, `/admin/vehicles/assignments`, `/admin/vehicles/costs` |
| Drivers | `/admin/drivers`, `/admin/drivers/schedule`, `/admin/drivers/bulk-schedule` |
| Customers | `/admin/customers`, `/admin/customers/price-list` |
| Invoices | `/admin/invoices` |
| Accounting | `/admin/accounting/revenue`, `/costs`, `/approvals`, `/reconciliation`, `/debt` |
| Payroll | `/admin/payroll`, `/admin/payroll/adjustments`, `/allowances`, `/deductions` |
| HR | `/admin/leave`, `/admin/overtime`, `/admin/violations` |
| Reports | `/admin/reports`, `/admin/reports/overview` |
| System | `/admin/users`, `/admin/system/users`, `/admin/settings`, `/admin/notifications`, `/admin/profile`, `/admin/billing` |

---

## 12. Backend Spec & Convention

### 12.1 Controller conventions

- Controller chỉ nhận request, gọi service/query, trả response envelope.
- Logic nghiệp vụ phức tạp phải nằm trong service, không nhét vào controller.
- Generic CRUD tạm nằm ở `CetaSpecController`; khi module ổn định nên tách sang controller/service riêng.
- Không expose raw exception message ở production.
- Validation lỗi phải trả `422` theo envelope chuẩn.

### 12.2 Request validation

- FormRequest chuyên biệt cho auth/upload/chat.
- Generic resource dùng `CetaResourceRequest`:
  - Field không tồn tại trong table bị `prohibited`.
  - Có domain rules cho `users`, `companies`, `trips`, `vehicles`, `drivers`, `leave_requests`, `trip_costs`, `cost_approval_requests`, `overtime_requests`.
- Khi thêm rule mới phải thêm test tối thiểu cho happy path và validation fail nếu endpoint quan trọng.

### 12.3 Tenant-scoped query

- Với query builder generic, `scopedQuery()` tự thêm:
  - `whereNull(deleted_at)` nếu table có soft delete.
  - `where(company_id, TenantContext::companyId)` nếu table có `company_id`.
  - notifications scope theo `notifiable_id = current user`.
- Với Eloquent model, dùng trait `BelongsToTenant` nếu model nằm trong tenant domain.

### 12.4 Audit

- `TrackUserActions` không được làm fail request chính nếu audit lỗi.
- Audit payload phải có tối thiểu: user, action, resource/table, status code, IP, user agent.
- Read audit nhạy cảm cho drivers/users/reconciliations đã có middleware `audit.sensitive_reads`; khi bật cần thêm vào route group hoặc route cụ thể.

### 12.5 Transaction rules

Dùng `DB::transaction()` cho mọi thao tác:

- Ghi nhiều bảng trong cùng nghiệp vụ.
- Tạo trip kèm stops/surcharges/costs.
- Assign trip đồng thời update vehicle/driver/assignment/history.
- Approve/reject cost và update trip_cost.
- Confirm reconciliation và lock items.
- Issue/pay/cancel invoice và ghi status history.
- Generate/approve/pay payroll.

### 12.6 Naming conventions

| Concern | Convention |
|---|---|
| API URL | kebab-case, plural: `/trip-costs` |
| DB table | snake_case plural: `trip_costs` |
| PHP class | PascalCase: `TripCostService` |
| TS service | kebab filename + singleton: `trip.service.ts` |
| TS type | PascalCase: `Trip`, `TripStatus` |
| Env key | UPPER_SNAKE_CASE |
| Status value | lowercase snake_case |

---

## 13. Security, Privacy, Reliability

- Không trả password, remember token hoặc secret trong API.
- Không log raw password/token/OTP.
- OTP reset password TTL hiện 2 phút, throttle 60 giây/email.
- Refresh token TTL hiện 30 ngày và có `is_revoked`.
- Account inactive không được login.
- CORS và Sanctum domain phải đồng bộ frontend host.
- PII encryption flag `SHIP_ENCRYPT_PII` đang mặc định false; nếu bật phải có kế hoạch migrate và key management.
- Tất cả endpoint write quan trọng phải audit được.
- Không để frontend hide button là lớp bảo mật duy nhất; quyền quan trọng phải enforce backend.
- Tenant mismatch phải trả `403`, không fallback âm thầm sang tenant khác.

---

## 14. Testing & Acceptance

### 14.1 Backend

Tối thiểu khi sửa API:

- Feature test cho endpoint chính.
- Test auth required nếu endpoint protected.
- Test tenant scoping nếu endpoint đọc/ghi tenant data.
- Test validation `422` cho payload sai.
- Test business rule `422/403/409` nếu có rule.

Commands:

```bash
cd ship-app-api
composer test
composer lint
```

### 14.2 Frontend

Tối thiểu khi sửa UI:

- `npm run build` pass.
- `npm run lint` pass hoặc ghi rõ warning còn lại.
- Smoke route/page trong browser nếu đổi flow chính.
- Kiểm tra loading, empty, error, success state.
- Kiểm tra responsive desktop và mobile cho form/table phức tạp.

Commands:

```bash
cd ship-app
npm run build
npm run lint
```

---

## 15. Known Gaps / Technical Debt

| Mức | Gap | Hướng xử lý |
|---|---|---|
| High | Frontend trip action service dùng `POST`, backend route hiện là `PATCH` | Chuẩn hóa method ở `trip.service.ts`, các service action khác và tests |
| High | Backend trip status machine hẹp hơn frontend status model | Chốt canonical status, migrate backend hoặc thu gọn frontend |
| High | Refresh token response key backend `refreshToken` lệch frontend `refresh_token` | Thêm mapper hoặc đổi backend response có cả hai key |
| High | Permission matrix `/auth/me` chưa đọc chi tiết `user_permissions` cho user thường | Implement matrix thật và backend middleware theo module/action |
| High | Code còn dùng model `LoginLog`/`ExportLog` nhưng DB SQLite hiện tại không có bảng `login_logs`/`export_logs` | Khôi phục migration/table hoặc chuyển auth sessions/export history sang bảng hiện hành |
| Medium | `CetaSpecController` generic quá rộng, nhiều business rule nằm trong controller | Tách dần controller/service theo domain có rủi ro cao |
| Medium | Một số endpoint frontend khai báo chưa có backend route đầy đủ (`payroll export theo id`, CQT provider thật...) | Ẩn UI hoặc implement endpoint |
| Medium | `dataProvider.tsx` còn nhiều resource aliases legacy | Chuẩn hóa resource name kebab-case và xóa alias theo sprint |
| Medium | Database còn bảng legacy/quotation/payroll mở rộng chưa phủ UI đầy đủ | Gắn trạng thái module: active, hidden, planned |
| Medium | `ship-app/CONVENTION.md` và `ship-app-api/spec.md` có nội dung spec cũ | Hoặc xóa, hoặc thay bằng link trỏ về file root |
| Low | `ship-app-api/README.md` vẫn còn Laravel skeleton text | Viết lại README backend theo dự án |

---

## 16. Change Management Rules

### 16.1 Thêm module mới

Backend:

1. Tạo migration/table theo quy ước tenant/soft delete.
2. Tạo model, fillable/casts/relations.
3. Nếu dùng generic controller, thêm resource map và route.
4. Nếu có nghiệp vụ riêng, tạo controller + service + FormRequest.
5. Thêm tests.
6. Cập nhật spec này.

Frontend:

1. Tạo domain/request/api types.
2. Thêm endpoint constants.
3. Tạo service/hook.
4. Tạo page list/detail/form theo pattern Ant Design.
5. Thêm route config, sidebar nếu cần.
6. Thêm i18n.
7. Build/lint/smoke.
8. Cập nhật spec này.

### 16.2 Thay đổi API contract

- Không đổi response shape âm thầm.
- Nếu đổi field, hỗ trợ backward-compatible trong ít nhất một sprint hoặc ghi migration rõ.
- Cập nhật `ENDPOINTS`, service, TS types và tests cùng commit.
- Nếu đổi HTTP method, sửa cả route backend và service frontend.

### 16.3 Thay đổi database

- Không edit migration đã chạy.
- Migration mới phải rollback được.
- Dữ liệu quan trọng phải có backfill hoặc default an toàn.
- Với production MySQL, kiểm tra index/foreign key trước khi thêm constraint.

### 16.4 Thay đổi quyền

- Quyền UI và quyền API phải đi cùng nhau.
- Route quan trọng cần backend middleware hoặc explicit service-level authorization.
- `/auth/me` phải trả đủ dữ liệu để frontend render đúng.

---

## 17. Changelog

| Version | Ngày | Nội dung |
|---|---|---|
| 2.0 | 2026-05-11 | Viết lại thành spec hiện hành theo code: Laravel 12, CetaSpecController, tenant middleware, API contract, DB 66 bảng, frontend routes/services, business rules và technical debt. |
| 1.1 | 2026-05-08 | Spec cũ cập nhật trip/payroll/service conventions. |
| 1.0 | 2026-05-08 | Initial generation từ phân tích codebase cũ. |
