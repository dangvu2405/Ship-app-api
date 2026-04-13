# Frontend — Thay đổi backend (SOC2 / multi-tenant / toàn vẹn dữ liệu)

Tài liệu này mô tả **hợp đồng API và hành vi** frontend cần nắm sau các thay đổi backend (tenant context, `company_id`, validation, payroll lock, audit). Không sửa file kế hoạch SOC2 gốc.

**Tham chiếu thêm:** [FRONTEND_API_ENDPOINTS.md](./FRONTEND_API_ENDPOINTS.md), [SOC2_CONTROL_MAPPING.md](./SOC2_CONTROL_MAPPING.md), [SOC2_DATABASE_STRATEGY.md](./SOC2_DATABASE_STRATEGY.md).

---

## 1. Phạm vi route bị ảnh hưởng

Các route **admin** (`auth:sanctum` + `role:admin`), cả legacy `/api/...` và `/api/v1/...`, đều chạy thêm:

- `tenant.context` — thiết lập **tenant** theo user hoặc theo header/query (xem mục 2).
- `audit.sensitive_reads` — ghi audit **READ** (GET) cho một số nhóm path (drivers, payrolls, positions).

Route **authenticated thường** (không admin) **không** gắn hai middleware trên — hành vi cũ.

---

## 2. Tenant context — header & query

### 2.1 Khi nào list/API bị lọc theo công ty?

Backend áp **global scope** `BelongsToTenant` trên các model có `company_id` (ví dụ: `drivers`, `vehicles`, `trips`, `vehicle_assignments`, `vehicle_expenses`, `payrolls`, `payroll_lines`), **chỉ khi** `TenantContext` có `company_id` khác `null`.

- **Admin không gắn tài xế** (`user.driver_id` null): thường **không** có tenant → thấy **toàn bộ** công ty (như trước, nếu không gửi header/query).
- **User có `driver` + `office`**: tenant mặc định = `office.company_id` → list chỉ trong **một** công ty.

### 2.2 Admin chủ động chọn công ty (multi-company UI)

Chỉ áp dụng khi user có role **admin** và `company_id` trỏ tới bản ghi `companies` tồn tại:

| Cách | Ví dụ |
|------|--------|
| Header | `X-Company-Id: 2` |
| Query (fallback nếu không gửi header) | `GET /api/v1/drivers?company_id=2` |

**Lưu ý:** Header được ưu tiên hơn query khi cả hai có giá trị (logic backend hiện tại).

### 2.3 Gợi ý frontend

- Lưu **company đang chọn** trong state (Zustand/Redux/context).
- Gắn **`X-Company-Id`** vào mọi request admin tới API (axios/fetch interceptor), **hoặc** append `company_id` vào query cho các màn list — nhất quán một cách để tránh lệch tenant.
- Màn **đổi công ty**: sau khi đổi, **invalidate cache** và refetch list (drivers, trips, payrolls, …).

---

## 3. Trường `company_id` trên JSON (schema)

Các resource sau có thêm (hoặc đã có và luôn đồng bộ) **`company_id`** trong payload JSON khi backend serialize model:

- `Driver`, `Vehicle`, `Trip`, `VehicleAssignment`, `VehicleExpense`
- `Payroll` (đã có); `PayrollLine` (thêm)

Frontend nên:

- Bổ sung type/interface TypeScript (hoặc OpenAPI client regenerate).
- Dùng `company_id` để badge/filter UI nội bộ nếu cần; **không** tin tưởng client-only — luôn gửi đúng cặp `driver_id` / `vehicle_id` cùng công ty (mục 4).

---

## 4. Validation mới — cùng công ty (422)

### 4.1 Trip (`POST/PATCH` trips)

Nếu `driver_id` và `vehicle_id` thuộc **hai `company_id` khác nhau**, API trả **422** với lỗi (ví dụ):

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "vehicle_id": ["Xe và tài xế phải cùng công ty (company_id)."]
  }
}
```

**Việc cần làm UI:** khi chọn tài xế, chỉ load danh sách xe **cùng company** (filter client + server), hoặc dùng office/company từ driver để cascade chọn xe.

### 4.2 Vehicle assignment (`POST/PATCH` vehicle_assignments)

Cùng rule: tài xế và xe phải cùng `company_id`.

---

## 5. Trips — filter `company_id` (GET)

`GET /api/v1/trips?company_id=<id>` lọc theo cột **`trips.company_id`** (denormalized), không còn suy luận chỉ qua `vehicle.office`.

Filter `office_id` (theo `vehicle.office_id`) **giữ** như cũ.

---

## 6. Payroll — lock & `snapshot_json`

- Khi gọi `POST .../payrolls/{id}/lock`, response payroll có thêm **`snapshot_json`** (object JSON): rules thưởng km, `config('payroll')`, map `drivers` → `base_salary` / `position_id` tại thời điểm khóa, v.v.
- Mỗi `payroll_lines[].meta_json` có thêm khóa **`lock_snapshot`** (sau lock).

**UI:**

- Hiển thị “đã khóa” + có thể link “Xem snapshot cấu hình” từ `snapshot_json` (read-only).
- **Không** gửi PATCH chỉnh sửa line/payroll khi `status === 'locked'` — backend trả **403/validation** (observer).

---

## 7. Header tùy chọn — tương quan audit

Backend ghi `request_id` / `user_agent` vào audit khi có.

- Gửi **`X-Request-Id`** (UUID) trên request admin giúp correlate log phía API/gateway.

---

## 8. Mã hóa PII (ít ảnh hưởng API tức thời)

Env `SHIP_ENCRYPT_PII` / config `ship.encrypt_pii_fields`: khi bật, một số cột driver được mã hóa **trong DB**; API vẫn trả giá trị **đã giải mã** cho client.

Frontend **không** cần đổi cách hiển thị trừ khi team backend thông báo thêm format đặc biệt.

---

## Checklist nhanh cho team FE

| Hạng mục | Hành động |
|----------|-----------|
| Admin multi-company | Gửi `X-Company-Id` (hoặc `?company_id=`) nhất quán |
| Types | Thêm `company_id` cho driver, vehicle, trip, assignment, expense, payroll_line |
| Form trip / phân công xe | Cascade chọn xe theo công ty tài xế; xử lý message 422 |
| Payroll detail | Đọc `snapshot_json`, `meta_json.lock_snapshot` khi locked |
| Observability | Gửi `X-Request-Id` (khuyến nghị) |

---

*Tạo: đồng bộ với backend SOC2 / tenant (migrations + middleware + observers).*
