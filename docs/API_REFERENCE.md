# API Reference — Ship App Backend

> Tài liệu này được tổng hợp trực tiếp từ source code FormRequest + route:list.  
> **Base URL:** `/api` · **Auth:** `Authorization: Bearer {token}` · **Content-Type:** `application/json`

---

## Quy ước chung

### Response wrapper
```json
{ "success": true,  "message": "...", "data": { ... } }
{ "success": false, "message": "...", "errors": { "field": ["msg"] } }
```

### Pagination
```json
{ "data": [...], "current_page": 1, "last_page": 5, "per_page": 15, "total": 72 }
```

### Ký hiệu cột Required
- ✅ = required  
- ⬜ = nullable / optional

---

## 1. Auth

### `POST /auth/login` — Public · throttle 5/phút

| Field | Type | R | Rules |
|-------|------|---|-------|
| `email` | string | ✅ | email |
| `password` | string | ✅ | - |

**Response 200**
```json
{
  "data": {
    "access_token": "1|abc...",
    "refresh_token": "def...(64 chars)",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": { "id": 1, "username": "admin", "email": "...", "status": "active", "roles": [] }
  }
}
```

---

### `POST /auth/register` — Admin only

| Field | Type | R | Rules |
|-------|------|---|-------|
| `username` | string | ✅ | max:100, unique:users, regex `^[a-zA-Z0-9_]+$` |
| `email` | string | ✅ | email:rfc,dns, max:255, unique:users |
| `password` | string | ✅ | confirmed, min:8, mixedCase, numbers, uncompromised |
| `password_confirmation` | string | ✅ | khớp password |

**Response 201**
```json
{ "data": { "id": 10, "username": "newuser", "email": "...", "status": "active", "roles": [] } }
```

---

### `POST /auth/logout` — Auth required · no body

**Response 200** `{ "data": null }`

---

### `POST /auth/refresh` — Auth required · no body

**Response 200**
```json
{ "data": { "access_token": "...", "refresh_token": "...", "token_type": "Bearer", "expires_in": 3600 } }
```

---

### `POST /auth/refresh-token` — Public · throttle 20/phút

| Field | Type | R | Rules |
|-------|------|---|-------|
| `refresh_token` | string | ✅ | size:64 |

**Response 200** — giống `/auth/refresh`

---

### `POST /auth/forgot-password` — Public · throttle 3/phút

| Field | Type | R | Rules |
|-------|------|---|-------|
| `email` | string | ✅ | email |

**Response 200** `{ "data": null }`

---

### `POST /auth/reset-password` — Public · throttle 5/phút

| Field | Type | R | Rules |
|-------|------|---|-------|
| `token` | string | ✅ | - |
| `email` | string | ✅ | email |
| `password` | string | ✅ | confirmed, min:6 |
| `password_confirmation` | string | ✅ | - |

**Response 200** `{ "data": null }`

---

### `POST /auth/social/login` — Public · throttle 10/phút

| Field | Type | R | Rules |
|-------|------|---|-------|
| `provider` | string | ✅ | `google` \| `facebook` \| `apple` |
| `access_token` | string | ⬜ | required_without:id_token |
| `id_token` | string | ⬜ | required_without:access_token |

**Response 200** — giống `/auth/login`

---

### `GET /auth/me` — Auth required

**Response 200**
```json
{
  "data": {
    "user": {
      "id": 1, "username": "admin", "email": "...", "status": "active",
      "driver": { "id": 3, "license_no": "B2-123456", "available_status": "available" },
      "roles": [{ "id": 1, "name": "admin", "permissions": [{ "id": 1, "name": "schedule.approve" }] }]
    },
    "tenants": [{ "company_id": 1, "company_name": "Công ty TNHH ABC" }]
  }
}
```

---

### `GET /auth/sessions` — Auth required

| Query | Type | R | Rules |
|-------|------|---|-------|
| `per_page` | integer | ⬜ | default 10 |

**Response 200** — paginated sessions

---

### `GET /auth/sessions/summary` — Auth required · no params

**Response 200** `{ "data": { "total_sessions": 3, "active_sessions": 2, "last_login_at": "..." } }`

---

### `POST /auth/sessions/{sessionId}/revoke` — Auth required · no body
### `POST /auth/sessions/{sessionId}/lock-account` — Auth required · no body

**Response 200** `{ "data": null }`

---

### `GET /auth/logs` — Auth required

| Query | Type | R | Rules |
|-------|------|---|-------|
| `date` | date | ⬜ | YYYY-MM-DD |

---

### `GET /auth/actions` — Auth required

| Query | Type | R | Rules |
|-------|------|---|-------|
| `username` | string | ⬜ | max:100 |
| `action` | string | ⬜ | max:150 |
| `from` | date | ⬜ | - |
| `to` | date | ⬜ | after_or_equal:from |
| `status_code` | integer | ⬜ | between:100,599 |

---

## 2. Companies — Admin only

### `GET /companies`

| Query | Type | R | Mô tả |
|-------|------|---|-------|
| `search` | string | ⬜ | tìm `code`, `name` |
| `status` | string | ⬜ | `active` \| `inactive` |
| `sort` | string | ⬜ | `id` `code` `name` `status` `created_at` (prefix `-` = DESC) |
| `per_page` | integer | ⬜ | - |

---

### `POST /companies`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `code` | string | ✅ | max:50, unique:companies,code |
| `name` | string | ✅ | max:255 |
| `tax_code` | string | ⬜ | max:50 |
| `address` | string | ⬜ | - |
| `phone` | string | ⬜ | max:20 |
| `email` | string | ⬜ | email |
| `status` | string | ✅ | `active` \| `inactive` |

**Response 201**
```json
{
  "data": {
    "id": 1, "code": "CTY001", "name": "Công ty TNHH ABC",
    "tax_code": "0123456789", "address": "...", "phone": "0901234567",
    "email": "contact@abc.com", "status": "active",
    "created_at": "2026-04-25T08:00:00.000000Z", "updated_at": "..."
  }
}
```

---

### `GET /companies/{id}` — Response: company object
### `PUT /companies/{id}` — tất cả field optional (`sometimes`), cùng field như POST
### `DELETE /companies/{id}` — Response 200 `{ "data": null }`

---

## 3. Offices — Admin only

### `GET /offices`

| Query | Type | R | Mô tả |
|-------|------|---|-------|
| `search` | string | ⬜ | tìm `code`, `name` |
| `company_id` | integer | ⬜ | - |
| `sort` | string | ⬜ | - |
| `per_page` | integer | ⬜ | - |

---

### `POST /offices`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `company_id` | integer | ✅ | exists:companies,id |
| `code` | string | ✅ | max:50 |
| `name` | string | ✅ | max:255 |
| `address` | string | ⬜ | - |
| `manager_id` | integer | ⬜ | exists:drivers,id |

**Response 201**
```json
{
  "data": {
    "id": 2, "company_id": 1, "code": "VP001", "name": "Văn phòng Hà Nội",
    "address": "456 Đường Láng", "manager_id": 3,
    "company": { "id": 1, "name": "Công ty TNHH ABC" }
  }
}
```

---

### `GET /offices/{id}` — Office + `company` + `manager`
### `PUT /offices/{id}` — tất cả optional, cùng field như POST
### `DELETE /offices/{id}`

---

### `POST /offices/{office}/apply-schedule`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `schedule_id` | integer | ✅ | exists:work_schedule_templates,id |
| `start_date` | date | ✅ | YYYY-MM-DD |
| `end_date` | date | ✅ | after_or_equal:start_date, max range 120 ngày |
| `notes` | string | ⬜ | max:500 |
| `replace_drafts` | boolean | ⬜ | default false |

**Response 200 — sync (≤8000 rows)**
```json
{ "data": { "created_count": 120, "updated_count": 15, "drivers_count": 28 } }
```
**Response 200 — async (>8000 rows)**
```json
{ "data": { "queued": true, "estimated_rows": 12500, "driver_count": 50, "day_count": 250 } }
```

---

## 4. Departments — Admin only

### `GET /departments`

| Query | Type | R | Mô tả |
|-------|------|---|-------|
| `search` | string | ⬜ | tìm `code`, `name` |
| `office_id` | integer | ⬜ | - |
| `sort` | string | ⬜ | - |
| `per_page` | integer | ⬜ | - |

---

### `POST /departments`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `office_id` | integer | ✅ | exists:offices,id |
| `parent_id` | integer | ⬜ | exists:departments,id |
| `code` | string | ✅ | max:50 |
| `name` | string | ✅ | max:255 |

**Response 201**
```json
{ "data": { "id": 5, "office_id": 2, "parent_id": null, "code": "DEPT001", "name": "Phòng Vận Hành" } }
```

---

### `GET /departments/{id}` — Department + `office` + `parent`
### `PUT /departments/{id}` — tất cả optional (`sometimes`)
### `DELETE /departments/{id}`

---

## 5. Positions — Admin only

### `GET /positions` — Query: `search`, `sort`, `per_page`

### `POST /positions`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `company_id` | integer | ✅ | exists:companies,id |
| `code` | string | ✅ | max:50, unique:positions,code |
| `name` | string | ✅ | max:255 |
| `base_salary` | numeric | ✅ | min:0 |
| `level` | integer | ⬜ | min:0 |

**Response 201**
```json
{ "data": { "id": 3, "company_id": 1, "code": "DRV_SR", "name": "Tài Xế Cao Cấp", "base_salary": 9000000, "level": 2 } }
```

### `GET /positions/{id}` / `PUT /positions/{id}` / `DELETE /positions/{id}`
PUT: tất cả optional, `code` unique bỏ qua ID hiện tại.

---

## 6. Drivers — Admin only

### `GET /drivers`

| Query | Type | R | Mô tả |
|-------|------|---|-------|
| `search` | string | ⬜ | tìm `license_no` |
| `employee_id` | integer | ⬜ | - |
| `available_status` | string | ⬜ | `available` \| `busy` \| `offline` |
| `sort` | string | ⬜ | - |
| `per_page` | integer | ⬜ | - |

Response: paginated + relations `office`, `department`, `position`, `user`

---

### `POST /drivers`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `code` | string | ✅ | max:50, unique:drivers,code |
| `name` | string | ✅ | max:255 |
| `email` | string | ⬜ | email, unique:drivers,email |
| `phone` | string | ⬜ | max:20, regex `^0[0-9]{9,10}$` |
| `dob` | date | ⬜ | YYYY-MM-DD |
| `gender` | string | ⬜ | `male` \| `female` \| `other` |
| `address` | string | ⬜ | - |
| `avatar_url` | string | ⬜ | url, max:255 |
| `national_id_no` | string | ⬜ | max:30 |
| `national_id_issue_date` | date | ⬜ | - |
| `national_id_issue_place` | string | ⬜ | max:255 |
| `social_insurance_no` | string | ⬜ | max:30 |
| `health_insurance_no` | string | ⬜ | max:30 |
| `insurance_registered_at` | date | ⬜ | - |
| `office_id` | integer | ✅ | exists:offices,id |
| `department_id` | integer | ⬜ | exists:departments,id |
| `position_id` | integer | ✅ | exists:positions,id |
| `status` | string | ✅ | `active` \| `inactive` \| `resigned` |
| `join_date` | date | ✅ | YYYY-MM-DD |
| `resign_date` | date | ⬜ | after_or_equal:join_date |
| `bank_name` | string | ⬜ | max:255 |
| `bank_account_no` | string | ⬜ | max:50 |
| `bank_account_name` | string | ⬜ | max:255 |
| `license_no` | string | ✅ | max:50 |
| `license_class` | string | ⬜ | max:20 |
| `expired_date` | date | ⬜ | ngày hết hạn bằng lái |
| `license_image_url` | string | ⬜ | url, max:255 |
| `identity_image_url` | string | ⬜ | url, max:255 |
| `driver_insurance_no` | string | ⬜ | max:30 |
| `driver_insurance_expired_date` | date | ⬜ | - |
| `health_certificate_no` | string | ⬜ | max:30 |
| `health_certificate_expired_date` | date | ⬜ | - |
| `available_status` | string | ✅ | `available` \| `busy` \| `offline` |

**Response 201**
```json
{
  "data": {
    "id": 3, "code": "DRV001", "name": "Nguyễn Văn A",
    "license_no": "B2-123456", "license_class": "B2", "available_status": "available",
    "status": "active", "join_date": "2026-01-01",
    "office": { "id": 2, "name": "Văn phòng Hà Nội" },
    "department": { "id": 5, "name": "Phòng Vận Hành" },
    "position": { "id": 3, "name": "Tài Xế Cao Cấp" }
  }
}
```

---

### `GET /drivers/{id}` — Driver + `office` + `department` + `position` + `user`
### `PUT /drivers/{id}` — tất cả optional, unique bỏ qua ID hiện tại
### `DELETE /drivers/{id}`

---

## 7. Vehicles — Admin only

### `GET /vehicles`

| Query | Type | R | Mô tả |
|-------|------|---|-------|
| `search` | string | ⬜ | tìm `plate_number`, `brand`, `model` |
| `office_id` | integer | ⬜ | - |
| `status` | string | ⬜ | `active` \| `maintenance` \| `inactive` |
| `sort` | string | ⬜ | - |
| `per_page` | integer | ⬜ | - |

---

### `POST /vehicles`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `office_id` | integer | ✅ | exists:offices,id |
| `plate_number` | string | ✅ | max:20, unique:vehicles,plate_number |
| `type` | string | ✅ | `truck` \| `van` \| `car` \| `motorcycle` |
| `brand` | string | ⬜ | max:100 |
| `model` | string | ⬜ | max:100 |
| `year` | integer | ⬜ | min:1900, max:2100 |
| `capacity` | integer | ⬜ | min:0 |
| `status` | string | ✅ | `active` \| `maintenance` \| `inactive` |
| `image_front` | string | ⬜ | url, max:255 |
| `image_back` | string | ⬜ | url, max:255 |
| `image_side` | string | ⬜ | url, max:255 |
| `image_other` | string | ⬜ | url, max:255 |

**Response 201**
```json
{
  "data": {
    "id": 4, "office_id": 2, "plate_number": "51A-12345",
    "type": "van", "brand": "Toyota", "model": "Hiace",
    "year": 2022, "capacity": 16, "status": "active",
    "image_front": null, "image_back": null, "image_side": null, "image_other": null,
    "office": { "id": 2, "name": "Văn phòng Hà Nội" }
  }
}
```

---

### `GET /vehicles/{id}` — Vehicle + `office`
### `PUT /vehicles/{id}` — tất cả optional, unique bỏ qua ID hiện tại
### `DELETE /vehicles/{id}` — Response 422 nếu xe đang có assignment

---

## 8. Vehicle Assignments — Admin only

### `GET /vehicle_assignments` — Query: `vehicle_id`, `driver_id`, `sort`, `per_page`

### `POST /vehicle_assignments`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `vehicle_id` | integer | ✅ | exists:vehicles,id |
| `driver_id` | integer | ✅ | exists:drivers,id |
| `from_date` | date | ✅ | YYYY-MM-DD |
| `to_date` | date | ⬜ | after_or_equal:from_date |

> Kiểm tra **overlap thời gian** cho cả xe và tài xế — 422 nếu trùng.

**Response 201**
```json
{
  "data": {
    "id": 10, "vehicle_id": 4, "driver_id": 3,
    "from_date": "2026-04-25", "to_date": null,
    "vehicle": { "id": 4, "plate_number": "51A-12345" },
    "driver": { "id": 3, "name": "Nguyễn Văn A" }
  }
}
```

### `GET /vehicle_assignments/{id}` / `PUT /vehicle_assignments/{id}` / `DELETE /vehicle_assignments/{id}`
PUT fields: `vehicle_id`(sometimes), `driver_id`(sometimes), `from_date`(sometimes), `to_date`(nullable).

---

## 9. Vehicle Expenses — Admin only

### `GET /vehicle_expenses` — Query: `vehicle_id`, `driver_id`, `type`, `sort`, `per_page`

### `POST /vehicle_expenses`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `vehicle_id` | integer | ✅ | exists:vehicles,id |
| `driver_id` | integer | ⬜ | exists:drivers,id |
| `type` | string | ✅ | `fuel` \| `maintenance` \| `repair` \| `toll` \| `parking` \| `other` |
| `amount` | numeric | ✅ | min:0 |
| `expense_date` | date | ✅ | YYYY-MM-DD |
| `note` | string | ⬜ | - |

**Response 201**
```json
{
  "data": {
    "id": 20, "vehicle_id": 4, "driver_id": 3,
    "type": "fuel", "amount": 500000,
    "expense_date": "2026-04-25", "note": "Đổ xăng tuyến HN-HCM"
  }
}
```

### `GET /vehicle_expenses/{id}` / `PUT /vehicle_expenses/{id}` / `DELETE /vehicle_expenses/{id}`
PUT: tất cả optional (`sometimes`).

---

## 10. Customers — Admin only

### `GET /customers`

| Query | Type | R | Mô tả |
|-------|------|---|-------|
| `search` | string | ⬜ | tìm `name`, `tax_code`, `email` |
| `type` | string | ⬜ | `individual` \| `company` |
| `sort` | string | ⬜ | - |
| `per_page` | integer | ⬜ | - |

---

### `POST /customers`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `company_id` | integer | ✅ | exists:companies,id |
| `type` | string | ✅ | `individual` \| `company` |
| `name` | string | ✅ | max:255 |
| `tax_code` | string | ⬜ | max:50 |
| `phone` | string | ⬜ | max:20 |
| `email` | string | ⬜ | email |
| `address` | string | ⬜ | - |

**Response 201**
```json
{
  "data": {
    "id": 8, "company_id": 1, "type": "company",
    "name": "Tập đoàn XYZ", "tax_code": "0987654321",
    "phone": "0981234567", "email": "contact@xyz.com", "address": "789 Đường Nguyễn Huệ"
  }
}
```

### `GET /customers/{id}` / `PUT /customers/{id}` / `DELETE /customers/{id}`
PUT: tất cả optional (`sometimes`).

---

## 11. Trips — Admin only

### `GET /trips`

| Query | Type | R | Mô tả |
|-------|------|---|-------|
| `search` | string | ⬜ | tìm `code`, `start_point`, `end_point` |
| `customer_id` | integer | ⬜ | - |
| `driver_id` | integer | ⬜ | - |
| `vehicle_id` | integer | ⬜ | - |
| `status` | string | ⬜ | `pending` \| `in_progress` \| `completed` \| `cancelled` |
| `office_id` | integer | ⬜ | lọc theo văn phòng của xe |
| `sort` | string | ⬜ | - |
| `per_page` | integer | ⬜ | - |

Response: paginated + `customer`, `driver`, `vehicle`

---

### `POST /trips`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `code` | string | ✅ | max:50, unique:trips,code |
| `customer_id` | integer | ✅ | exists:customers,id |
| `driver_id` | integer | ✅ | exists:drivers,id — không có trip `in_progress` khác |
| `vehicle_id` | integer | ✅ | exists:vehicles,id — không có trip `in_progress` khác |
| `start_point` | string | ✅ | max:255, khác `end_point` |
| `end_point` | string | ✅ | max:255 |
| `distance_km` | numeric | ⬜ | min:0 |
| `start_time` | datetime | ⬜ | bắt buộc nếu status = `in_progress` hoặc `completed` |
| `end_time` | datetime | ⬜ | after_or_equal:start_time — bắt buộc nếu status = `completed` |
| `price` | numeric | ⬜ | min:0 |
| `status` | string | ✅ | `pending` \| `in_progress` \| `completed` \| `cancelled` |

**Response 201**
```json
{
  "data": {
    "id": 45, "code": "TRIP-2026-001",
    "customer_id": 3, "driver_id": 5, "vehicle_id": 2,
    "start_point": "Hà Nội", "end_point": "TP.HCM",
    "distance_km": 1700.5, "start_time": null, "end_time": null,
    "price": 5000000, "status": "pending",
    "customer": { "id": 3, "name": "Tập đoàn XYZ" },
    "driver": { "id": 5, "name": "Nguyễn Văn A" },
    "vehicle": { "id": 2, "plate_number": "51A-12345" }
  }
}
```

---

### `GET /trips/{id}` — Trip + `customer` + `driver` + `vehicle`

---

### `PUT /trips/{id}`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `code` | string | ⬜ | max:50, unique (bỏ qua ID hiện tại) |
| `customer_id` | integer | ⬜ | exists:customers,id |
| `driver_id` | integer | ⬜ | exists:drivers,id |
| `vehicle_id` | integer | ⬜ | exists:vehicles,id |
| `start_point` | string | ⬜ | max:255 |
| `end_point` | string | ⬜ | max:255 |
| `distance_km` | numeric | ⬜ | min:0 |
| `start_time` | datetime | ⬜ | - |
| `end_time` | datetime | ⬜ | after_or_equal:start_time |
| `price` | numeric | ⬜ | min:0 |
| `status` | string | ⬜ | chỉ chuyển hợp lệ: `pending→pending/in_progress/cancelled`, `in_progress→in_progress/completed/cancelled` |

---

### `DELETE /trips/{id}`

---

### `POST /trips/{id}/assign`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `driver_id` | integer | ✅ | exists:drivers,id |

Response: trip updated, ghi `trip_status_histories`

---

### `POST /trips/{id}/start` — no body
Status `pending/in_progress` → `in_progress`. Tự set `start_time = now()`.

### `POST /trips/{id}/pickup` — no body
Ghi event `pickup` vào `trip_status_histories`.

### `POST /trips/{id}/transit` — no body
Ghi event `transit` vào `trip_status_histories`.

### `POST /trips/{id}/arrive` — no body
Ghi event `arrived` vào `trip_status_histories`.

### `POST /trips/{id}/complete` — no body
Status → `completed`. Tự set `end_time = now()`. 422 nếu status = `cancelled`.

### `POST /trips/{id}/cancel`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `reason` | string | ⬜ | max:500 |

Status → `cancelled`. 422 nếu đã `completed`.

### `POST /trips/{id}/delay`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `reason` | string | ⬜ | max:500 |

Ghi event `delayed`.

### `POST /trips/{id}/resume` — no body
Ghi event `in_progress` (sau delay).

---

## 12. Trip Bonus Rules — Admin only

### `GET /trip_bonus_rules` — Query: `company_id`, `sort`, `per_page`

### `POST /trip_bonus_rules`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `company_id` | integer | ✅ | exists:companies,id (tự lấy từ tenant nếu không gửi) |
| `effective_from` | date | ✅ | mặc định today nếu không gửi |
| `effective_to` | date | ⬜ | after_or_equal:effective_from |
| `min_km` | numeric | ✅ | min:0 |
| `max_km` | numeric | ⬜ | gt:min_km |
| `bonus_per_km` | numeric | ✅ | min:0 |

**Response 201**
```json
{
  "data": {
    "id": 5, "company_id": 1,
    "effective_from": "2026-04-25", "effective_to": null,
    "min_km": 100, "max_km": 500, "bonus_per_km": 1500
  }
}
```

### `PUT /trip_bonus_rules/{id}` — tất cả optional
### `DELETE /trip_bonus_rules/{id}`

---

## 13. Invoices — Admin only

### `GET /invoices`

| Query | Type | R | Mô tả |
|-------|------|---|-------|
| `search` | string | ⬜ | tìm `code` |
| `trip_id` | integer | ⬜ | - |
| `customer_id` | integer | ⬜ | - |
| `status` | string | ⬜ | `draft` \| `issued` \| `paid` \| `cancelled` |
| `sort` | string | ⬜ | - |
| `per_page` | integer | ⬜ | - |

---

### `POST /invoices`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `code` | string | ✅ | max:50, unique:invoices,code |
| `customer_id` | integer | ✅ | exists:customers,id |
| `trip_id` | integer | ⬜ | exists:trips,id — trip phải `completed`, chưa có invoice, `customer_id` phải khớp trip |
| `subtotal` | numeric | ✅ | min:0 |
| `vat_rate` | numeric | ⬜ | min:0, max:100 (%) |
| `vat_amount` | numeric | ⬜ | min:0 — tự tính `subtotal × vat_rate / 100` nếu không gửi |
| `total_amount` | numeric | ✅ | min:0 — **phải bằng** `subtotal + vat_amount` (±0.01) |
| `status` | string | ✅ | `draft` \| `issued` \| `paid` \| `cancelled` |
| `issued_at` | date | ⬜ | YYYY-MM-DD |
| `paid_at` | date | ⬜ | after_or_equal:issued_at |

**Response 201**
```json
{
  "data": {
    "id": 30, "code": "INV-2026-001", "customer_id": 3, "trip_id": 45,
    "subtotal": 5000000, "vat_rate": 10, "vat_amount": 500000, "total_amount": 5500000,
    "status": "draft", "issued_at": null, "paid_at": null,
    "customer": { "id": 3, "name": "Tập đoàn XYZ" },
    "trip": { "id": 45, "code": "TRIP-2026-001" }
  }
}
```

---

### `GET /invoices/{id}` — Invoice + `customer` + `trip`

### `PUT /invoices/{id}` — tất cả optional, cùng ràng buộc trip/total như POST

### `DELETE /invoices/{id}`

### `POST /invoices/{id}/issue` — no body
Status → `issued`, set `issued_at = now()`. 422 nếu không phải `draft`.

### `POST /invoices/{id}/mark-paid` — no body
Status → `paid`, set `paid_at = now()`.

### `POST /invoices/{id}/send-cqt` — no body
Gửi lên cơ quan thuế.

### `POST /invoices/{id}/cancel` — no body
Status → `cancelled`. 422 nếu đã `paid`.

---

## 14. Payrolls — Admin only

### `GET /payrolls`

| Query | Type | R | Mô tả |
|-------|------|---|-------|
| `company_id` | integer | ⬜ | - |
| `month` | integer | ⬜ | 1–12 |
| `year` | integer | ⬜ | - |
| `status` | string | ⬜ | `draft` \| `approved` \| `locked` \| `paid` |
| `sort` | string | ⬜ | - |
| `per_page` | integer | ⬜ | - |

Response: paginated + `company`

---

### `POST /payrolls`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `company_id` | integer | ✅ | exists:companies,id |
| `month` | integer | ✅ | min:1, max:12 |
| `year` | integer | ✅ | min:2000, max:2100 |

**Response 201**
```json
{
  "data": {
    "id": 5, "company_id": 1, "month": 4, "year": 2026, "status": "draft",
    "company": { "id": 1, "name": "Công ty TNHH ABC" },
    "lines": [
      {
        "id": 20, "driver_id": 3,
        "base_salary": 8000000, "trip_bonus": 1500000,
        "fuel_excess_deduction": 200000, "total": 9300000,
        "driver": { "id": 3, "name": "Nguyễn Văn A" }
      }
    ]
  }
}
```

---

### `GET /payrolls/{id}` — Payroll + `company` + `lines.driver`

### `PUT /payrolls/{id}`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `status` | string | ⬜ | `draft` \| `approved` \| `locked` |
| `notes` | string | ⬜ | max:1000 |

422 nếu payroll đã `locked`.

### `DELETE /payrolls/{id}` — 422 nếu đã `locked`

### `POST /payrolls/{id}/approve` — no body
`draft` → `approved`. 422 nếu không phải `draft`.

### `POST /payrolls/{id}/lock` — no body
`approved` → `locked`. 422 nếu không phải `approved`.

### `POST /payrolls/{id}/mark-paid` — no body
Set `paid_at = now()`, status → `paid`. 422 nếu chưa `approved`.

### `GET /payrolls/{id}/export` — Response: export payload

---

### `GET /payrolls/my-salary` — Auth required

| Query | Type | R | Rules |
|-------|------|---|-------|
| `month` | integer | ⬜ | min:1, max:12 (default tháng hiện tại) |
| `year` | integer | ⬜ | min:2000, max:2100 |

---

### `GET /payrolls/driver/{driverId}` — Admin only

| Query | Type | R | Rules |
|-------|------|---|-------|
| `month` | integer | ⬜ | min:1, max:12 |
| `year` | integer | ⬜ | min:2000, max:2100 |

---

## 15. Payroll Adjustments — Admin only

### `GET /payroll-adjustments`

| Query | Type | R | Mô tả |
|-------|------|---|-------|
| `driver_id` | integer | ⬜ | - |
| `payroll_id` | integer | ⬜ | - |
| `type` | string | ⬜ | `addition` \| `deduction` |
| `company_id` | integer | ⬜ | - |

---

### `POST /payroll-adjustments`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `payroll_id` | integer | ✅ | exists:payrolls,id |
| `driver_id` | integer | ✅ | exists:drivers,id |
| `type` | string | ✅ | `addition` \| `deduction` |
| `amount` | numeric | ✅ | min:0 |
| `reason` | string | ✅ | max:1000 |
| `category` | string | ⬜ | `violation_refund` \| `leave_restore` \| `ot_late_approval` \| `manual` |
| `source_type` | string | ⬜ | max:50 |
| `source_id` | integer | ⬜ | - |

**Response 201**
```json
{
  "data": {
    "id": 12, "payroll_id": 5, "driver_id": 3,
    "type": "addition", "amount": 500000,
    "reason": "Thưởng KPI", "category": "manual",
    "source_type": null, "source_id": null, "approved_by": null,
    "driver": { "id": 3, "name": "Nguyễn Văn A" },
    "payroll": { "id": 5, "month": 4, "year": 2026 }
  }
}
```

---

### `GET /payroll-adjustments/{id}`

### `PUT /payroll-adjustments/{id}`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `type` | string | ⬜ | `addition` \| `deduction` |
| `amount` | numeric | ⬜ | min:0 |
| `reason` | string | ⬜ | max:1000 |

422 nếu đã approved.

### `DELETE /payroll-adjustments/{id}` — 422 nếu đã approved

### `POST /payroll-adjustments/{id}/approve` — no body
Set `approved_by = current_user_id`.

### `POST /payroll-adjustments/{id}/reject` — no body
Body optional: `reason` (nullable, max:1000). Xóa bản ghi.

---

## 16. Users — Admin only

### `GET /users`

| Query | Type | R | Mô tả |
|-------|------|---|-------|
| `search` | string | ⬜ | tìm `username`, `email` |
| `status` | string | ⬜ | `active` \| `inactive` |
| `sort` | string | ⬜ | - |
| `per_page` | integer | ⬜ | - |

Response: paginated + `driver`, `roles`

---

### `POST /users`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `username` | string | ✅ | max:255, unique:users,username |
| `email` | string | ✅ | email, unique:users,email |
| `password` | string | ✅ | min:6 |
| `avatar_url` | string | ⬜ | url, max:255 |
| `driver_id` | integer | ⬜ | exists:drivers,id |
| `status` | string | ✅ | `active` \| `inactive` |
| `emergency_contact_name` | string | ⬜ | max:255 |
| `emergency_contact_phone` | string | ⬜ | max:20, regex `^0[0-9]{9,10}$` |
| `residential_address` | string | ⬜ | max:500 |

**Response 201**
```json
{
  "data": {
    "id": 15, "username": "driver_an", "email": "an@company.com",
    "avatar_url": null, "driver_id": 3, "status": "active",
    "emergency_contact_name": null, "emergency_contact_phone": null,
    "residential_address": null,
    "driver": { "id": 3, "name": "Nguyễn Văn A" },
    "roles": []
  }
}
```

---

### `GET /users/{id}` — User + `driver` + `roles` + `permissions`

### `PUT /users/{id}` — tất cả optional, unique bỏ qua ID hiện tại, `password` nullable

### `DELETE /users/{id}`

---

## 17. Roles & Permissions — Admin only

### `GET /roles` — Query: `search`(tìm name), `sort`, `per_page`
Response: paginated + `permissions`

### `POST /roles`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `name` | string | ✅ | max:255, unique:roles,name |
| `description` | string | ⬜ | - |

**Response 201** `{ "data": { "id": 3, "name": "dispatcher", "description": "...", "permissions": [] } }`

### `GET /roles/{id}` / `PUT /roles/{id}` / `DELETE /roles/{id}`
PUT: tất cả optional, unique bỏ qua ID hiện tại.

### `POST /roles/{role}/permissions`
Sync toàn bộ permissions — **thay thế**, không append.

| Field | Type | R | Rules |
|-------|------|---|-------|
| `permission_ids` | integer[] | ⬜ | array, mỗi phần tử exists:permissions,id |

**Response 200**
```json
{
  "data": {
    "id": 3, "name": "dispatcher",
    "permissions": [{ "id": 1, "name": "schedule.approve" }]
  }
}
```

### `GET /permissions` — tất cả permissions
### `GET /permissions/{id}`

---

## 18. Attendance — Admin only
> Alias: `/attendance/*` = `/attendances/*`

### `GET /attendance`

| Query | Type | R | Mô tả |
|-------|------|---|-------|
| `driver_id` | integer | ⬜ | - |
| `date` | date | ⬜ | YYYY-MM-DD |
| `from` | date | ⬜ | - |
| `to` | date | ⬜ | - |
| `status` | string | ⬜ | `present` \| `absent` \| `late` \| `half_day` \| `leave` |
| `per_page` | integer | ⬜ | max:200, default 50 |

Response fields per record: `id`, `driver_id`, `date`, `check_in`, `check_out`, `work_hours`, `overtime_hours`, `status`

---

### `POST /attendance/check-in`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `driver_id` | integer | ✅ | exists:drivers,id |
| `check_in_time` | datetime | ✅ | format: `Y-m-d H:i:s` |

**Response 201**
```json
{
  "data": {
    "id": 100, "driver_id": 3, "work_date": "2026-04-25",
    "start_time": "2026-04-25 07:55:00", "end_time": null, "status": "approved"
  }
}
```

---

### `POST /attendance/check-out`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `driver_id` | integer | ✅ | exists:drivers,id |
| `check_out_time` | datetime | ✅ | format: `Y-m-d H:i:s` |

---

### `PATCH /attendance/{id}/adjust`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `check_in` | datetime | ⬜ | format: `Y-m-d H:i:s` |
| `check_out` | datetime | ⬜ | format: `Y-m-d H:i:s`, after:check_in |
| `work_hours` | numeric | ⬜ | min:0, max:24 |
| `overtime_hours` | numeric | ⬜ | min:0, max:8 |
| `status` | string | ⬜ | `present` \| `absent` \| `late` \| `half_day` \| `leave` |
| `reason` | string | ✅* | max:500 — *required_with: check_in, check_out, status |

---

### `GET /attendances/late`

| Query | Type | R | Mô tả |
|-------|------|---|-------|
| `driver_id` | integer | ⬜ | - |
| `from` | date | ⬜ | - |
| `to` | date | ⬜ | - |

---

### `POST /attendances/late/notify`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `from` | date | ⬜ | - |
| `to` | date | ⬜ | after_or_equal:from |
| `driver_ids` | integer[] | ⬜ | array of integers |
| `message` | string | ⬜ | max:500 |

**Response 200** `{ "data": { "queued": true, "scope": { "from": "...", "to": "...", "driver_ids": [] } } }`

---

## 19. Leave — Admin only

### `GET /leave/types` — no params
**Response 200** `{ "data": [{ "id": 1, "name": "Nghỉ phép năm", "code": "ANNUAL" }] }`

---

### `GET /leave`

| Query | Type | R | Mô tả |
|-------|------|---|-------|
| `driver_id` | integer | ⬜ | - |
| `status` | string | ⬜ | `pending` \| `approved` \| `rejected` \| `cancelled` |
| `from` | date | ⬜ | - |
| `to` | date | ⬜ | - |

---

### `POST /leave`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `driver_id` | integer | ✅ | exists:drivers,id |
| `leave_type_id` | integer | ✅ | exists:leave_types,id |
| `from_date` | date | ✅ | after_or_equal:today |
| `to_date` | date | ✅ | after_or_equal:from_date |
| `total_days` | numeric | ✅ | min:0.5, max:365 |
| `reason` | string | ⬜ | max:1000 |
| `attachment_urls` | string[] | ⬜ | array of valid URLs |

**Response 201**
```json
{
  "data": {
    "id": 55, "driver_id": 3, "leave_type_id": 1,
    "from_date": "2026-05-01", "to_date": "2026-05-03",
    "total_days": 3, "reason": "Du lịch gia đình",
    "status": "pending", "attachment_urls": [],
    "driver": { "id": 3, "name": "Nguyễn Văn A" },
    "leave_type": { "id": 1, "name": "Nghỉ phép năm" }
  }
}
```

---

### `GET /leave/{id}` — Leave + `driver` + `leave_type`

### `POST /leave/{leaveRequest}/approve` — no body
> Người duyệt ≠ người tạo đơn. Set `approved_by`, `approved_at`.

### `POST /leave/{leaveRequest}/reject`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `rejection_reason` | string | ✅ | max:1000 |

### `POST /leave/{leaveRequest}/cancel` — no body

---

## 20. Overtime — Admin only

### `GET /overtime`

| Query | Type | R | Mô tả |
|-------|------|---|-------|
| `driver_id` | integer | ⬜ | - |
| `company_id` | integer | ⬜ | - |
| `status` | string | ⬜ | `pending` \| `approved` \| `rejected` |
| `from` | date | ⬜ | - |
| `to` | date | ⬜ | - |

---

### `POST /overtime`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `driver_id` | integer | ✅ | exists:drivers,id |
| `company_id` | integer | ✅ | exists:companies,id |
| `work_date` | date | ✅ | YYYY-MM-DD |
| `start_time` | time | ✅ | format: `H:i` |
| `end_time` | time | ✅ | format: `H:i`, after:start_time |
| `ot_hours` | numeric | ✅ | min:0.5, max:8 |
| `reason` | string | ⬜ | max:500 |

**Response 201**
```json
{
  "data": {
    "id": 25, "driver_id": 3, "company_id": 1,
    "work_date": "2026-04-26", "start_time": "17:00", "end_time": "20:00",
    "ot_hours": 3, "reason": "Hoàn thành hàng giao gấp", "status": "pending"
  }
}
```

---

### `GET /overtime/{id}`

### `POST /overtime/{overtimeRequest}/approve` — no body
> Người duyệt ≠ người tạo. Set `approved_by`, `approved_at`.

### `POST /overtime/{overtimeRequest}/reject`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `rejection_reason` | string | ✅ | max:500 |

---

## 21. Violations — Admin only

### `GET /violations`

| Query | Type | R | Mô tả |
|-------|------|---|-------|
| `driver_id` | integer | ⬜ | - |
| `company_id` | integer | ⬜ | - |
| `status` | string | ⬜ | `pending` \| `confirmed` \| `disputed` \| `resolved` \| `waived` |
| `from` | date | ⬜ | - |
| `to` | date | ⬜ | - |

---

### `POST /violations`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `driver_id` | integer | ✅ | exists:drivers,id |
| `company_id` | integer | ✅ | exists:companies,id |
| `trip_id` | integer | ⬜ | exists:trips,id |
| `type` | string | ✅ | `speeding` \| `route_deviation` \| `fuel_misuse` \| `behavior` \| `accident` \| `other` |
| `occurred_at` | date | ✅ | YYYY-MM-DD |
| `description` | string | ✅ | max:2000 |
| `penalty_amount` | numeric | ✅ | min:0 |
| `evidence_urls` | string[] | ⬜ | array of valid URLs |

**Response 201**
```json
{
  "data": {
    "id": 60, "driver_id": 3, "company_id": 1, "trip_id": 45,
    "type": "speeding", "occurred_at": "2026-04-20",
    "description": "Vượt tốc độ 80km/h", "penalty_amount": 1000000,
    "status": "pending", "reporter_id": 1,
    "evidence_urls": ["https://cdn.example.com/img1.jpg"],
    "driver": { "id": 3, "name": "Nguyễn Văn A" }
  }
}
```

---

### `GET /violations/{id}`

### `POST /violations/{violation}/confirm` — no body
> Người xác nhận ≠ người tạo. Set `confirmed_at`, queue trừ lương.

### `POST /violations/{violation}/dispute`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `reason` | string | ✅ | max:2000 |
| `evidence_urls` | string[] | ⬜ | array of valid URLs |

### `POST /violations/{violation}/resolve-dispute`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `resolution` | string | ✅ | `upheld` \| `overturned` |
| `resolution_note` | string | ⬜ | max:1000 |

### `POST /violations/{violation}/waive`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `waive_reason` | string | ✅ | max:1000 |

> Người tha ≠ người tạo. Set `waived_by`, `waived_at`.

---

## 22. Driver Schedules — Admin only

### `GET /driver-schedules`

| Query | Type | R | Rules |
|-------|------|---|-------|
| `driver_id` | integer | ⬜ | exists:drivers,id |
| `office_id` | integer | ⬜ | exists:offices,id |
| `work_date` | date | ⬜ | YYYY-MM-DD |
| `from` | date | ⬜ | required_with:to |
| `to` | date | ⬜ | required_with:from, after_or_equal:from |
| `status` | string | ⬜ | `draft` \| `submitted` \| `approved` \| `locked` |

---

### `POST /driver-schedules`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `driver_id` | integer | ✅ | exists:drivers,id |
| `office_id` | integer | ✅ | exists:offices,id |
| `work_date` | date | ✅ | after_or_equal:today |
| `shift_code` | string | ⬜ | `day` \| `night` \| `split` \| `custom` |
| `start_time` | time | ✅ | format: `H:i` |
| `end_time` | time | ✅ | format: `H:i` |
| `vehicle_id` | integer | ⬜ | exists:vehicles,id |
| `notes` | string | ⬜ | max:500 |

**Response 201**
```json
{
  "data": {
    "id": 200, "driver_id": 3, "office_id": 2,
    "work_date": "2026-04-28", "shift_code": "day",
    "start_time": "06:00", "end_time": "14:00",
    "vehicle_id": 4, "notes": "Ca sáng thứ 2", "status": "draft",
    "driver": { "id": 3, "name": "Nguyễn Văn A" },
    "vehicle": { "id": 4, "plate_number": "51A-12345" },
    "office": { "id": 2, "name": "Văn phòng Hà Nội" }
  }
}
```

---

### `GET /driver-schedules/{id}` — Schedule + `driver` + `vehicle` + `office`

### `PUT /driver-schedules/{id}`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `work_date` | date | ⬜ | - |
| `shift_code` | string | ⬜ | `day` \| `night` \| `split` \| `custom` |
| `start_time` | time | ⬜ | format: `H:i` |
| `end_time` | time | ⬜ | format: `H:i` |
| `vehicle_id` | integer | ⬜ | exists:vehicles,id |
| `notes` | string | ⬜ | max:500 |

### `DELETE /driver-schedules/{id}`

---

### `POST /driver-schedules/{driverWorkSchedule}/submit` — no body
`draft` → `submitted`

### `POST /driver-schedules/{driverWorkSchedule}/approve`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `hos_override` | boolean | ⬜ | - |
| `override_reason` | string | ⬜ | max:500 |

`submitted` → `approved`. Kiểm tra HOS compliance.

### `POST /driver-schedules/{driverWorkSchedule}/reject` — no body
`submitted` → `draft`

### `POST /driver-schedules/{driverWorkSchedule}/lock` — no body
`approved` → `locked`

### `POST /driver-schedules/{driverWorkSchedule}/override`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `work_date` | date | ⬜ | - |
| `shift_code` | string | ⬜ | `day` \| `night` \| `split` \| `custom` |
| `start_time` | time | ⬜ | format: `H:i` |
| `end_time` | time | ⬜ | format: `H:i` |
| `vehicle_id` | integer | ⬜ | exists:vehicles,id |
| `notes` | string | ⬜ | max:500 |
| `override_reason` | string | ✅ | max:500 |

### `GET /driver-schedules/{driverWorkSchedule}/hos-check`
**Response 200** `{ "data": { "compliant": true, "weekly_hours": 42.5, "daily_hours": 8.5, "violations": [] } }`

---

## 23. Workforce — Auth required

### `GET /workforce/driver-schedules`

| Query | Type | R | Rules |
|-------|------|---|-------|
| `from` | date | ✅ | YYYY-MM-DD |
| `to` | date | ✅ | after_or_equal:from |
| `driver_id` | integer | ⬜ | exists:drivers,id |
| `office_id` | integer | ⬜ | exists:offices,id |
| `shift_code` | string | ⬜ | max:20 |
| `per_page` | integer | ⬜ | min:1, max:500 |

---

### `GET /workforce/leave-requests`

| Query | Type | R | Rules |
|-------|------|---|-------|
| `from` | date | ✅ | YYYY-MM-DD |
| `to` | date | ✅ | after_or_equal:from |
| `driver_id` | integer | ⬜ | exists:drivers,id |
| `status` | string | ⬜ | `pending` \| `approved` \| `rejected` \| `cancelled` |
| `per_page` | integer | ⬜ | min:1, max:500 |

---

### `GET /workforce/absences`

| Query | Type | R | Rules |
|-------|------|---|-------|
| `from` | date | ✅ | YYYY-MM-DD |
| `to` | date | ✅ | after_or_equal:from |
| `driver_id` | integer | ⬜ | exists:drivers,id |
| `per_page` | integer | ⬜ | min:1, max:500 |

---

### `PUT /workforce/driver-schedules/{id}/approve` — permission: schedule.approve

| Field | Type | R | Rules |
|-------|------|---|-------|
| `hos_override` | boolean | ⬜ | - |
| `override_reason` | string | ⬜ | max:500 |

### `PUT /workforce/driver-schedules/{id}/lock` — permission: schedule.approve · no body

---

## 24. Work Schedule Templates — Admin only

### `GET /work-schedule-templates` — Query: `search`, `sort`, `per_page`

### `POST /work-schedule-templates`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `company_id` | integer | ⬜ | exists:companies,id |
| `name` | string | ✅ | max:120 |
| `shift_code` | string | ✅ | `day` \| `night` \| `split` \| `custom` |
| `start_time` | time | ✅ | format: `H:i` |
| `end_time` | time | ✅ | format: `H:i` |
| `description` | string | ⬜ | max:2000 |
| `is_active` | boolean | ⬜ | default true |

**Response 201**
```json
{
  "data": {
    "id": 7, "company_id": null, "name": "Ca sáng tiêu chuẩn",
    "shift_code": "day", "start_time": "06:00", "end_time": "14:00",
    "description": null, "is_active": true
  }
}
```

### `GET /work-schedule-templates/{id}` / `DELETE /work-schedule-templates/{id}`

### `PUT /work-schedule-templates/{id}`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `name` | string | ⬜ | max:120 |
| `shift_code` | string | ⬜ | `day` \| `night` \| `split` \| `custom` |
| `start_time` | time | ⬜ | format: `H:i` |
| `end_time` | time | ⬜ | format: `H:i` |
| `description` | string | ⬜ | max:2000 |
| `is_active` | boolean | ⬜ | - |

---

## 25. Reports — Admin only

### `GET /reports/dashboard`

| Query | Type | R | Rules |
|-------|------|---|-------|
| `month` | integer | ⬜ | min:1, max:12 (default tháng hiện tại) |
| `year` | integer | ⬜ | min:2000, max:2100 |

Response: cached 1 giờ

---

### `GET /reports/payroll-summary`

| Query | Type | R | Rules |
|-------|------|---|-------|
| `company_id` | integer | ✅ | exists:companies,id |
| `month` | integer | ⬜ | min:1, max:12 |
| `year` | integer | ⬜ | min:2000, max:2100 |

Response: cached 24 giờ

---

### `GET /reports/revenue-summary`

Phải cung cấp **một trong hai**: (`from`+`to`) hoặc (`month`+`year`).

| Query | Type | R | Rules |
|-------|------|---|-------|
| `company_id` | integer | ⬜ | exists:companies,id |
| `from` | date | ⬜* | YYYY-MM-DD |
| `to` | date | ⬜* | after_or_equal:from |
| `month` | integer | ⬜* | min:1, max:12 |
| `year` | integer | ⬜* | min:2000, max:2100 |

---

## 26. Notifications — Auth required

### `GET /notifications`

| Query | Type | R | Mô tả |
|-------|------|---|-------|
| `per_page` | integer | ⬜ | max:100, default 15 |
| `page` | integer | ⬜ | - |
| `read` | integer | ⬜ | `0` = chưa đọc · `1` = đã đọc |

**Response 200**
```json
{
  "data": {
    "data": [
      {
        "id": "550e8400-...", "type": "App\\Notifications\\TripAssigned",
        "data": { "title": "Chuyến xe mới", "body": "Bạn được phân công TRIP-001" },
        "read_at": null, "created_at": "2026-04-25T09:00:00.000000Z"
      }
    ],
    "total": 12, "current_page": 1
  }
}
```

---

### `GET /notifications/unread-count`
**Response 200** `{ "data": { "count": 5 } }`

### `POST /notifications/{id}/read` — no body

### `POST /notifications/read-all` — no body

---

## 27. Chat — Auth required

### `GET /chat/sessions`

| Query | Type | R | Rules |
|-------|------|---|-------|
| `limit` | integer | ⬜ | min:1, max:100 (default 20) |

**Response 200**
```json
{ "data": [{ "session_id": "sess_abc123", "title": "Hỏi doanh thu", "last_message_at": "..." }] }
```

---

### `DELETE /chat/sessions/{sessionId}` — Xóa toàn bộ messages trong session

### `GET /chat/messages`

| Query | Type | R | Rules |
|-------|------|---|-------|
| `session_id` | string | ✅ | max:64 |
| `limit` | integer | ⬜ | min:1, max:100 (default 30) |

---

### `POST /chat/messages`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `message` | string | ✅ | max:4000 |
| `session_id` | string | ⬜ | max:64 — null = tạo session mới |
| `task` | string | ⬜ | `chat` \| `classify` \| `extract` \| `advice` |
| `context` | object | ⬜ | dữ liệu ngữ cảnh bổ sung |
| `model` | string | ⬜ | max:100 |

**Response 200**
```json
{ "data": { "session_id": "sess_abc123", "reply": "Doanh thu tháng 4...", "sources": [] } }
```

---

### `POST /chat/messages/stream`
Cùng body như `POST /chat/messages`.
**Response** — `Content-Type: text/event-stream`
```
data: {"chunk":"Doanh thu"}
data: {"chunk":" tháng 4"}
data: [DONE]
```

---

## 28. Upload — Auth required

### `POST /upload` — `multipart/form-data`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `file` | file | ✅ | image, mimes:`jpg,jpeg,png,webp`, max:5120 (5MB) |

**Response 201**
```json
{ "data": { "file_url": "https://res.cloudinary.com/.../abc.jpg", "file_name": "abc.jpg", "size": 204800, "mime_type": "image/jpeg" } }
```

---

## 29. Public Holidays — Auth required

### `GET /public-holidays`

| Query | Type | R | Rules |
|-------|------|---|-------|
| `year` | integer | ✅ | min:2000, max:2100 |
| `country_code` | string | ⬜ | max:5 (default `VN`) |

**Response 200**
```json
{ "data": [{ "date": "2026-04-30", "name": "Ngày Giải Phóng", "type": "public" }] }
```

---

## 30. AI Business Assist — Admin only

### `POST /ai/business-assist`

| Field | Type | R | Rules |
|-------|------|---|-------|
| `task` | string | ✅ | `dashboard_insight` \| `payroll_analysis` \| `trip_optimization` \| `risk_alert` \| `recommendation` |
| `company_id` | integer | ⬜ | exists:companies,id |
| `month` | integer | ⬜ | min:1, max:12 |
| `year` | integer | ⬜ | min:2000, max:2100 |
| `language` | string | ⬜ | `vi` \| `en` |
| `tone` | string | ⬜ | `concise` \| `detailed` \| `executive` |
| `question` | string | ⬜ | max:2000 |
| `context` | object | ⬜ | dữ liệu ngữ cảnh bổ sung |

---

## HTTP Status Codes

| Code | Ý nghĩa | Khi nào |
|------|---------|---------|
| `200` | OK | Thành công |
| `201` | Created | Tạo mới thành công |
| `401` | Unauthorized | Chưa đăng nhập / token hết hạn |
| `403` | Forbidden | Không đủ role/permission |
| `404` | Not Found | Không tìm thấy record |
| `409` | Conflict | Trùng lịch (schedule, assignment) |
| `422` | Unprocessable | Lỗi validation hoặc business rule |
| `429` | Too Many Requests | Vượt throttle |
| `500` | Server Error | Lỗi hệ thống |

---

## State Machines

```
Trip:     pending → in_progress → completed
          pending/in_progress → cancelled

Invoice:  draft → issued → paid
          draft/issued → cancelled

Payroll:  draft → approved → locked → paid

Leave/OT: pending → approved / rejected
          pending/approved → cancelled

Violation: pending → confirmed → (disputed → resolved)
           pending/confirmed → waived

Schedule: draft → submitted → approved → locked
          submitted → draft (reject)
          approved/locked → override (audit trail)
```

---

## Cách nhanh nhất tái tạo tài liệu này

```bash
# 1. Lấy toàn bộ routes + auth level
php artisan route:list --json | python3 script.py

# 2. Lấy toàn bộ validation rules trong 1 lệnh
find app/Http/Requests -name "*.php" | sort | while read f; do
  echo "=== $(basename $f .php) ==="
  awk '/function rules/,/^    \}/' "$f"
done

# 3. Generate OpenAPI spec (cần storage writable)
php artisan l5-swagger:generate
# File output: storage/api-docs/api-docs.json
```
