# Company Ship API — Frontend Reference

Base URL: `http://your-domain/api/v1`  
Auth: All protected endpoints require `Authorization: Bearer <token>` header.  
Content-Type: `application/json`

---

## Response Envelope

All responses follow this structure:

```json
{
  "success": true | false,
  "message": "Human-readable status",
  "data": { ... } | [ ... ],
  "errors": { "field": ["error"] }  // only on 422
}
```

---

## 1. Authentication

### POST /auth/login
Login with email and password.

**Request**
```json
{ "email": "admin@example.com", "password": "secret123" }
```

**Response 200**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "eyJ...",
    "token_type": "Bearer",
    "user": { "id": 1, "name": "Admin", "email": "admin@example.com", "roles": ["admin"] }
  }
}
```

**Errors**: `401 Invalid credentials`, `422 Validation failed`

---

### POST /auth/social/login
Login via Google / Facebook / Apple OAuth token.

**Request**
```json
{
  "provider": "google",
  "access_token": "ya29...",
  "id_token": null
}
```

| Field | Type | Required |
|-------|------|----------|
| provider | string | yes — `google`, `facebook`, `apple` |
| access_token | string | required if `id_token` absent |
| id_token | string | required if `access_token` absent (Apple uses id_token) |

**Response 200** — same as `/auth/login`

---

### POST /auth/forgot-password
Send password reset email.

**Request**
```json
{ "email": "user@example.com" }
```

**Response 200**
```json
{ "success": true, "message": "Password reset link sent." }
```

---

### POST /auth/reset-password
Reset password using the token from the email.

**Request**
```json
{
  "token": "abc123...",
  "email": "user@example.com",
  "password": "newpassword",
  "password_confirmation": "newpassword"
}
```

**Response 200**
```json
{ "success": true, "message": "Password reset successfully." }
```

---

### POST /auth/logout  *(auth required)*
Revoke current token.

**Response 200**
```json
{ "success": true, "message": "Logged out." }
```

---

### POST /auth/refresh  *(auth required)*
Refresh access token.

**Response 200** — returns new `token`.

---

### GET /auth/me  *(auth required)*
Get current authenticated user.

**Response 200**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "Admin",
      "email": "admin@example.com",
      "driver": { "id": 10, "name": "...", "status": "active" },
      "roles": [{ "name": "admin", "permissions": [...] }]
    }
  }
}
```

---

## 2. Driver Work Schedules  *(admin required)*

### GET /driver-schedules
List schedules. Supports filters: `driver_id`, `office_id`, `work_date`, `from`, `to`, `status`.

**Query params**
| Param | Type | Description |
|-------|------|-------------|
| driver_id | int | Filter by driver |
| office_id | int | Filter by office |
| work_date | date | Exact date (`YYYY-MM-DD`) |
| from / to | date | Date range |
| status | string | `draft`, `submitted`, `approved`, `locked` |

**Response 200**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "driver_id": 10,
        "office_id": 2,
        "work_date": "2026-05-01",
        "shift_code": "day",
        "start_time": "07:00",
        "end_time": "17:00",
        "vehicle_id": 5,
        "status": "approved",
        "driver": { "id": 10, "name": "Nguyen Van A" },
        "vehicle": { "id": 5, "plate_number": "51A-12345" }
      }
    ],
    "total": 42,
    "per_page": 50
  }
}
```

---

### POST /driver-schedules
Create a new draft schedule.

**Request**
```json
{
  "driver_id": 10,
  "office_id": 2,
  "work_date": "2026-05-01",
  "shift_code": "day",
  "start_time": "07:00",
  "end_time": "17:00",
  "vehicle_id": 5,
  "notes": "Regular route"
}
```

| Field | Required | Notes |
|-------|----------|-------|
| driver_id | yes | Must be active driver |
| office_id | yes | |
| work_date | yes | >= today |
| shift_code | no | `day`, `night`, `split`, `custom`. Default: `day` |
| start_time / end_time | yes | `HH:MM` format |
| vehicle_id | no | Conflict check applied |
| notes | no | max 500 chars |

**Response 201** — created schedule object.

**Errors**:
- `409 Conflict` — driver or vehicle already has a non-draft schedule on this date/shift.
- `422 Validation failed`

---

### GET /driver-schedules/{id}
Get single schedule detail.

---

### PUT/PATCH /driver-schedules/{id}
Update a draft or submitted schedule.

**Request** — any updatable fields (same as POST, all optional).

**Errors**: `422` if locked; `409` if conflict.

---

### DELETE /driver-schedules/{id}
Delete a schedule. Cannot delete locked schedules.

---

### POST /driver-schedules/{id}/submit
Submit a draft schedule for approval.

**Response 200**
```json
{ "success": true, "message": "Schedule submitted for approval.", "data": { ... } }
```

---

### POST /driver-schedules/{id}/approve
Approve a submitted schedule.

---

### POST /driver-schedules/{id}/reject
Reject and return schedule to draft.

---

### POST /driver-schedules/{id}/lock
Lock a submitted/approved schedule.

**Response 200**
```json
{ "success": true, "message": "Schedule locked.", "data": { "status": "locked" } }
```

---

### POST /driver-schedules/{id}/override
Manager override for a locked/approved schedule.

**Request**
```json
{
  "work_date": "2026-05-02",
  "shift_code": "day",
  "start_time": "08:00",
  "end_time": "18:00",
  "vehicle_id": 3,
  "override_reason": "Emergency route adjustment due to vehicle breakdown"
}
```

---

### GET /driver-schedules/{id}/hos-check
Hours-of-Service pre-check for a schedule row.

**Response 200**
```json
{
  "success": true,
  "message": "HOS check passed.",
  "data": {
    "driver_id": 10,
    "work_date": "2026-05-01",
    "total_hours": 10,
    "limit_hours": 12,
    "is_ok": true
  }
}
```

Compatibility note: backend accepts both `GET` and `POST` for this endpoint.

---

## 3. Attendance  *(admin required)*

### GET /attendance
List attendance records. Filters: `driver_id`, `date`, `from`, `to`, `status`.

---

### POST /attendance/check-in
Record a driver check-in.

**Request**
```json
{
  "driver_id": 10,
  "check_in_time": "2026-05-01 07:05:00"
}
```

**Response 201**
```json
{
  "success": true,
  "message": "Check-in recorded.",
  "data": {
    "id": 101,
    "driver_id": 10,
    "date": "2026-05-01",
    "check_in": "2026-05-01 07:05:00",
    "check_out": null,
    "work_hours": null,
    "overtime_hours": 0,
    "status": "present"
  }
}
```

---

### POST /attendance/check-out
Record a driver check-out. Automatically calculates `work_hours` and `overtime_hours` (> 8h).

**Request**
```json
{
  "driver_id": 10,
  "check_out_time": "2026-05-01 17:30:00"
}
```

---

### PATCH /attendance/{id}/adjust
Admin override for an attendance record.

**Request**
```json
{
  "check_in": "2026-05-01 07:00:00",
  "check_out": "2026-05-01 16:00:00",
  "status": "present",
  "reason": "GPS system was offline, manual correction."
}
```

All fields optional. `reason` is required when any field is provided.

### Legacy aliases (for FE backward compatibility)
- `GET /attendances` → same as `GET /attendance`
- `POST /attendances/check-in` → same as `POST /attendance/check-in`
- `POST /attendances/check-out` → same as `POST /attendance/check-out`
- `PATCH /attendances/{id}/adjust` → same as `PATCH /attendance/{id}/adjust`
- `GET /attendances/late` and `GET /attendances/late/list` → late attendance list
- `POST /attendances/late/notify` → queue notify late-attendance batch (legacy FE endpoint)

---

## 4. Leave Management  *(admin required)*

### GET /leave/types
List active leave types.

**Response 200**
```json
{
  "success": true,
  "data": {
    "leave_types": [
      {
        "id": 1,
        "code": "ANNUAL",
        "name": "Nghỉ phép năm",
        "is_paid": true,
        "annual_quota_days": 12,
        "allow_carry_forward": true
      },
      {
        "id": 2,
        "code": "SICK",
        "name": "Nghỉ ốm",
        "is_paid": false,
        "annual_quota_days": 0
      }
    ]
  }
}
```

---

### GET /leave
List leave requests. Filters: `driver_id`, `status`, `from`, `to`.

**Status values**: `pending`, `approved`, `rejected`, `cancelled`

---

### POST /leave
Submit a new leave request.

**Request**
```json
{
  "driver_id": 10,
  "leave_type_id": 1,
  "from_date": "2026-05-10",
  "to_date": "2026-05-12",
  "total_days": 3,
  "reason": "Family vacation",
  "attachment_urls": ["https://cdn.example.com/doc.pdf"]
}
```

**Errors**:
- `422` — overlapping leave request exists, or insufficient balance for paid leave.

---

### GET /leave/{id}
Get single leave request detail.

---

### POST /leave/{id}/approve
Approve a leave request. **SoD rule**: the user who created the request cannot approve it.

**Response 200**
```json
{ "success": true, "message": "Leave request approved." }
```

**Errors**: `403 Separation of Duties violation`, `422 Wrong status`

---

### POST /leave/{id}/reject
Reject a leave request.

**Request**
```json
{ "rejection_reason": "Peak season, insufficient staff coverage." }
```

---

### POST /leave/{id}/cancel
Cancel a leave request (driver or admin). Restores balance for paid leave that was already approved.

---

## 5. Overtime  *(admin required)*

### GET /overtime
List OT requests. Filters: `driver_id`, `company_id`, `status`, `from`, `to`.

---

### POST /overtime
Submit an OT request.

**Request**
```json
{
  "driver_id": 10,
  "company_id": 1,
  "work_date": "2026-05-01",
  "start_time": "17:00",
  "end_time": "20:00",
  "ot_hours": 3,
  "reason": "Urgent delivery"
}
```

**Errors**:
- `422` — monthly OT cap of 40 hours would be exceeded.

---

### GET /overtime/{id}
Get single OT request.

---

### POST /overtime/{id}/approve
Approve an OT request. **SoD rule**: the requester cannot approve their own OT.

**Response 200**
```json
{ "success": true, "message": "Overtime request approved." }
```

**Errors**: `403 Separation of Duties violation`

---

### POST /overtime/{id}/reject
Reject an OT request.

**Request**
```json
{ "rejection_reason": "Not justified." }
```

---

## 6. Violations & Disputes  *(admin required)*

### GET /violations
List violations. Filters: `driver_id`, `company_id`, `status`, `from`, `to`.

**Status values**: `pending`, `confirmed`, `disputed`, `waived`

---

### POST /violations
Record a new violation.

**Request**
```json
{
  "driver_id": 10,
  "company_id": 1,
  "trip_id": 55,
  "type": "speeding",
  "occurred_at": "2026-05-01 14:30:00",
  "description": "Driver exceeded speed limit by 30km/h on Highway 1.",
  "penalty_amount": 500000,
  "evidence_urls": ["https://cdn.example.com/gps-screenshot.jpg"]
}
```

| type values | Description |
|-------------|-------------|
| speeding | Speed limit exceeded |
| route_deviation | Significant off-route |
| fuel_misuse | Fuel card abuse detected |
| behavior | Driver misconduct report |
| accident | Traffic accident |
| other | Other violations |

**Response 201** — violation object.

---

### GET /violations/{id}
Get violation detail including dispute if any.

---

### POST /violations/{id}/confirm
Confirm a pending violation. **SoD rule**: the reporter cannot be the confirmer.

**Response 200**
```json
{
  "success": true,
  "message": "Violation confirmed. Penalty will be applied to next payroll."
}
```

**Errors**: `403 Separation of Duties violation`, `422 Not in pending status`

---

### POST /violations/{id}/dispute
Open a dispute on a violation (driver response).

**Request**
```json
{
  "reason": "GPS data is incorrect. I was within speed limit. See dashcam footage.",
  "evidence_urls": ["https://cdn.example.com/dashcam.mp4"]
}
```

**Response 201** — dispute object. Violation status changes to `disputed`.

---

### POST /violations/{id}/resolve-dispute
Resolve a dispute.

**Request**
```json
{
  "resolution": "upheld",
  "resolution_note": "GPS data verified by 3rd party. Violation stands."
}
```

| resolution | Effect |
|-----------|--------|
| `upheld` | Dispute rejected; violation confirmed |
| `overturned` | Dispute accepted; violation waived |

---

### POST /violations/{id}/waive
Waive a violation without a dispute. **SoD**: reporter cannot waive.

**Request**
```json
{ "waive_reason": "First offense. Driver acknowledged and trained." }
```

---

## 7. Payroll  *(admin required)*

### GET /payrolls
List payrolls. Filters: `company_id`, `month`, `year`, `status`.

**Response 200**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "company_id": 1,
        "month": 5,
        "year": 2026,
        "status": "draft",
        "locked_at": null,
        "approved_at": null,
        "company": { "id": 1, "name": "Company A" }
      }
    ]
  }
}
```

---

### POST /payrolls
Generate / recalculate a payroll draft.

**Request**
```json
{ "company_id": 1, "month": 5, "year": 2026 }
```

**How calculation works**:
1. Base salary from `positions.base_salary`.
2. Proration: unpaid leave days reduce base (base × unpaid_days / std_days).
3. Trip bonus: km × bonus_per_km per applicable rule tier.
4. Overtime pay: OT hours × hourly_rate × multiplier (150%/200%/300%).
5. Night shift allowance: night hours × hourly_rate × differential%.
6. Public holiday pay: 300% daily rate for each holiday worked.
7. Deductions: insurance (10.5%), confirmed violations, unpaid leave.
8. Net = base + trip_bonus + ot_pay + night_allowance + ph_pay + allowance − deduction − leave_deduction − violation_deduction − fuel_cost − tax.

**Response 201** — payroll + lines array.

---

### GET /payrolls/{id}
Get payroll detail with lines.

---

### PUT/PATCH /payrolls/{id}
Update payroll notes or status (draft only).

---

### DELETE /payrolls/{id}
Delete a draft payroll.

---

### POST /payrolls/{id}/approve
Approve a draft payroll. Status changes `draft → approved`.

**SoD**: the creator of the payroll cannot be the approver.

**Response 200**
```json
{ "success": true, "message": "Payroll approved.", "data": { "status": "approved", ... } }
```

---

### POST /payrolls/{id}/lock
Lock an approved payroll. Status changes `approved → locked`. Creates `snapshot_json`.

**Response 200**
```json
{ "success": true, "message": "Payroll locked.", "data": { "status": "locked", "locked_at": "2026-05-31T23:59:00Z" } }
```

---

### GET /payrolls/{id}/export
Export payroll as CSV.

**Response 200** — CSV file download.

---

### GET /payrolls/my-salary  *(auth required, any role)*
Get current user's payroll line for a period.

**Query params**: `month`, `year`

**Response 200**
```json
{
  "success": true,
  "data": {
    "payroll": { "month": 5, "year": 2026, "status": "locked" },
    "line": {
      "base_salary": 10000000,
      "trip_bonus": 2500000,
      "overtime_pay": 450000,
      "night_shift_allowance": 200000,
      "public_holiday_pay": 0,
      "allowance": 500000,
      "deduction": 1050000,
      "leave_unpaid_deduction": 0,
      "violation_deduction": 0,
      "fuel_cost": 300000,
      "tax": 0,
      "net_salary": 12300000,
      "working_days": 22,
      "leave_days_paid": 0,
      "leave_days_unpaid": 0,
      "overtime_hours": 5,
      "trips_completed_count": 15,
      "total_distance_km": 3200.5
    }
  }
}
```

---

## Payroll UI Spec (for Frontend Design)

Use this section as the UI contract for payroll list/detail screens.

### A. Payroll List Screen (`/payrolls`)

Recommended table columns:

| Column | Source field | Format | Notes |
|---|---|---|---|
| Payroll ID | `id` | number | Click to open detail |
| Company | `company.name` | text | fallback: `company_id` |
| Period | `month`, `year` | `MM/YYYY` | example: `04/2026` |
| Status | `status` | badge | `draft`, `approved`, `locked` |
| Approved At | `approved_at` | datetime | nullable |
| Locked At | `locked_at` | datetime | nullable |
| Notes | `notes` | text (truncate) | max 1 line in list |
| Actions | N/A | buttons | View / Approve / Lock / Export |

Filter bar:
- `company_id` (select)
- `month` (select 1-12)
- `year` (select/input)
- `status` (select)

Status badge mapping:
- `draft` -> neutral (gray)
- `approved` -> warning/info (amber/blue)
- `locked` -> success/final (green)

---

### B. Payroll Detail Screen (`/payrolls/{id}`)

Layout suggestion:

1. **Header Summary**
   - Period, company, status, approved_at, locked_at, notes
2. **Line Items Table** (one row per driver)
3. **Totals Footer** (sum money columns + sum KPI columns)
4. **Action Bar** (approve/lock/export depending on status and role)

Line items table columns (recommended order):

| Group | Column | Source field | Format |
|---|---|---|---|
| Driver | Driver ID | `driver_id` | number |
| Driver | Driver Name | `driver.name` | text |
| Earnings | Base Salary | `base_salary` | currency |
| Earnings | Trip Bonus | `trip_bonus` | currency |
| Earnings | Overtime Pay | `overtime_pay` | currency |
| Earnings | Night Shift Allowance | `night_shift_allowance` | currency |
| Earnings | Public Holiday Pay | `public_holiday_pay` | currency |
| Earnings | Allowance | `allowance` | currency |
| Deductions | Insurance/General Deduction | `deduction` | currency |
| Deductions | Unpaid Leave Deduction | `leave_unpaid_deduction` | currency |
| Deductions | Violation Deduction | `violation_deduction` | currency |
| Deductions | Fuel Cost | `fuel_cost` | currency |
| Deductions | Tax | `tax` | currency |
| Result | Net Salary | `net_salary` | currency (highlight) |
| KPI | Working Days | `working_days` | number |
| KPI | Paid Leave Days | `leave_days_paid` | number |
| KPI | Unpaid Leave Days | `leave_days_unpaid` | number |
| KPI | Overtime Hours | `overtime_hours` | number (1 decimal) |
| KPI | Trips Completed | `trips_completed_count` | number |
| KPI | Total Distance | `total_distance_km` | number (km) |

Totals footer (minimum):
- `total_base_salary`
- `total_trip_bonus`
- `total_overtime_pay`
- `total_allowance`
- `total_deduction` (sum of all deduction columns)
- `total_net_salary`
- `total_trips_completed`
- `total_distance_km`

---

### C. Action/State Rules (important for button enable/disable)

- If status = `draft`:
  - Show: `Approve`, `Delete`, `Edit Notes`, `Export`
  - Hide/Disable: `Lock`
- If status = `approved`:
  - Show: `Lock`, `Export`
  - Disable: editable fields
- If status = `locked`:
  - Show: `Export` only
  - Disable all mutation actions

Role constraints:
- Admin can call approve/lock/export.
- Driver/staff should only use `/payrolls/my-salary`.

SoD rule:
- User who created payroll must not approve same payroll (expect `403`).

---

### D. FE Data Types & Formatting Rules

- Currency: render VND with thousand separators, no decimals by default.
- Decimal fields:
  - `overtime_hours`: keep 1-2 decimals.
  - `total_distance_km`: keep 1-2 decimals.
- Null datetime (`approved_at`, `locked_at`): show `-`.
- Large payload:
  - Use server-side pagination for list.
  - For detail, enable column pinning and horizontal scroll.

---

### E. Suggested TypeScript Interfaces

```ts
type PayrollStatus = "draft" | "approved" | "locked";

interface PayrollSummaryItem {
  id: number;
  company_id: number;
  month: number;
  year: number;
  status: PayrollStatus;
  approved_at: string | null;
  locked_at: string | null;
  notes: string | null;
  company?: { id: number; name: string };
}

interface PayrollLineItem {
  driver_id: number;
  driver?: { id: number; name: string };
  base_salary: number;
  trip_bonus: number;
  overtime_pay: number;
  night_shift_allowance: number;
  public_holiday_pay: number;
  allowance: number;
  deduction: number;
  leave_unpaid_deduction: number;
  violation_deduction: number;
  fuel_cost: number;
  tax: number;
  net_salary: number;
  working_days: number;
  leave_days_paid: number;
  leave_days_unpaid: number;
  overtime_hours: number;
  trips_completed_count: number;
  total_distance_km: number;
}
```

---

## 8. Master Data  *(admin required)*

Standard CRUD for all master data resources. All support `GET /`, `POST /`, `GET /{id}`, `PUT /{id}`, `DELETE /{id}`.

| Resource | Path | Notes |
|----------|------|-------|
| Companies | `/companies` | Top-level tenant |
| Offices | `/offices` | Cost/profit centers |
| Departments | `/departments` | |
| Positions | `/positions` | Contains `base_salary` |
| Drivers | `/drivers` | Core driver record |
| Vehicles | `/vehicles` | Fleet |
| Vehicle Assignments | `/vehicle_assignments` | Driver ↔ vehicle |
| Vehicle Expenses | `/vehicle_expenses` | Fuel, maintenance |
| Customers | `/customers` | Freight customers |
| Trips | `/trips` | Individual hauls |
| Trip Bonus Rules | `/trip_bonus_rules` | km-tier bonus config |
| Invoices | `/invoices` | Customer invoices |

---

## 9. Reports  *(admin required)*

### GET /reports/dashboard
Summary metrics: total drivers, trips this month, revenue, payroll cost.

### GET /reports/payroll-summary
Payroll cost breakdown by company/office for a period.

**Query params**: `company_id` (required), `month`, `year`

---

## 10. Users & RBAC  *(admin required)*

### GET /users / POST /users / GET /users/{id} / PUT /users/{id} / DELETE /users/{id}
User management.

### GET /roles / POST /roles / GET /roles/{id} / PUT /roles/{id} / DELETE /roles/{id}
Role management.

### POST /roles/{id}/permissions
Sync permissions for a role.

**Request**
```json
{ "permissions": ["payrolls.approve", "drivers.edit"] }
```

### GET /permissions
List all available permissions.

---

## 11. AI Business Assist  *(admin required)*

### POST /ai/business-assist
Ask the AI advisor a business question.

**Request**
```json
{ "question": "Which drivers have the highest fuel cost this month?" }
```

**Response 200**
```json
{
  "success": true,
  "data": { "answer": "Driver Nguyen Van A has the highest fuel cost at 1,500,000 VND..." }
}
```

---

## 12. Chat  *(auth required)*

### GET /chat/sessions
List chat sessions for current user.

### DELETE /chat/sessions/{sessionId}
Delete a chat session.

### GET /chat/messages
List messages. Query: `session_id`.

### POST /chat/messages
Send a message.

**Request**
```json
{ "session_id": "abc-123", "content": "Hello" }
```

### POST /chat/messages/stream
Stream a chat response (SSE).

---

## Error Codes Reference

| HTTP Code | Meaning |
|-----------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad request |
| 401 | Unauthenticated — missing or invalid token |
| 403 | Forbidden — insufficient role or SoD violation |
| 404 | Resource not found |
| 409 | Conflict — schedule/vehicle duplicate |
| 422 | Validation failed — see `errors` field |
| 500 | Server error |

---

## Legacy Compatibility Endpoints

These endpoints are kept for FE backward compatibility:

- `GET /documentation` → returns links to Swagger UI/OpenAPI sources
- `GET /employees` → alias mapped to drivers list payload
- `GET /allowances` → compatibility endpoint (currently returns empty array)
- `GET /deductions` → compatibility endpoint (currently returns empty array)

---

## Common Pagination Response

All list endpoints return paginated results:

```json
{
  "current_page": 1,
  "data": [...],
  "first_page_url": "...",
  "last_page": 5,
  "last_page_url": "...",
  "next_page_url": "...",
  "per_page": 20,
  "prev_page_url": null,
  "total": 98
}
```

Pass `?page=2` to navigate pages.

---

## Date / Time Formats

| Field type | Format |
|-----------|--------|
| Date | `YYYY-MM-DD` (e.g. `2026-05-01`) |
| Datetime | `YYYY-MM-DD HH:MM:SS` (e.g. `2026-05-01 07:00:00`) |
| Time | `HH:MM` (e.g. `07:00`) |
| Currency | Integer VND, no decimal (e.g. `10000000`) |

All timestamps in API responses are UTC ISO 8601. Frontend should convert to local timezone (`Asia/Ho_Chi_Minh`) for display.
