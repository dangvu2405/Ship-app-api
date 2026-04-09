# Handoff Frontend – Schema MUST HAVE (Sau migration 2026-04-15)

Tài liệu này mô tả **dữ liệu đã có trên database** sau bộ migration MUST HAVE (`leave_*`, payroll line items, `payslips`, kế toán tối thiểu, bảng lịch sử trạng thái, index hiệu năng). Mục đích: frontend có thể **thiết kế màn hình, form, state machine và mock API** trước khi backend expose đầy đủ endpoint.

**Tham chiếu kỹ thuật backend:** [MUST_HAVE_MIGRATION_SPEC.md](./MUST_HAVE_MIGRATION_SPEC.md)  
**Danh sách endpoint hiện có:** [FRONTEND_API_ENDPOINTS.md](./FRONTEND_API_ENDPOINTS.md)

**Payload gửi lên (body):** mục **§8**. **Response trả về:** mục **§9**.

---

## 1) Trạng thái tích hợp API

| Khu vực | Database | REST API (hiện tại) |
|--------|----------|---------------------|
| Leave (loại phép, đơn, số dư) | Đã có bảng | **Chưa** có route chuẩn trong `FRONTEND_API_ENDPOINTS` — cần phase backend |
| Tax brackets / Insurance rates | Đã có bảng | **Chưa** — thường dùng cho màn cấu hình / chỉ đọc |
| `payroll_earnings` / `payroll_deductions` | Đã có bảng | **Chưa** — hiển thị chi tiết dòng thu/chi theo `payroll_detail` |
| `payslips` | Đã có bảng | **Chưa** — phiếu lương + `snapshot_json` |
| Chart of accounts / Journals | Đã có bảng | **Chưa** — module kế toán tối thiểu |
| Status histories (payroll / trip / invoice) | Đã có bảng | **Chưa** — timeline / audit UI |

Frontend nên bọc **API client** theo envelope chuẩn dự án (xem `BaseController`):

```json
{ "success": true, "message": "...", "data": { } }
```

```json
{ "success": false, "message": "...", "errors": { } }
```

### 1.1 List có phân trang (pattern `HasIndexQuery`)

Các endpoint `index` trong dự án thường trả `data` **lồng**: mảng bản ghi nằm trong `data.data`, thông tin trang trong `data.meta`:

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

Query gợi ý (khi backend áp dụng giống module hiện có): `per_page`, `sort_by`, `sort_order`, `keyword` / `q`, và các tham số lọc theo từng resource.

---

## 2) Nghỉ phép (Leave)

### 2.1 `leave_types` (danh mục loại phép)

| Field | Kiểu UI gợi ý | Ghi chú |
|-------|----------------|--------|
| `id` | number | PK |
| `code` | string, unique | VD: `annual`, `sick` |
| `name` | string | Hiển thị |
| `is_paid` | boolean | |
| `annual_quota_days` | decimal (6,2) | Ngày/năm |
| `allow_carry_forward` | boolean | |
| `requires_attachment` | boolean | Bật upload nếu true |
| `status` | enum UI | Mặc định DB: `active` |
| `created_at` / `updated_at` | datetime | |
| `deleted_at` | datetime nullable | Soft delete |

### 2.2 `leave_requests` (đơn nghỉ)

| Field | Kiểu UI gợi ý | Ghi chú |
|-------|----------------|--------|
| `employee_id` | select / context user | FK `employees` |
| `leave_type_id` | select | FK `leave_types` |
| `from_date`, `to_date` | date | Validate `from <= to` |
| `total_days` | decimal (6,2) | Có thể tính FE hoặc lấy từ API |
| `reason` | textarea | |
| `status` | workflow | Gợi ý: `draft` → `submitted` → `approved` \| `rejected`; `cancelled` |
| `approved_by`, `approved_at` | user + datetime | |
| `attachment_urls` | JSON array URL | FE: upload → lưu URL (API upload TBD) |
| `created_by`, `updated_by`, `deleted_by` | admin audit | |
| `timestamps`, `deleted_at` | | |

**Gợi ý màn hình:** danh sách theo `employee_id` + filter `status`; form tạo/sửa khi `draft`; nút submit; màn duyệt cho HR/Admin.

### 2.3 `leave_balances` (số dư theo năm)

| Field | Ghi chú |
|-------|--------|
| `employee_id`, `leave_type_id`, `year` | Unique composite |
| `opening_days`, `earned_days`, `used_days`, `adjusted_days`, `closing_days` | decimal (6,2) |

**Gợi ý màn hình:** bảng theo năm; có thể đọc-only cho nhân viên, chỉnh `adjusted_days` cho HR.

---

## 3) Tuân thủ lương (tham chiếu – chủ yếu admin)

### 3.1 `tax_brackets`

- `effective_from`, `effective_to` (nullable), `level`, `income_from`, `income_to` (nullable), `tax_rate` (%), `quick_deduction`, `status`.
- UI: bảng versioning theo `effective_from` (chỉnh sửa ít, hiển thị nhiều).

### 3.2 `insurance_rates`

- Kỳ hiệu lực + các tỷ lệ % (social/health/unemployment cho NLĐ và DN) + `salary_cap_amount`.
- UI: màn cấu hình theo khoảng ngày.

### 3.3 `payroll_earnings` / `payroll_deductions`

Gắn với **một** `payroll_detail_id` (chi tiết lương theo nhân viên trong một kỳ).

**Earnings:** `type_code`, `name`, `amount`, `taxable`, `insurable`, `source` (`system` \| `manual` \| `import`), `meta_json`, audit `*_by`, soft delete.

**Deductions:** `type_code`, `name`, `amount`, `pre_tax`, `source`, `meta_json`, audit, soft delete.

**Gợi ý type_code (thống nhất với backend sau):**  
Earnings: `BASE`, `OT`, `BONUS`, `ALLOWANCE`, …  
Deductions: `TAX`, `BHXH`, `BHYT`, `BHTN`, `OTHER`, …

**UI:** tab “Chi tiết” trong màn payroll detail: bảng hai cột nhóm Thu / Khấu trừ; cho phép thêm dòng nếu policy cho phép `manual` (chờ service layer).

### 3.4 `payslips`

| Field | Ghi chú |
|-------|--------|
| `payroll_id`, `payroll_detail_id`, `employee_id` | |
| `issue_number` | Unique; hiển thị trên phiếu |
| `issued_at`, `issued_by`, `status` | Gợi ý: `draft`, `issued`, `void` |
| `snapshot_json` | **Bắt buộc JSON** — payload bất biến khi đã issue |
| **Ràng buộc** | Một `payroll_detail_id` chỉ một payslip (`unique`) |

**UI:** màn xem phiếu lương (read-only từ `snapshot_json`); nút “Phát hành”; export PDF (sau).

---

## 4) Kế toán tối thiểu (GL)

### 4.1 `chart_of_accounts`

- `code` (unique), `name`, `type` (UI: `asset` \| `liability` \| `equity` \| `revenue` \| `expense`), `parent_id` (cây), `is_postable`, `status`, soft delete.

### 4.2 `journal_entries`

- `entry_no` (unique), `entry_date`, `source_type`, `source_id` (polymorphic kiểu đơn giản), `status` (`draft` \| `posted` \| `reversed`), `description`, `posted_by`, `posted_at`.

### 4.3 `journal_entry_lines`

- `journal_entry_id`, `account_id`, `debit`, `credit`, `line_description`, `line_no`.

**UI:** form bút toán: header + nhiều dòng; validate **tổng Nợ = tổng Có** (FE + BE).

---

## 5) Lịch sử trạng thái (timeline)

Cấu trúc thống nhất: `from_status` (nullable), `to_status`, `changed_by`, `changed_at`, `note`.

| Bảng | FK |
|------|-----|
| `payroll_status_histories` | `payroll_id` |
| `trip_status_histories` | `trip_id` |
| `invoice_status_histories` | `invoice_id` |

**UI:** component timeline trên màn chi tiết Payroll / Trip / Invoice; sort theo `changed_at`.

---

## 6) Hiệu năng & filter gợi ý (đã thêm index)

Backend đã thêm index phục vụ list lớn (không đổi contract API nhưng list sẽ nhanh hơn khi filter đúng cột):

- `attendances`: `(employee_id, status, date)`
- `trips`: `(driver_id, status, start_time)`, `(vehicle_id, status, start_time)`
- `vehicle_expenses`: `(vehicle_id, expense_date, type)`
- `payroll_details`: `(employee_id, payroll_id)`

Frontend có thể ưu tiên filter/sort theo các cột này khi thiết kế URL query (sau khi BE hỗ trợ).

---

## 7) Gợi ý REST (đặt tên sớm – **chưa implement**)

Chỉ để **chốt contract với backend**, có thể đổi khi implement:

- `GET/POST /api/leave-types`, `GET/PATCH /api/leave-types/{id}`
- `GET/POST /api/leave-requests`, `GET/PATCH /api/leave-requests/{id}`, action `submit`, `approve`, `reject`
- `GET /api/employees/{id}/leave-balances?year=`
- `GET /api/payroll-details/{id}/earnings`, `.../deductions`
- `GET /api/payrolls/{id}/payslips`, `GET /api/payslips/{id}`, `POST /api/payslips/{id}/issue`
- `GET/POST /api/chart-of-accounts`, `GET/POST /api/journal-entries`, nested `lines`
- `GET /api/payrolls/{id}/status-history`, tương tự trips/invoices

Quyền: theo RBAC hiện có (`admin`, `hr`, `accountant`, …) — chi tiết gán khi backend mở route.

---

## 8) Mẫu request body (payload gửi lên API)

Header: `Content-Type: application/json`. Đây là **contract dự kiến** để FE tạo form / hook; backend có thể siết validation (FormRequest) sau.

### 8.1 `POST /api/leave-types` — tạo loại phép

```json
{
  "code": "sick",
  "name": "Nghỉ ốm",
  "is_paid": true,
  "annual_quota_days": 30,
  "allow_carry_forward": false,
  "requires_attachment": true,
  "status": "active"
}
```

### 8.2 `PATCH /api/leave-types/{id}` — sửa loại phép (partial)

```json
{
  "name": "Nghỉ ốm (có lương)",
  "annual_quota_days": 20,
  "status": "active"
}
```

### 8.3 `POST /api/leave-requests` — tạo đơn nghỉ

```json
{
  "employee_id": 5,
  "leave_type_id": 1,
  "from_date": "2026-05-01",
  "to_date": "2026-05-03",
  "total_days": 3,
  "reason": "Việc gia đình",
  "attachment_urls": []
}
```

> Với NV tự tạo, backend có thể **bỏ qua** `employee_id` và lấy từ user đăng nhập.

### 8.4 `PATCH /api/leave-requests/{id}` — sửa khi còn `draft`

```json
{
  "to_date": "2026-05-04",
  "total_days": 4,
  "reason": "Cập nhật lịch",
  "attachment_urls": ["https://cdn.example.com/leaves/10/scan.pdf"]
}
```

### 8.5 `POST /api/leave-requests/{id}/submit`

```json
{
  "note": "Đã đính kèm giấy tờ"
}
```

Hoặc body rỗng: `{}`.

### 8.6 `POST /api/leave-requests/{id}/approve`

```json
{
  "note": "Đồng ý"
}
```

### 8.7 `POST /api/leave-requests/{id}/reject`

```json
{
  "note": "Thiếu giấy chứng nhận"
}
```

### 8.8 `POST /api/leave-requests/{id}/cancel`

```json
{
  "note": "Không nghỉ nữa"
}
```

### 8.9 `PATCH /api/employees/{employeeId}/leave-balances/{id}` — HR điều chỉnh số dư

```json
{
  "adjusted_days": 0.5
}
```

Hoặc cập nhật đầy đủ các cột số (nếu BE cho phép):

```json
{
  "opening_days": 2,
  "earned_days": 12,
  "used_days": 1.5,
  "adjusted_days": 0.5,
  "closing_days": 12.5
}
```

### 8.10 `POST /api/tax-brackets`

```json
{
  "effective_from": "2026-01-01",
  "effective_to": null,
  "level": 1,
  "income_from": 0,
  "income_to": 5000000,
  "tax_rate": 5,
  "quick_deduction": 0,
  "status": "active"
}
```

### 8.11 `POST /api/insurance-rates`

```json
{
  "effective_from": "2026-01-01",
  "effective_to": null,
  "social_employee_rate": 8,
  "social_company_rate": 17,
  "health_employee_rate": 1.5,
  "health_company_rate": 3,
  "unemployment_employee_rate": 1,
  "unemployment_company_rate": 1,
  "salary_cap_amount": 46800000,
  "status": "active"
}
```

### 8.12 `POST /api/payroll-details/{id}/earnings`

```json
{
  "type_code": "ALLOWANCE",
  "name": "Phụ cấp điện thoại",
  "amount": 500000,
  "taxable": true,
  "insurable": false,
  "source": "manual",
  "meta_json": { "policy": "HR-2026-01" }
}
```

### 8.13 `PATCH /api/payroll-earnings/{id}`

```json
{
  "amount": 600000,
  "name": "Phụ cấp điện thoại (điều chỉnh)"
}
```

### 8.14 `POST /api/payroll-details/{id}/deductions`

```json
{
  "type_code": "OTHER",
  "name": "Khấu trừ khác",
  "amount": 100000,
  "pre_tax": false,
  "source": "manual",
  "meta_json": null
}
```

### 8.15 `POST /api/payslips` — nháp (nếu tách bước tạo / issue)

```json
{
  "payroll_id": 20,
  "payroll_detail_id": 50,
  "employee_id": 5,
  "issue_number": "PS-2026-04-00001",
  "snapshot_json": {
    "employee": { "id": 5, "code": "NV001", "full_name": "Nguyễn Văn A" },
    "period": { "month": 4, "year": 2026 },
    "earnings": [],
    "deductions": [],
    "net_pay": "0.00"
  }
}
```

### 8.16 `POST /api/payslips/{id}/issue` — phát hành

```json
{
  "issue_number": "PS-2026-04-00001",
  "note": "Phát hành đúng kỳ"
}
```

Hoặc `{}` nếu `issue_number` do server sinh.

### 8.17 `POST /api/chart-of-accounts`

```json
{
  "code": "111",
  "name": "Tiền mặt",
  "type": "asset",
  "parent_id": null,
  "is_postable": true,
  "status": "active"
}
```

### 8.18 `POST /api/journal-entries` — header + dòng

```json
{
  "entry_no": "JE-2026-0005",
  "entry_date": "2026-04-15",
  "source_type": "manual",
  "source_id": null,
  "description": "Điều chỉnh cuối kỳ",
  "status": "draft",
  "lines": [
    {
      "account_id": 1,
      "debit": 15000000,
      "credit": 0,
      "line_description": "Chi phí lương",
      "line_no": 1
    },
    {
      "account_id": 2,
      "debit": 0,
      "credit": 15000000,
      "line_description": "Phải trả lương",
      "line_no": 2
    }
  ]
}
```

> Có thể không gửi `entry_no` nếu backend tự sinh số chứng từ.

### 8.19 `PATCH /api/journal-entries/{id}` — sửa bản nháp

```json
{
  "description": "Mô tả cập nhật"
}
```

**Ghi sổ (gợi ý):** `POST /api/journal-entries/{id}/post` với `{}` hoặc `{ "note": "Đã kiểm tra cân đối Nợ/Có" }`.

### 8.20 Status history — thường **không** gửi từ FE

Bảng lịch sử trạng thái thường do service ghi khi đổi `payrolls.status`, `trips.status`, `invoices.status`. Nếu sau này có API thủ công:

```json
{
  "to_status": "approved",
  "note": "Ghi chú",
  "changed_at": "2026-04-15T10:00:00Z"
}
```

---

## 9) Mẫu response JSON (contract dự kiến — mock / OpenAPI)

Các ví dụ dưới đây **mô phỏng** payload khi backend implement; field khớp migration. Số/chuỗi chỉ mang tính minh họa.

### 9.1 Thành công — `GET /api/leave-types` (list)

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "data": [
      {
        "id": 1,
        "code": "annual",
        "name": "Phép năm",
        "is_paid": true,
        "annual_quota_days": "12.00",
        "allow_carry_forward": true,
        "requires_attachment": false,
        "status": "active",
        "created_at": "2026-04-15T08:00:00.000000Z",
        "updated_at": "2026-04-15T08:00:00.000000Z",
        "deleted_at": null
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 15,
      "total": 1
    }
  }
}
```

### 9.2 Thành công — `GET /api/leave-types/{id}` (chi tiết)

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "id": 1,
    "code": "annual",
    "name": "Phép năm",
    "is_paid": true,
    "annual_quota_days": "12.00",
    "allow_carry_forward": true,
    "requires_attachment": false,
    "status": "active",
    "created_at": "2026-04-15T08:00:00.000000Z",
    "updated_at": "2026-04-15T08:00:00.000000Z",
    "deleted_at": null
  }
}
```

### 9.3 Thành công — `POST /api/leave-requests` (tạo đơn)

```json
{
  "success": true,
  "message": "Leave request created",
  "data": {
    "id": 10,
    "employee_id": 5,
    "leave_type_id": 1,
    "from_date": "2026-05-01",
    "to_date": "2026-05-03",
    "total_days": "3.00",
    "reason": "Việc gia đình",
    "status": "draft",
    "approved_by": null,
    "approved_at": null,
    "attachment_urls": ["https://cdn.example.com/leaves/10/file.pdf"],
    "created_by": 2,
    "updated_by": 2,
    "deleted_by": null,
    "created_at": "2026-04-15T09:00:00.000000Z",
    "updated_at": "2026-04-15T09:00:00.000000Z",
    "deleted_at": null,
    "leave_type": {
      "id": 1,
      "code": "annual",
      "name": "Phép năm"
    }
  }
}
```

### 9.4 Thành công — `GET /api/employees/{id}/leave-balances?year=2026`

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "year": 2026,
    "balances": [
      {
        "id": 3,
        "employee_id": 5,
        "leave_type_id": 1,
        "year": 2026,
        "opening_days": "2.00",
        "earned_days": "12.00",
        "used_days": "1.50",
        "adjusted_days": "0.00",
        "closing_days": "12.50",
        "created_at": "2026-04-15T08:00:00.000000Z",
        "updated_at": "2026-04-15T08:00:00.000000Z",
        "leave_type": {
          "id": 1,
          "code": "annual",
          "name": "Phép năm"
        }
      }
    ]
  }
}
```

### 9.5 Thành công — `GET /api/tax-brackets` (list)

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "data": [
      {
        "id": 1,
        "effective_from": "2026-01-01",
        "effective_to": null,
        "level": 1,
        "income_from": "0.00",
        "income_to": "5000000.00",
        "tax_rate": "5.00",
        "quick_deduction": "0.00",
        "status": "active",
        "created_at": "2026-04-15T08:00:00.000000Z",
        "updated_at": "2026-04-15T08:00:00.000000Z"
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 15,
      "total": 1
    }
  }
}
```

### 9.6 Thành công — `GET /api/insurance-rates` (list)

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "data": [
      {
        "id": 1,
        "effective_from": "2026-01-01",
        "effective_to": null,
        "social_employee_rate": "8.00",
        "social_company_rate": "17.00",
        "health_employee_rate": "1.50",
        "health_company_rate": "3.00",
        "unemployment_employee_rate": "1.00",
        "unemployment_company_rate": "1.00",
        "salary_cap_amount": "46800000.00",
        "status": "active",
        "created_at": "2026-04-15T08:00:00.000000Z",
        "updated_at": "2026-04-15T08:00:00.000000Z"
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 15,
      "total": 1
    }
  }
}
```

### 9.7 Thành công — `GET /api/payroll-details/{id}/earnings` (collection, không phân trang hoặc có meta tùy BE)

```json
{
  "success": true,
  "message": "OK",
  "data": [
    {
      "id": 100,
      "payroll_detail_id": 50,
      "type_code": "BASE",
      "name": "Lương cơ bản",
      "amount": "15000000.00",
      "taxable": true,
      "insurable": true,
      "source": "system",
      "meta_json": null,
      "created_by": null,
      "updated_by": null,
      "deleted_by": null,
      "created_at": "2026-04-15T10:00:00.000000Z",
      "updated_at": "2026-04-15T10:00:00.000000Z",
      "deleted_at": null
    }
  ]
}
```

### 9.8 Thành công — `GET /api/payroll-details/{id}/deductions`

```json
{
  "success": true,
  "message": "OK",
  "data": [
    {
      "id": 200,
      "payroll_detail_id": 50,
      "type_code": "TAX",
      "name": "Thuế TNCN tạm khấu",
      "amount": "750000.00",
      "pre_tax": false,
      "source": "system",
      "meta_json": { "bracket_level": 1 },
      "created_by": null,
      "updated_by": null,
      "deleted_by": null,
      "created_at": "2026-04-15T10:00:00.000000Z",
      "updated_at": "2026-04-15T10:00:00.000000Z",
      "deleted_at": null
    }
  ]
}
```

### 9.9 Thành công — `GET /api/payslips/{id}`

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "id": 1,
    "payroll_id": 20,
    "payroll_detail_id": 50,
    "employee_id": 5,
    "issue_number": "PS-2026-04-00001",
    "issued_at": "2026-04-15T11:30:00.000000Z",
    "status": "issued",
    "snapshot_json": {
      "employee": { "id": 5, "code": "NV001", "full_name": "Nguyễn Văn A" },
      "period": { "month": 4, "year": 2026 },
      "earnings": [{ "code": "BASE", "name": "Lương cơ bản", "amount": "15000000.00" }],
      "deductions": [{ "code": "TAX", "name": "Thuế TNCN", "amount": "750000.00" }],
      "net_pay": "12000000.00"
    },
    "issued_by": 1,
    "created_at": "2026-04-15T11:30:00.000000Z",
    "updated_at": "2026-04-15T11:30:00.000000Z"
  }
}
```

### 9.10 Thành công — `GET /api/chart-of-accounts` (list / tree)

Backend có thể trả **flat list** (FE tự build cây từ `parent_id`) hoặc trả sẵn `children`.

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "data": [
      {
        "id": 1,
        "code": "111",
        "name": "Tiền mặt",
        "type": "asset",
        "parent_id": null,
        "is_postable": true,
        "status": "active",
        "created_at": "2026-04-15T08:00:00.000000Z",
        "updated_at": "2026-04-15T08:00:00.000000Z",
        "deleted_at": null
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 15,
      "total": 1
    }
  }
}
```

### 9.11 Thành công — `GET /api/journal-entries/{id}` (kèm dòng)

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "id": 5,
    "entry_no": "JE-2026-0005",
    "entry_date": "2026-04-15",
    "source_type": "payroll",
    "source_id": 20,
    "status": "posted",
    "description": "Hạch toán lương tháng 4",
    "posted_by": 1,
    "posted_at": "2026-04-15T14:00:00.000000Z",
    "created_at": "2026-04-15T14:00:00.000000Z",
    "updated_at": "2026-04-15T14:00:00.000000Z",
    "lines": [
      {
        "id": 10,
        "journal_entry_id": 5,
        "account_id": 1,
        "debit": "15000000.00",
        "credit": "0.00",
        "line_description": "Chi phí lương",
        "line_no": 1,
        "created_at": "2026-04-15T14:00:00.000000Z",
        "updated_at": "2026-04-15T14:00:00.000000Z",
        "account": {
          "id": 1,
          "code": "6421",
          "name": "Chi phí nhân công"
        }
      },
      {
        "id": 11,
        "journal_entry_id": 5,
        "account_id": 2,
        "debit": "0.00",
        "credit": "15000000.00",
        "line_description": "Phải trả lương",
        "line_no": 2,
        "created_at": "2026-04-15T14:00:00.000000Z",
        "updated_at": "2026-04-15T14:00:00.000000Z",
        "account": {
          "id": 2,
          "code": "334",
          "name": "Phải trả người lao động"
        }
      }
    ]
  }
}
```

### 9.12 Thành công — `GET /api/payrolls/{id}/status-history`

```json
{
  "success": true,
  "message": "OK",
  "data": [
    {
      "id": 1,
      "payroll_id": 20,
      "from_status": null,
      "to_status": "draft",
      "changed_by": 1,
      "changed_at": "2026-04-15T09:00:00.000000Z",
      "note": "Khởi tạo kỳ lương",
      "created_at": "2026-04-15T09:00:00.000000Z",
      "updated_at": "2026-04-15T09:00:00.000000Z"
    },
    {
      "id": 2,
      "payroll_id": 20,
      "from_status": "draft",
      "to_status": "approved",
      "changed_by": 1,
      "changed_at": "2026-04-15T10:00:00.000000Z",
      "note": null,
      "created_at": "2026-04-15T10:00:00.000000Z",
      "updated_at": "2026-04-15T10:00:00.000000Z"
    }
  ]
}
```

(`trip` / `invoice` status-history có cùng dạng mảng, đổi `payroll_id` → `trip_id` / `invoice_id`.)

### 9.13 Lỗi thường gặp

**401 — chưa đăng nhập / token hết hạn**

```json
{
  "success": false,
  "message": "Unauthorized"
}
```

**403 — không đủ quyền**

```json
{
  "success": false,
  "message": "Forbidden"
}
```

**404 — không tìm thấy**

```json
{
  "success": false,
  "message": "Resource not found"
}
```

**422 — validation (ví dụ tạo đơn nghỉ)**

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "to_date": ["The to date must be a date after or equal to from date."],
    "total_days": ["The total days field is required."]
  }
}
```

> **Lưu ý:** `decimal` từ Laravel/MySQL thường được serialize thành **chuỗi** (vd. `"12.00"`) trong JSON; frontend nên parse thống nhất.

---

## 10) Checklist cho Frontend

1. Thêm route/module UI cho Leave (master data + đơn + số dư).
2. Chuẩn bị types TypeScript/interface khớp bảng trên (nullable đúng với DB).
3. Payroll detail: layout tab **Tổng hợp (cũ)** + tab **Dòng thu / Dòng khấu trừ (mới)** + **Phiếu lương** khi có API.
4. Timeline status cho Payroll / Trip / Invoice dùng chung component.
5. Kế toán: cây tài khoản + form bút toán (có thể phase 2).
6. Theo dõi [FRONTEND_API_ENDPOINTS.md](./FRONTEND_API_ENDPOINTS.md) khi backend cập nhật endpoint thật.
