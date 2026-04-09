# Frontend API Endpoints

**Schema mới (leave, payroll lines, payslip, GL, status history):** xem [FRONTEND_MUST_HAVE_SCHEMA_HANDOFF.md](./FRONTEND_MUST_HAVE_SCHEMA_HANDOFF.md) — **§8** mẫu JSON request (payload), **§9** mẫu response; gợi ý API trước khi backend wiring xong.

## Base URL
- Local: `http://localhost:8080`
- **Versioned API:** `/api/v1` (mọi endpoint nghiệp vụ + auth + chat)
- **Health (không version, cho probe):** `GET /api/health` — có thêm bản tương đương `GET /api/v1/health`

## Auth headers
- Public endpoint: không cần token.
- Auth endpoint: `Authorization: Bearer <token>`
- JSON header: `Content-Type: application/json`

## Public
- `GET /api/v1` (thông tin API / version)
- `GET /api/health` hoặc `GET /api/v1/health`
- `POST /api/v1/auth/login`

## Authenticated (auth:sanctum)
- `POST /api/v1/auth/logout`
- `POST /api/v1/auth/refresh`
- `GET /api/v1/auth/me` (chuẩn REST spec; cùng payload với `GET /api/v1/user`)
- `GET /api/v1/user` (giữ để tương thích)
- `GET /api/v1/payrolls/my-salary`

## Admin (auth:sanctum + role:admin)

### Auth quản trị
- `POST /api/v1/auth/register`

### Companies
- `GET /api/v1/companies`
- `POST /api/v1/companies`
- `GET /api/v1/companies/{company}`
- `PUT|PATCH /api/v1/companies/{company}`
- `DELETE /api/v1/companies/{company}`

### Offices
- `GET /api/v1/offices`
- `POST /api/v1/offices`
- `GET /api/v1/offices/{office}`
- `PUT|PATCH /api/v1/offices/{office}`
- `DELETE /api/v1/offices/{office}`

### Departments
- `GET /api/v1/departments`
- `POST /api/v1/departments`
- `GET /api/v1/departments/{department}`
- `PUT|PATCH /api/v1/departments/{department}`
- `DELETE /api/v1/departments/{department}`

### Positions
- `GET /api/v1/positions`
- `POST /api/v1/positions`
- `GET /api/v1/positions/{position}`
- `PUT|PATCH /api/v1/positions/{position}`
- `DELETE /api/v1/positions/{position}`

### Employees
- `GET /api/v1/employees`
- `POST /api/v1/employees`
- `GET /api/v1/employees/{employee}`
- `PUT|PATCH /api/v1/employees/{employee}`
- `DELETE /api/v1/employees/{employee}`

### Drivers
- `GET /api/v1/drivers`
- `POST /api/v1/drivers`
- `GET /api/v1/drivers/{driver}`
- `PUT|PATCH /api/v1/drivers/{driver}`
- `DELETE /api/v1/drivers/{driver}`

### Users
- `GET /api/v1/users`
- `POST /api/v1/users`
- `GET /api/v1/users/{user}`
- `PUT|PATCH /api/v1/users/{user}`
- `DELETE /api/v1/users/{user}`

### Roles & Permissions
- `GET /api/v1/roles`
- `POST /api/v1/roles`
- `GET /api/v1/roles/{role}`
- `PUT|PATCH /api/v1/roles/{role}`
- `DELETE /api/v1/roles/{role}`
- `POST /api/v1/roles/{role}/permissions`
- `GET /api/v1/permissions`
- `GET /api/v1/permissions/{permission}`

### Vehicles
- `GET /api/v1/vehicles`
- `POST /api/v1/vehicles`
- `GET /api/v1/vehicles/{vehicle}`
- `PUT|PATCH /api/v1/vehicles/{vehicle}`
- `DELETE /api/v1/vehicles/{vehicle}`

### Vehicle Assignments
- `GET /api/v1/vehicle_assignments`
- `POST /api/v1/vehicle_assignments`
- `GET /api/v1/vehicle_assignments/{vehicle_assignment}`
- `PUT|PATCH /api/v1/vehicle_assignments/{vehicle_assignment}`
- `DELETE /api/v1/vehicle_assignments/{vehicle_assignment}`

### Vehicle Expenses
- `GET /api/v1/vehicle_expenses`
- `POST /api/v1/vehicle_expenses`
- `GET /api/v1/vehicle_expenses/{vehicle_expense}`
- `PUT|PATCH /api/v1/vehicle_expenses/{vehicle_expense}`
- `DELETE /api/v1/vehicle_expenses/{vehicle_expense}`

### Customers
- `GET /api/v1/customers`
- `POST /api/v1/customers`
- `GET /api/v1/customers/{customer}`
- `PUT|PATCH /api/v1/customers/{customer}`
- `DELETE /api/v1/customers/{customer}`

### Trips
- `GET /api/v1/trips`
- `POST /api/v1/trips`
- `GET /api/v1/trips/{trip}`
- `PUT|PATCH /api/v1/trips/{trip}`
- `DELETE /api/v1/trips/{trip}`

### Trip Bonus Rules
- `GET /api/v1/trip_bonus_rules`
- `POST /api/v1/trip_bonus_rules`
- `GET /api/v1/trip_bonus_rules/{trip_bonus_rule}`
- `PUT|PATCH /api/v1/trip_bonus_rules/{trip_bonus_rule}`
- `DELETE /api/v1/trip_bonus_rules/{trip_bonus_rule}`

### Invoices
- `GET /api/v1/invoices`
- `POST /api/v1/invoices`
- `GET /api/v1/invoices/{invoice}`
- `PUT|PATCH /api/v1/invoices/{invoice}`
- `DELETE /api/v1/invoices/{invoice}`

### Allowances
- `GET /api/v1/allowances`
- `POST /api/v1/allowances`
- `GET /api/v1/allowances/{allowance}`
- `PUT|PATCH /api/v1/allowances/{allowance}`
- `DELETE /api/v1/allowances/{allowance}`

### Deductions
- `GET /api/v1/deductions`
- `POST /api/v1/deductions`
- `GET /api/v1/deductions/{deduction}`
- `PUT|PATCH /api/v1/deductions/{deduction}`
- `DELETE /api/v1/deductions/{deduction}`

### Attendances
- `GET /api/v1/attendances`
- `POST /api/v1/attendances`
- `GET /api/v1/attendances/{attendance}`
- `PUT|PATCH /api/v1/attendances/{attendance}`
- `DELETE /api/v1/attendances/{attendance}`

### Payrolls
- `GET /api/v1/payrolls`
- `POST /api/v1/payrolls`
- `GET /api/v1/payrolls/{payroll}`
- `PUT|PATCH /api/v1/payrolls/{payroll}`
- `DELETE /api/v1/payrolls/{payroll}`
- `POST /api/v1/payrolls/{id}/approve`
- `POST /api/v1/payrolls/{id}/lock`
- `GET /api/v1/payrolls/{id}/export`

### Chat (AI Assistant)
- `GET /api/v1/chat/sessions` (Lấy danh sách các phiên chat)
- `DELETE /api/v1/chat/sessions/{sessionId}` (Xoá phiên chat)
- `GET /api/v1/chat/messages` (Truyền `session_id`, `limit` để lấy lịch sử)
- `POST /api/v1/chat/messages` (Gửi chat JSON thông thường)
- `POST /api/v1/chat/messages/stream` (Gửi chat và nhận dữ liệu qua SSE/Stream)

### Reports
- `GET /api/v1/reports/dashboard`
- `GET /api/v1/reports/payroll-summary`

## API v2 (Clean Architecture, admin + throttle)
- `GET /api/v2/employees`
- `POST /api/v2/employees`
- `GET /api/v2/employees/{employee}`
- `PUT /api/v2/employees/{employee}`
- `DELETE /api/v2/employees/{employee}`

## Swagger (nội bộ)
- `GET /api/documentation`

## Response envelope chuẩn
- Success:
```json
{ "success": true, "message": "...", "data": ... }
```
- Error:
```json
{ "success": false, "message": "...", "errors": ... }
```
