# Ship App API Specification

## 1) Document Info
- **Project**: Ship App API
- **Framework**: Laravel 12.x
- **Language**: PHP 8.3+
- **API Style**: REST JSON
- **Auth**: Laravel Sanctum (Bearer token)
- **Current Access Policy**: Most APIs are `admin-only`
- **Last Updated**: 2026-03-25

---

## 2) Architecture Overview
- **Primary API routes**: `routes/api.php`
- **Secondary (Clean Architecture) API routes**: `routes/api_v2.php`
- **Base API prefix**: `/api`
- **Controllers**: `app/Http/Controllers/Api/*`
- **Domain modules (v2)**: `src/*`
- **Global exception JSON handling**: `bootstrap/app.php`

---

## 3) Security and Access Control

### 3.1 Authentication
- **Scheme**: Bearer token (Sanctum personal access token)
- **Login endpoint**: `POST /api/auth/login`
- **Token header**:
  - `Authorization: Bearer <token>`

### 3.2 Authorization
- Middleware aliases:
  - `role` -> `App\Http\Middleware\RoleMiddleware`
  - `permission` -> `App\Http\Middleware\PermissionMiddleware`
- Current route protection:
  - Most routes are in middleware group: `auth:sanctum` + `role:admin`
  - Public routes: `GET /api/health`, `POST /api/auth/login`

### 3.3 Default Role Model
- Roles seeded by `RolesAndPermissionsSeeder`:
  - `admin`
  - `hr`
  - `manager`
  - `staff`
- Permissions mapped in role/permission pivot tables.

---

## 4) API Response Contract

### 4.1 Success format
```json
{
  "success": true,
  "message": "OK",
  "data": {}
}
```

### 4.2 Error format
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field": ["error message"]
  }
}
```

### 4.3 Common status codes
- `200` OK
- `201` Created
- `400` Bad Request
- `401` Unauthenticated
- `403` Forbidden
- `404` Not Found
- `405` Method Not Allowed
- `422` Validation Error
- `500` Internal Server Error

---

## 5) Public API Endpoints
- `GET /api/health`
  - Health check endpoint, no auth required.
- `POST /api/auth/login`
  - Authenticate user and issue Sanctum token.

---

## 6) Protected Auth Endpoints (Admin-only)
- `POST /api/auth/register`
- `POST /api/auth/logout`
- `POST /api/auth/refresh`
- `GET /api/user`

---

## 7) Core Resource Endpoints (Admin-only)

All below are standard REST endpoints unless otherwise noted:
- `GET /api/{resource}`
- `POST /api/{resource}`
- `GET /api/{resource}/{id}`
- `PUT|PATCH /api/{resource}/{id}`
- `DELETE /api/{resource}/{id}`

### 7.1 Organization & HR
- `companies`
- `offices`
- `departments`
- `positions`
- `employees`
- `drivers`
- `users`
- `roles`
- `permissions` (read endpoints)

### 7.2 Fleet & Operations
- `vehicles`
- `vehicle_assignments`
- `vehicle_expenses`
- `customers`
- `trips`
- `invoices`

### 7.3 Payroll
- `payrolls`
- `allowances`
- `deductions`
- `attendances`

### 7.4 Reports
- `GET /api/reports/dashboard`
- `GET /api/reports/payroll-summary`

### 7.5 Additional payroll actions
- `GET /api/payrolls/my-salary`
- `POST /api/payrolls/{id}/approve`
- `POST /api/payrolls/{id}/lock`
- `GET /api/payrolls/{id}/export`

### 7.6 Additional role action
- `POST /api/roles/{role}/permissions` (sync permissions)

---

## 8) v2 API (Clean Architecture)
- Prefix: `/api/v2/employees`
- Endpoints:
  - `GET /api/v2/employees`
  - `POST /api/v2/employees`
  - `GET /api/v2/employees/{employee}`
  - `PUT /api/v2/employees/{employee}`
  - `DELETE /api/v2/employees/{employee}`
- Middleware: `auth:sanctum`, `role:admin`, `throttle:60,1`

---

## 9) Data Model Summary

### 9.1 Primary business tables
- `companies`, `offices`, `departments`, `positions`
- `employees`, `drivers`, `users`
- `roles`, `permissions`, `user_roles`, `role_permissions`
- `vehicles`, `vehicle_assignments`, `vehicle_expenses`
- `customers`, `trips`, `invoices`
- `allowances`, `deductions`, `employee_allowances`, `employee_deductions`
- `attendances`
- `payrolls`, `payroll_details`, `payroll_adjustments`
- `trip_bonus_rules`

### 9.2 Logging and cache tables
- `login_logs`, `audit_logs`, `export_logs`, `report_caches`
- `refresh_tokens`, `personal_access_tokens`
- `sessions`, `password_reset_tokens`
- Laravel infra: `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`

### 9.3 Common conventions
- Most business tables include:
  - `id`
  - `created_at`, `updated_at`
  - optional `deleted_at` (soft delete)
- Foreign keys and indexes are defined in migrations.

---

## 10) Seeder Strategy

### 10.1 Seeders
- `DatabaseSeeder`
  - Seeds core master data.
  - Calls `RolesAndPermissionsSeeder`.
  - Calls `AllTablesSeeder`.
- `AllTablesSeeder`
  - Ensures remaining business and system tables have sample records.
  - Inserts into token/session/cache/jobs/logging tables.
- `BulkDataSeeder`
  - High-volume seed flow for test data.

### 10.2 Recommended commands
- Fresh reset + seed:
```bash
php artisan migrate:fresh --seed
```
- Seed only:
```bash
php artisan db:seed
```

---

## 11) Swagger / OpenAPI
- Swagger package configured via `config/l5-swagger.php`
- Primary annotations:
  - `app/Http/Controllers/Controller.php`
  - API controllers under `app/Http/Controllers/Api/`
- Regenerate docs:
```bash
php artisan l5-swagger:generate
```
- Default docs endpoints:
  - UI: `/api/documentation`
  - JSON: `/docs` (mapped by package config)

---

## 12) Error Handling and Exception Policy
- API exceptions are normalized to JSON in `bootstrap/app.php`.
- Handled exception families include:
  - Validation
  - Authentication / Authorization
  - Model not found
  - Method/Route not found
  - Query/PDO exceptions
  - Fallback generic throwable
- Production should avoid exposing stack traces.

---

## 13) Operational Runbook

### 13.1 Local run
```bash
php artisan serve --host=127.0.0.1 --port=8000
```
Health check:
```bash
curl -i http://127.0.0.1:8000/api/health
```

### 13.2 Public tunnel (quick)
```bash
cloudflared tunnel --config /dev/null --url "http://127.0.0.1:8000" --no-autoupdate
```
Notes:
- Keep both `php artisan serve` and `cloudflared` running.
- Quick tunnel URL changes each restart.
- If origin is down, Cloudflared logs `connection refused`.

---

## 14) Quality Checklist (Release Gate)
- [ ] `php artisan route:list --path=api --except-vendor` passes.
- [ ] `php artisan migrate:fresh --seed` passes in clean env.
- [ ] Swagger docs regenerate successfully.
- [ ] Protected endpoints reject non-admin users with `403`.
- [ ] Public endpoints (`/api/health`, `/api/auth/login`) remain accessible.
- [ ] No uncaught server exceptions in common CRUD flows.

---

## 15) Future Improvements
- Add API versioning policy document (`v1`, `v2`, deprecation schedule).
- Add request/response schema examples per endpoint (contract-first).
- Add Postman collection and environment files.
- Add E2E auth and authorization tests for role boundaries.
- Add named Cloudflare tunnel setup guide for stable domain.

