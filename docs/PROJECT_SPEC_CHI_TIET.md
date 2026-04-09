# PROJECT SPEC — Company Ship ERP API
> **Tài liệu tham chiếu chính (AI must-read):** Đọc file này trước mọi thao tác với codebase.  
> **Cập nhật lần cuối:** 2026-04-09

---

## 0. Bản đồ tài liệu (Document Map)

| File | Nội dung |
|------|----------|
| `docs/PROJECT_SPEC_CHI_TIET.md` | **File này** — spec tổng thể, single source of truth cho AI |
| `docs/ARCHITECTURE_GUARDRAILS.md` | Quy tắc kiến trúc bắt buộc, không được vi phạm |
| `docs/PAYROLL_MODULE_EXTENSION_DESIGN.md` | Thiết kế mở rộng module Payroll production |
| `docs/FRONTEND_API_ENDPOINTS.md` | Danh sách endpoint cho frontend |
| `docs/uc_diagram_spec.md` | Đặc tả Use Case Diagram (UC-01 → UC-40) |
| `docs/sequence_activity_diagram_spec.md` | Đặc tả Sequence & Activity Diagram |
| `docs/test_cases.md` | Bảng test cases theo module |
| `database_design.md` | ERD đầy đủ tất cả bảng |

---

## 1. Tổng quan dự án

### 1.1 Tên và mục đích
**Company Ship ERP** là hệ thống quản lý tổng hợp vận tải & nhân sự dành cho doanh nghiệp vận chuyển. Hệ thống cung cấp:
- Quản lý nhân sự, tài xế, chấm công, tính lương
- Quản lý đội xe, phân công xe-tài xế, chuyến đi
- Quản lý khách hàng, hóa đơn, doanh thu
- RBAC phân quyền chi tiết theo vai trò
- Báo cáo tổng quan, xuất dữ liệu

### 1.2 Kiến trúc hệ thống

```
Frontend (Vite/React — ship-app)  ←→  Backend API (ship-app-api)  ←→  MySQL DB
```

**Repo này:** `ship-app-api` — **chỉ là RESTful API backend**, không chứa frontend.

### 1.3 Tech stack

| Layer | Công nghệ | Version |
|-------|-----------|---------|
| Language | PHP | ^8.2 |
| Framework | Laravel | ^12.0 |
| Auth | Laravel Sanctum | ^4.3 |
| Database | MySQL | 8.x |
| Testing | Pest PHP | ^3.0 |
| API Docs | L5-Swagger (Darkaonline) | ^10.1 |
| Container | Docker + Nginx | — |

### 1.4 Môi trường

| Môi trường | URL | Cổng DB |
|------------|-----|---------|
| Docker Dev | `http://localhost:8080/api` | 3306 |
| Production | `http://localhost/api` (PHP-FPM + Nginx) | — |

**Lệnh khởi động dev:**
```bash
cd ship-app-api
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

---

## 2. Kiến trúc code (Architecture)

### 2.1 Cấu trúc thư mục quan trọng

```
ship-app-api/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/   # Tất cả controller API
│   │   ├── Requests/          # FormRequest validation
│   │   ├── Resources/         # API Resource transformer
│   │   └── Middleware/
│   ├── Models/                # Eloquent Models
│   ├── Services/              # Business Logic Services
│   ├── Policies/              # Authorization Policies
│   └── Traits/
├── routes/
│   ├── api.php                # Route chính (v1)
│   └── api_v2.php             # Route v2 (Clean Architecture)
├── database/
│   ├── migrations/
│   └── seeders/
├── tests/
│   ├── Arch/                  # Architecture tests (bắt buộc pass)
│   └── Feature/Api/           # Feature tests theo module
└── docs/                      # Tài liệu (folder này)
```

### 2.2 Nguyên tắc kiến trúc BẮTBUỘC (Guardrails)

> ⚠️ **VI PHẠM = REJECT khi review**

1. **Controller mỏng (Thin Controller):**
   - Chỉ: nhận request → gọi service → trả JSON response
   - KHÔNG xử lý business logic trong controller
   - KHÔNG dùng `DB::` trực tiếp trong controller

2. **FormRequest bắt buộc:**
   - Mọi endpoint có input đều phải dùng `FormRequest`
   - KHÔNG dùng `$request->validate()` hoặc `Validator::make()` trong controller

3. **Service theo trách nhiệm:**
   - `AuthService`: login / register / logout / refresh
   - `PayrollService`: generate + tính toán payroll
   - `PayrollWorkflowService`: approve / lock / delete + state guard
   - `PayrollQueryService`: export payload + my-salary query
   - `ReportService`: dashboard / payroll summary
   - `ChatService`: AI chat với Gemini
   - `GeminiService`: wrapper gọi Gemini API
   - `BusinessAiAdvisorService`: AI advisor nghiệp vụ
   - `AttendanceLateNotificationService`: thông báo đi trễ

4. **State machine Payroll:**
   ```
   draft → approved → locked
   ```
   - Chặn transition sai (đã locked không được approve lại)
   - Chặn sửa/xóa khi `locked`

5. **Response envelope chuẩn:**
   ```json
   // Success
   { "success": true, "message": "...", "data": ... }
   // Error
   { "success": false, "message": "...", "errors": ... }
   ```

6. **Soft delete:** Mọi model dùng `SoftDeletes` trừ bảng log/token

### 2.3 Architecture Tests (bắt buộc pass)

```bash
php artisan test --testsuite=Arch
# tests/Arch/ApiControllersArchitectureTest.php
# tests/Arch/ApiControllersValidationArchitectureTest.php
```

---

## 3. Phân quyền (RBAC)

### 3.1 Các vai trò (Roles)

| Role | Mô tả |
|------|-------|
| `admin` | Toàn quyền hệ thống |
| `hr` | Quản lý nhân sự, chấm công |
| `accountant` | Tính lương, hóa đơn, báo cáo |
| `coordinator` | Điều phối xe, chuyến đi |
| `driver` | Tài xế |
| `staff` | Nhân viên thường (xem lương cá nhân) |

### 3.2 Middleware phân quyền

```php
// Routes hiện tại trong api.php
Route::middleware(['auth:sanctum'])->group(...)          // Đã đăng nhập
Route::middleware(['auth:sanctum', 'role:admin'])->group(...) // Admin only
```

### 3.3 Danh sách Permission (granular)

Permission code theo format: `module.action`

**Auth:**
- `auth.login`, `auth.logout`, `auth.register`, `auth.refresh`

**Users & Roles:**
- `users.view`, `users.create`, `users.update`, `users.delete`
- `roles.view`, `roles.create`, `roles.update`, `roles.delete`, `roles.assign_permissions`
- `permissions.view`

**HR:**
- `employees.view`, `employees.create`, `employees.update`, `employees.delete`
- `drivers.view`, `drivers.create`, `drivers.update`, `drivers.delete`
- `allowances.view`, `allowances.create`, `allowances.update`, `allowances.delete`
- `deductions.view`, `deductions.create`, `deductions.update`, `deductions.delete`
- `attendances.view`, `attendances.create`, `attendances.update`, `attendances.delete`

**Fleet & Operations:**
- `vehicles.view`, `vehicles.create`, `vehicles.update`, `vehicles.delete`
- `vehicle_assignments.view`, `vehicle_assignments.create`, `vehicle_assignments.update`, `vehicle_assignments.delete`
- `vehicle_expenses.view`, `vehicle_expenses.create`, `vehicle_expenses.update`, `vehicle_expenses.delete`
- `trips.view`, `trips.create`, `trips.update`, `trips.delete`
- `invoices.view`, `invoices.create`, `invoices.update`, `invoices.delete`
- `customers.view`, `customers.create`, `customers.update`, `customers.delete`

**Payroll:**
- `payrolls.view`, `payrolls.create`, `payrolls.update`, `payrolls.delete`
- `payrolls.approve`, `payrolls.lock`, `payrolls.export`
- `payrolls.my_salary` *(nhân viên xem lương cá nhân)*

**Organization:**
- `companies.view`, `companies.create`, `companies.update`, `companies.delete`
- `offices.view`, `offices.create`, `offices.update`, `offices.delete`
- `departments.view`, `departments.create`, `departments.update`, `departments.delete`
- `positions.view`, `positions.create`, `positions.update`, `positions.delete`

**Reports:**
- `reports.dashboard`, `reports.payroll_summary`

---

## 4. Các Module & API Endpoints

### 4.1 Authentication (`/api/auth/`)

| Method | Endpoint | Auth | Mô tả |
|--------|----------|------|-------|
| POST | `/api/auth/login` | Public | Đăng nhập, nhận Bearer token |
| POST | `/api/auth/logout` | sanctum | Đăng xuất, revoke token |
| POST | `/api/auth/refresh` | sanctum | Làm mới token |
| POST | `/api/auth/register` | admin | Tạo tài khoản mới |

**Login response:**
```json
{
  "success": true,
  "data": {
    "user": { "id": 1, "email": "...", "roles": [...], "permissions": [...] },
    "token": "1|abc123..."
  }
}
```

### 4.2 Users & RBAC (`/api/users/`, `/api/roles/`, `/api/permissions/`)

| Method | Endpoint | Mô tả |
|--------|----------|-------|
| GET | `/api/users` | Danh sách user |
| POST | `/api/users` | Tạo user |
| GET | `/api/users/{id}` | Chi tiết user |
| PUT | `/api/users/{id}` | Cập nhật user |
| DELETE | `/api/users/{id}` | Xóa user |
| GET | `/api/roles` | Danh sách role |
| POST | `/api/roles` | Tạo role |
| POST | `/api/roles/{id}/permissions` | Sync permissions vào role |
| GET | `/api/permissions` | Danh sách permission |

### 4.3 Organization (`/api/companies/`, `offices/`, `departments/`, `positions/`)

Tất cả là `apiResource` (CRUD đầy đủ), quyền `admin`.

**Hierarchy:** Company → Office → Department (có cây cha-con) → Position

### 4.4 HR Module

**Employees** (`/api/employees/`): CRUD, bộ lọc theo `office_id`, `department_id`, `status`  
**Drivers** (`/api/drivers/`): CRUD hồ sơ tài xế (gắn `employee_id`, GPLX)  
**Allowances** (`/api/allowances/`): CRUD loại phụ cấp  
**Deductions** (`/api/deductions/`): CRUD loại khấu trừ  
**Attendances** (`/api/attendances/`): CRUD chấm công

Endpoint đặc biệt:
```
GET  /api/attendances/late/list    # Danh sách đi trễ
POST /api/attendances/late/notify  # Gửi thông báo đi trễ
```

### 4.5 Fleet & Operations

**Vehicles** (`/api/vehicles/`): CRUD thông tin xe  
**Vehicle Assignments** (`/api/vehicle_assignments/`): Gán xe-tài xế theo kỳ  
**Vehicle Expenses** (`/api/vehicle_expenses/`): Chi phí xe (nhiên liệu, sửa chữa)  
**Customers** (`/api/customers/`): CRUD khách hàng  
**Trips** (`/api/trips/`): CRUD chuyến đi
- Status flow: `pending → in_progress → completed`

**Invoices** (`/api/invoices/`): Hóa đơn từ chuyến đi (subtotal + VAT → total)  
**Trip Bonus Rules** (`/api/trip_bonus_rules/`): Quy tắc thưởng theo km

### 4.6 Payroll Module

| Method | Endpoint | Mô tả |
|--------|----------|-------|
| GET | `/api/payrolls` | Danh sách bảng lương |
| POST | `/api/payrolls` | Tạo & tính bảng lương |
| GET | `/api/payrolls/{id}` | Chi tiết bảng lương |
| PUT | `/api/payrolls/{id}` | Cập nhật (chỉ khi `draft`) |
| DELETE | `/api/payrolls/{id}` | Xóa (chỉ khi `draft`) |
| POST | `/api/payrolls/{id}/approve` | Duyệt (`draft → approved`) |
| POST | `/api/payrolls/{id}/lock` | Khóa (`approved → locked`) |
| GET | `/api/payrolls/{id}/export` | Xuất JSON/Excel |
| GET | `/api/payrolls/my-salary` | Nhân viên xem lương cá nhân |

**State flow bắt buộc:**
```
draft → approved → locked
         ↑     KHÔNG được quay ngược khi locked
```

**Logic tính lương (`PayrollService::generatePayroll`):**
```
net_salary = base_salary
           + overtime_pay
           + bonus (trip_bonus_rules theo km)
           + allowances (employee_allowances)
           - deductions (employee_deductions)
           - fuel_cost
           - tax (TAX_RATE = 0.1, hiện tại cố định)
```

### 4.7 Reports

| Method | Endpoint | Mô tả |
|--------|----------|-------|
| GET | `/api/reports/dashboard` | Thống kê tổng quan (nhân viên, xe, chuyến đi, lương) |
| GET | `/api/reports/payroll-summary` | Tổng hợp lương theo tháng/phòng ban |

### 4.8 AI Features

| Method | Endpoint | Mô tả |
|--------|----------|-------|
| POST | `/api/ai/business-assist` | AI tư vấn nghiệp vụ (Gemini) |
| GET | `/api/chat/sessions` | Danh sách phiên chat |
| DELETE | `/api/chat/sessions/{id}` | Xóa phiên chat |
| GET | `/api/chat/messages` | Lịch sử tin nhắn |
| POST | `/api/chat/messages` | Gửi tin nhắn |
| POST | `/api/chat/messages/stream` | Chat streaming (SSE) |

---

## 5. Database Design

### 5.1 Danh sách bảng chính

| Bảng | Module | Mô tả |
|------|--------|-------|
| `users` | Auth | Tài khoản hệ thống |
| `roles` | Auth | Vai trò |
| `permissions` | Auth | Quyền chi tiết |
| `role_permissions` | Auth | Pivot role ↔ permission |
| `user_roles` | Auth | Pivot user ↔ role |
| `refresh_tokens` | Auth | Token làm mới |
| `login_logs` | Auth | Lịch sử đăng nhập |
| `audit_logs` | Auth | Lịch sử thao tác |
| `sessions` | Auth | Session Laravel |
| `companies` | Org | Công ty |
| `offices` | Org | Văn phòng |
| `departments` | Org | Phòng ban (tự tham chiếu) |
| `positions` | Org | Chức danh |
| `customers` | Org | Khách hàng |
| `employees` | HR | Hồ sơ nhân viên |
| `drivers` | HR | Hồ sơ tài xế |
| `allowances` | HR | Loại phụ cấp |
| `deductions` | HR | Loại khấu trừ |
| `employee_allowances` | HR | Phụ cấp theo nhân viên |
| `employee_deductions` | HR | Khấu trừ theo nhân viên |
| `employee_salary_configs` | HR/Payroll | Cấu hình lương (có effective_from/to) |
| `attendances` | HR | Chấm công hàng ngày |
| `attendance_summaries` | Payroll | Tổng hợp công theo kỳ |
| `vehicles` | Fleet | Thông tin xe |
| `vehicle_assignments` | Fleet | Gán xe-tài xế |
| `vehicle_expenses` | Fleet | Chi phí xe |
| `trips` | Operations | Chuyến đi |
| `trip_bonus_rules` | Operations | Quy tắc thưởng theo km |
| `invoices` | Operations | Hóa đơn |
| `payroll_periods` | Payroll | Kỳ lương |
| `payrolls` | Payroll | Bảng lương theo tháng |
| `payroll_details` | Payroll | Chi tiết lương từng nhân viên |
| `payroll_adjustments` | Payroll | Điều chỉnh lương |
| `report_caches` | Reports | Cache báo cáo |
| `export_logs` | Reports | Lịch sử xuất file |

### 5.2 Quy tắc bảng

- **Soft delete:** `deleted_at` timestamp — hầu hết các bảng nghiệp vụ
- **Audit columns:** `created_by`, `updated_by`, `deleted_by` → FK users (nullable) — bảng payroll và salary config
- **Primary Key:** `bigint` auto-increment
- **Tiền tệ:** `decimal(15, 2)`, currency mặc định `VND`
- **Status enum:** tất cả status dùng string (không dùng PHP Enum)

### 5.3 Quan hệ quan trọng

```
Company (1) → (*) Office (1) → (*) Department
Employee (*) → (1) Department
Employee (*) → (1) Position
Employee (*) → (1) Office
User (1) → (0..1) Employee
User (*) ↔ (*) Role ↔ (*) Permission

Driver (1) → (1) Employee
Driver (*) → (*) Vehicle (via vehicle_assignments)
Trip → Driver, Vehicle, Customer
Invoice → Trip

Payroll → Company, PayrollPeriod
PayrollDetail → Payroll, Employee
AttendanceSummary → PayrollPeriod, Employee
EmployeeSalaryConfig → Employee
```

---

## 6. Services Reference

### 6.1 AuthService
**File:** `app/Services/AuthService.php`
- `login(array $credentials)` → token + user với roles/permissions
- `register(array $data)` → tạo user mới
- `logout(User $user)` → revoke token
- `refresh(User $user)` → tạo token mới

### 6.2 PayrollService
**File:** `app/Services/PayrollService.php`
- `generatePayroll(int $companyId, int $month, int $year)` → tạo payroll + details (DB::transaction)
- Kiểm tra trùng lặp trước khi tạo
- Tính: base + overtime + bonus + allowance - deduction - fuel - tax

### 6.3 PayrollWorkflowService
**File:** `app/Services/PayrollWorkflowService.php`
- `approve(Payroll $payroll, User $user)` → `draft → approved`
- `lock(Payroll $payroll)` → `approved → locked`
- `delete(Payroll $payroll)` → chỉ cho phép khi `draft`
- State guard: throw Exception khi transition sai

### 6.4 PayrollQueryService
**File:** `app/Services/PayrollQueryService.php`
- `exportPayload(Payroll $payroll)` → data export
- `mySalary(User $user)` → lương cá nhân

### 6.5 ReportService
**File:** `app/Services/ReportService.php`
- `dashboard()` → thống kê tổng quan
- `payrollSummary(array $filters)` → tổng hợp lương

### 6.6 ChatService / GeminiService
**File:** `app/Services/ChatService.php`, `GeminiService.php`
- Chat AI với Gemini, hỗ trợ streaming SSE
- Lưu session & lịch sử tin nhắn

---

## 7. Models Reference

### 7.1 Các Model hiện có (app/Models/)

Dựa trên controller & migration:
`User`, `Employee`, `Driver`, `Company`, `Office`, `Department`, `Position`, `Customer`,
`Vehicle`, `VehicleAssignment`, `VehicleExpense`, `Trip`, `TripBonusRule`, `Invoice`,
`Allowance`, `Deduction`, `EmployeeAllowance`, `EmployeeDeduction`, `EmployeeSalaryConfig`,
`Attendance`, `AttendanceSummary`, `Payroll`, `PayrollDetail`, `PayrollAdjustment`, `PayrollPeriod`,
`Role`, `Permission`, `User`, `LoginLog`, `AuditLog`, `ExportLog`, `ReportCache`, `RefreshToken`

### 7.2 User model — quan trọng

```php
// Relations
hasOne(Employee::class)
belongsToMany(Role::class, 'user_roles')
// Helpers
hasRole(string $role): bool
hasPermission(string $permission): bool
```

### 7.3 Payroll model — state

```php
// Status values: 'draft', 'approved', 'locked'
// Casted columns: locked_at, calculated_at, approved_at
belongsTo(Company::class)
belongsTo(PayrollPeriod::class)  // nullable (Phase 1+)
hasMany(PayrollDetail::class)
belongsTo(User::class, 'approved_by')
belongsTo(User::class, 'calculated_by')
```

---

## 8. Testing

### 8.1 Test suites

```bash
php artisan test                           # Toàn bộ test
php artisan test --testsuite=Arch         # Architecture tests (phải pass)
php artisan test --testsuite=Feature      # Feature tests
php artisan test --coverage               # Coverage report
```

### 8.2 Test files hiện có

```
tests/
├── Arch/
│   ├── ApiControllersArchitectureTest.php
│   └── ApiControllersValidationArchitectureTest.php
└── Feature/Api/
    ├── AuthApiTest.php
    ├── PayrollApiTest.php
    └── ReportsApiTest.php
```

### 8.3 Khi thêm module mới, phải có test cho:

1. Happy path (201/200)
2. Permission guard (401/403)
3. Validation (422)
4. State transition fail (nếu có state machine)

---

## 9. Môi trường & Cấu hình

### 9.1 .env quan trọng

```dotenv
APP_NAME="Company Ship"
APP_ENV=local
APP_KEY=base64:...
APP_URL=http://localhost:8080

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=ship_db
DB_USERNAME=root
DB_PASSWORD=root

GEMINI_API_KEY=...          # AI features
GEMINI_BASE_URL=...
```

### 9.2 Docker services

| Service | Container | Port |
|---------|-----------|------|
| PHP-FPM | `app` | - |
| MySQL 8 | `mysql` | 3306 |
| Nginx | `nginx` | 8080→80 |

### 9.3 Lệnh thường dùng

```bash
# Migrations
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:fresh --seed

# Tests
docker compose exec app php artisan test
docker compose exec app php artisan test --testsuite=Arch

# Cache
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear

# Tinker
docker compose exec app php artisan tinker
```

---

## 10. Roadmap & Trạng thái Implementation

### 10.1 Đã hoàn thành ✅

- [x] Authentication (login/logout/refresh/register) với Sanctum token
- [x] RBAC: roles, permissions, role-based middleware
- [x] Organization: companies, offices, departments, positions
- [x] HR: employees, drivers, allowances, deductions, employee_salary_configs
- [x] Fleet: vehicles, vehicle_assignments, vehicle_expenses
- [x] Operations: trips (state flow), invoices, customers, trip_bonus_rules
- [x] Payroll v1: generate, approve, lock, export, my-salary
- [x] Attendance: CRUD + late notification
- [x] Reports: dashboard, payroll-summary
- [x] AI Chat: ChatService, GeminiService, streaming SSE
- [x] Architecture tests với Pest
- [x] Payroll Phase 1: payroll_periods, employee_salary_configs, attendance_summaries tables
- [x] Swagger API documentation (`/api/documentation`)

### 10.2 Trong kế hoạch / Chưa làm ⏳

- [ ] Payroll Phase 2: payroll_earnings, payroll_deductions (line items), insurance_rates, tax_brackets
- [ ] Payroll Phase 3: salary_formula_sets, salary_formula_items, salary_advances
- [ ] Payroll Phase 4: payslips (phiếu lương), payroll_detail_snapshots, payroll_calculation_logs
- [ ] Granular route-level RBAC (hiện tại chỉ `role:admin` tất cả)
- [ ] File upload (avatar nhân viên, tài liệu)
- [ ] Push notifications / Email notifications
- [ ] PDF export cho payslip và invoice

---

## 11. Conventions & Coding Standards

### 11.1 Naming

| Loại | Convention | Ví dụ |
|------|------------|-------|
| Controller | PascalCase + Controller | `PayrollController` |
| Service | PascalCase + Service | `PayrollService` |
| FormRequest | PascalCase + Request | `StorePayrollRequest` |
| Resource | PascalCase + Resource | `PayrollResource` |
| Model | PascalCase singular | `Payroll`, `Employee` |
| Migration | snake_case mô tả | `create_payrolls_table` |
| Route | snake_case plural | `vehicle_assignments` |
| Table | snake_case plural | `payroll_details` |

### 11.2 PHP Standards

- PHP `^8.2`, Laravel 12
- `declare(strict_types=1)` cho tất cả file mới
- Type hints đầy đủ (parameter + return type)
- Dùng `readonly` property khi phù hợp
- DTO cho input phức tạp

### 11.3 Git conventions

- Branch: `feature/module-name`, `fix/bug-description`, `refactor/what`
- Commit: imperative, English hoặc Vietnamese rõ ràng

---

## 12. Câu hỏi thường gặp (FAQ for AI)

**Q: Tôi cần thêm endpoint mới, bắt đầu từ đâu?**  
→ 1. FormRequest validation → 2. Service method → 3. Controller gọi service → 4. Route → 5. Test Feature

**Q: Tôi cần sửa logic tính lương?**  
→ Sửa `app/Services/PayrollService.php::generatePayroll()`  
→ Cập nhật test `tests/Feature/Api/PayrollApiTest.php`

**Q: Tôi cần thêm permission mới?**  
→ Thêm vào seeder `database/seeders/PermissionSeeder.php`  
→ Thêm vào danh sách section 3.3 file này

**Q: Tại sao route X trả về 401/403?**  
→ Kiểm tra middleware: `auth:sanctum` (401) hay `role:admin` (403)  
→ Xem `routes/api.php` để biết route nằm trong middleware group nào

**Q: Tôi muốn thêm Service mới?**  
→ Phải đăng ký trong `AppServiceProvider` hoặc dùng Auto-discovery  
→ Inject qua constructor trong Controller

**Q: State Payroll bị lỗi transition?**  
→ Xem `PayrollWorkflowService` — throw `Exception` khi sai state  
→ State hợp lệ: `draft → approved → locked` (một chiều)

**Q: Dự án có frontend ở đâu?**  
→ Frontend nằm ở repo khác: `ship-app` (Vite/React)  
→ Kết nối qua `VITE_API_ORIGIN` → proxy `/api`

---

*File này được duy trì bởi AI để tránh mất context giữa các session. Cập nhật khi có thay đổi kiến trúc lớn.*
