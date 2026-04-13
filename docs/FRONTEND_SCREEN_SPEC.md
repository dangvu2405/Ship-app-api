# Frontend Screen Specification

Tai lieu nay mo ta chi tiet cac man hinh frontend de thiet ke UI/UX va trien khai theo backend hien tai.

- Base API: `/api/v1`
- Auth: `Bearer token`
- Response envelope:
  - success: `success`, `message`, `data`
  - error: `success=false`, `message`, optional `errors`

---

## 1. Auth Module

### 1.1 Login Screen
- Muc tieu: Dang nhap he thong.
- Components:
  - Input: `email`, `password`
  - Checkbox: remember me (UI only, optional)
  - Button: Login
  - Link: Forgot password
  - Social buttons: Google/Facebook/Apple
- API:
  - `POST /auth/login`
  - `POST /auth/social/login`
- Validation:
  - Email format
  - Password khong rong
- States:
  - loading khi submit
  - inline error khi `401/422`
- Success:
  - Luu token + user profile
  - Redirect Dashboard

### 1.2 Forgot Password Screen
- Components: email input + submit
- API: `POST /auth/forgot-password`
- Success message: "Password reset link sent"

### 1.3 Reset Password Screen
- Components:
  - token
  - email
  - password
  - confirm password
- API: `POST /auth/reset-password`

---

## 2. App Shell

### 2.1 Main Layout
- Left sidebar:
  - Dashboard
  - Workforce
  - Fleet
  - Operations
  - Payroll
  - Reports
  - Chat AI
  - System (RBAC/Master data)
- Topbar:
  - search global
  - notifications
  - user menu (profile, logout)
- API bootstrapping:
  - `GET /auth/me`
  - `GET /user`

### 2.2 Permission Guard
- Su dung roles/permissions tu profile user.
- Neu khong du quyen:
  - an menu item
  - route guard -> redirect 403 page

---

## 3. Dashboard Module

### 3.1 Dashboard Overview Screen
- Muc tieu: Tong quan KPI van hanh.
- Widgets:
  - so driver active
  - tong trip thang
  - tong doanh thu
  - tong chi phi luong
- Charts:
  - Revenue theo thang
  - Payroll cost theo office/company
- API:
  - `GET /reports/dashboard`
  - optional: `GET /reports/payroll-summary?company_id&month&year`
- Empty state:
  - card "No data"

---

## 4. Workforce Operations

### 4.1 Driver Schedule List Screen
- Table columns:
  - work_date, shift_code, driver, office, vehicle, start_time, end_time, status
- Filters:
  - driver_id, office_id, work_date, from-to, status
- API:
  - `GET /driver-schedules`
- Actions:
  - Create
  - Edit
  - Delete (not locked)
  - Submit
  - Approve/Reject
  - Lock
  - Override
  - HOS Check

### 4.2 Driver Schedule Create/Edit Drawer
- Fields:
  - driver_id (select)
  - office_id (select)
  - work_date
  - shift_code (`day|night|split|custom`)
  - start_time, end_time
  - vehicle_id (optional)
  - notes
- API:
  - `POST /driver-schedules`
  - `PUT/PATCH /driver-schedules/{id}`
- Errors:
  - `409`: trung xe/trung tai xe

### 4.3 Schedule Workflow Actions
- Submit:
  - `POST /driver-schedules/{id}/submit`
- Approve:
  - `POST /driver-schedules/{id}/approve`
- Reject:
  - `POST /driver-schedules/{id}/reject`
- Lock:
  - `POST /driver-schedules/{id}/lock`
- Override:
  - `POST /driver-schedules/{id}/override`
  - required: `override_reason`
- HOS check:
  - `GET /driver-schedules/{id}/hos-check`
  - compatibility: `POST /driver-schedules/{id}/hos-check`

### 4.4 Attendance List Screen
- Table columns:
  - date, driver, check_in, check_out, work_hours, overtime_hours, status
- Filters:
  - driver_id, date, from-to, status
- API:
  - `GET /attendance`
  - legacy alias: `GET /attendances`

### 4.5 Attendance Actions
- Check-in form:
  - `driver_id`, `check_in_time`
  - API: `POST /attendance/check-in`
- Check-out form:
  - `driver_id`, `check_out_time`
  - API: `POST /attendance/check-out`
- Adjust modal:
  - check_in, check_out, work_hours, overtime_hours, status, reason
  - API: `PATCH /attendance/{id}/adjust`
- Late attendance list:
  - `GET /attendances/late` or `/attendances/late/list`
- Late notify:
  - `POST /attendances/late/notify`

### 4.6 Leave Management Screens
- Leave Type list (read-only):
  - API: `GET /leave/types`
- Leave Request list:
  - API: `GET /leave`
  - filters: driver_id, status, from, to
- Leave create modal:
  - driver_id, leave_type_id, from_date, to_date, total_days, reason, attachment_urls
  - API: `POST /leave`
- Leave detail:
  - API: `GET /leave/{id}`
- Actions:
  - approve: `POST /leave/{id}/approve`
  - reject: `POST /leave/{id}/reject` + reason
  - cancel: `POST /leave/{id}/cancel`

### 4.7 Overtime Screens
- Overtime list:
  - API: `GET /overtime`
- Overtime create:
  - driver_id, company_id, work_date, start_time, end_time, ot_hours, reason
  - API: `POST /overtime`
- Overtime detail:
  - API: `GET /overtime/{id}`
- Actions:
  - approve: `POST /overtime/{id}/approve`
  - reject: `POST /overtime/{id}/reject`

### 4.8 Violations & Disputes Screens
- Violation list:
  - API: `GET /violations`
  - filters: driver_id, company_id, status, from, to
- Violation create:
  - driver_id, company_id, trip_id, type, occurred_at, description, penalty_amount, evidence_urls
  - API: `POST /violations`
- Violation detail:
  - API: `GET /violations/{id}`
- Actions:
  - confirm: `POST /violations/{id}/confirm`
  - dispute: `POST /violations/{id}/dispute`
  - resolve dispute: `POST /violations/{id}/resolve-dispute`
  - waive: `POST /violations/{id}/waive`

---

## 5. Fleet Module

### 5.1 Vehicles Screens
- List, create, update, delete
- API:
  - `GET /vehicles`
  - `POST /vehicles`
  - `GET /vehicles/{id}`
  - `PUT/PATCH /vehicles/{id}`
  - `DELETE /vehicles/{id}`

### 5.2 Vehicle Assignment Screens
- API CRUD: `/vehicle_assignments`
- Columns:
  - vehicle, driver, from_date, to_date

### 5.3 Vehicle Expense Screens
- API CRUD: `/vehicle_expenses`
- Columns:
  - type, amount, expense_date, vehicle, driver, note

---

## 6. Operations Module

### 6.1 Trips Screens
- API CRUD: `/trips`
- Fields:
  - code, customer_id, driver_id, vehicle_id
  - start_point, end_point
  - distance_km, start_time, end_time
  - price, status

### 6.2 Trip Bonus Rules Screens
- API CRUD: `/trip_bonus_rules`
- Fields:
  - min_km, max_km, bonus_per_km

### 6.3 Customers Screens
- API CRUD: `/customers`
- Fields:
  - type, name, tax_code, phone, email, address

### 6.4 Invoices Screens
- API CRUD: `/invoices`
- Fields:
  - code, trip_id, customer_id
  - subtotal, vat_rate, vat_amount, total_amount
  - status, issued_at, paid_at

---

## 7. Payroll Module

### 7.1 Payroll List Screen
- API: `GET /payrolls`
- Filters:
  - company_id, month, year, status
- Columns:
  - company, month, year, status, approved_at, locked_at

### 7.2 Payroll Generate/Recalculate
- API: `POST /payrolls`
- Payload:
  - company_id, month, year

### 7.3 Payroll Detail Screen
- API: `GET /payrolls/{id}`
- Hien thi payroll_lines:
  - base_salary, trip_bonus, overtime_pay, night_shift_allowance, public_holiday_pay
  - allowance, deduction, leave_unpaid_deduction, violation_deduction
  - fuel_cost, tax, net_salary
  - working_days, overtime_hours, trips_completed_count, total_distance_km

### 7.4 Payroll Actions
- Approve: `POST /payrolls/{id}/approve`
- Lock: `POST /payrolls/{id}/lock`
- Export: `GET /payrolls/{id}/export`

### 7.5 My Salary Screen (driver)
- API: `GET /payrolls/my-salary?month=&year=`
- Hien thi payslip ca nhan.

---

## 8. Reports Module

### 8.1 Dashboard Report Screen
- API: `GET /reports/dashboard`
- KPI cards + trend chart

### 8.2 Payroll Summary Report Screen
- API: `GET /reports/payroll-summary`
- Filter:
  - company_id (required), month, year

---

## 9. AI Assistant Module

### 9.1 Business Assist Screen
- Textarea nhap cau hoi nghiep vu.
- API: `POST /ai/business-assist`
- Payload:
  - `question`
- States:
  - typing/loading/error

---

## 10. Chat Module

### 10.1 Chat Session List Screen
- API: `GET /chat/sessions`
- Delete session: `DELETE /chat/sessions/{sessionId}`

### 10.2 Chat Conversation Screen
- Load messages: `GET /chat/messages?session_id=...`
- Send message: `POST /chat/messages`
- Streaming response: `POST /chat/messages/stream`

---

## 11. System Administration Module

### 11.1 Users Screens
- API CRUD: `/users`
- Fields:
  - username, email, password (create/update), driver_id, status
- Detail:
  - roles + permissions

### 11.2 Roles Screens
- API CRUD: `/roles`
- Sync permissions:
  - `POST /roles/{id}/permissions`

### 11.3 Permissions Screen
- List: `GET /permissions`
- Detail: `GET /permissions/{id}`

### 11.4 Master Data Screens
- Companies: `/companies`
- Offices: `/offices`
- Departments: `/departments`
- Positions: `/positions`
- Drivers: `/drivers`

---

## 12. Legacy Compatibility Screens (Optional in menu)

Khong can hien tren menu chinh, nhung FE cu co the dang goi:

- `GET /documentation`
- `GET /employees` (alias to drivers)
- `GET /allowances` (compat stub)
- `GET /deductions` (compat stub)
- `GET/POST/PATCH /attendances/*` aliases

---

## 13. UX State Standards (apply to all screens)

- Loading:
  - table skeleton for list
  - button loading for actions
- Empty state:
  - icon + "No data"
  - CTA create new record
- Error state:
  - 401 -> redirect login
  - 403 -> permission message
  - 404 -> "Not found"
  - 409 -> business conflict toast/dialog
  - 422 -> inline validation messages
  - 500 -> retry action
- Confirmation:
  - delete / lock / waive / resolve actions require confirm dialog

---

## 14. Suggested Frontend Route Map

- `/login`
- `/dashboard`
- `/workforce/schedules`
- `/workforce/attendance`
- `/workforce/leave`
- `/workforce/overtime`
- `/workforce/violations`
- `/fleet/vehicles`
- `/fleet/assignments`
- `/fleet/expenses`
- `/ops/trips`
- `/ops/bonus-rules`
- `/ops/customers`
- `/ops/invoices`
- `/payroll/list`
- `/payroll/:id`
- `/payroll/my-salary`
- `/reports/dashboard`
- `/reports/payroll-summary`
- `/ai/business-assist`
- `/chat`
- `/system/users`
- `/system/roles`
- `/system/permissions`
- `/system/companies`
- `/system/offices`
- `/system/departments`
- `/system/positions`
- `/system/drivers`

