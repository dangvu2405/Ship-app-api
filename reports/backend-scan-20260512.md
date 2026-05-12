**📋 BÁO CÁO SCAN BACKEND — COMPANY SHIP**
**Ngày scan**: 2026-05-12
**Laravel**: 12 · **PHP**: 8.2 · **DB**: MySQL 8.0

**📊 TỔNG QUAN**
| Hạng mục | Vấn đề | Critical 🔴 | High 🟠 | Medium 🟡 | Low 🟢 |
|----------|--------|-------------|---------|-----------|--------|
| [A] Multi-tenant | 2 | 2 | 0 | 0 | 0 |
| [B] Business Rules | 6 | 1 | 5 | 0 | 0 |
| [C] Authorization | 1 | 0 | 1 | 0 | 0 |
| [D] Eloquent & DB | 2 | 0 | 0 | 2 | 0 |
| [E] API & Response | 3 | 0 | 0 | 3 | 0 |
| [F] Validation | 2 | 0 | 1 | 1 | 0 |
| [G] Audit & Logging | 0 | 0 | 0 | 0 | 0 |
| [H] Performance | 2 | 0 | 1 | 0 | 1 |
| **TỔNG** | **18** | **3** | **8** | **6** | **1** |

**Điểm sức khoẻ backend**: 17/100
> 100 - (Critical×10) - (High×5) - (Medium×2) - (Low×1)

---

**🔴 CRITICAL — Sửa ngay trước khi deploy**

### [A-01] Tenant fallback + cho phép override `company_id`
**Hạng mục**: A · **Điểm KT**: A-01/A-03
**File**: [app/Http/Controllers/Api/CetaSpecController.php](app/Http/Controllers/Api/CetaSpecController.php#L409), [app/Http/Controllers/Api/CetaSpecController.php](app/Http/Controllers/Api/CetaSpecController.php#L434), [app/Services/BaseService.php](app/Services/BaseService.php#L30)

**Vấn đề**:
- Khi tenant không resolve được, code fallback về company đầu tiên -> rò dữ liệu cross-tenant.
- `company_id` từ request không bị override nếu đã có -> user có thể tạo dữ liệu vào tenant khác.

**Fix**:
```php
// Gợi ý: luôn ép company_id theo tenant, không fallback
$companyId = $this->tenantContext->getCompanyId();
if ($companyId === null || $companyId <= 0) {
    abort(403, 'Không thể xác định company_id.');
}
$payload['company_id'] = $companyId;
```

---

### [A-02] Query `trips` không scope `company_id`
**Hạng mục**: A · **Điểm KT**: A-01/A-02
**File**: [app/Services/Trip/TripService.php](app/Services/Trip/TripService.php#L33), [app/Services/Fleet/FleetService.php](app/Services/Fleet/FleetService.php#L30-L34)

**Vấn đề**:
- `TripService` đọc trip theo ID không scope tenant.
- `FleetService` lấy danh sách trip bận theo ngày không scope tenant, làm sai kết quả và rò dữ liệu.

**Fix**:
```php
$trip = DB::table('trips')
    ->where('company_id', $this->companyId())
    ->where('id', $tripId)
    ->first();
```

---

### [B-01] Trạng thái trip dùng enum ngoài spec
**Hạng mục**: B · **Điểm KT**: B-01/D-05
**File**: [app/Http/Controllers/Api/TripController.php](app/Http/Controllers/Api/TripController.php#L330-L423), [app/Http/Controllers/Api/CetaSpecController.php](app/Http/Controllers/Api/CetaSpecController.php#L592-L595), [app/Services/Trip/TripService.php](app/Services/Trip/TripService.php#L17-L23)

**Vấn đề**:
- Sử dụng `assigned`, `in_transit`, `arrived`, `delivered` trong khi DB/spec chỉ cho `pending/in_progress/completed/cancelled`.
- Dễ gây lỗi DB enum + sai workflow (WF-01).

**Fix**:
```php
// Map đúng spec
// assign -> in_progress, deliver -> completed, arrive -> in_progress + actual_delivered_at
```

---

**🟠 HIGH — Sửa trong sprint này**

### [C-02] `authorize()` luôn trả `true` (không check permission)
**Hạng mục**: C · **Điểm KT**: C-02/C-03
**File**: [app/Http/Requests/CetaResourceRequest.php](app/Http/Requests/CetaResourceRequest.php#L57-L60), [app/Http/Requests/AppFormRequest.php](app/Http/Requests/AppFormRequest.php#L13-L16), [app/Http/Requests/Trip/StoreTripRequest.php](app/Http/Requests/Trip/StoreTripRequest.php#L11-L15)

**Vấn đề**:
- Toàn bộ FormRequest không kiểm tra `user_permissions` -> bỏ qua phân quyền chi tiết.

**Fix**:
```php
public function authorize(): bool
{
    $user = $this->user();
    return $user?->hasPermission('orders:create', $this->tenantCompanyId()) ?? false;
}
```

---

### [F-02] Validation sai bảng + không scope tenant
**Hạng mục**: F · **Điểm KT**: F-02/A-01
**File**: [app/Http/Requests/Trip/StoreTripRequest.php](app/Http/Requests/Trip/StoreTripRequest.php#L22-L23), [app/Http/Requests/Trip/UpdateTripRequest.php](app/Http/Requests/Trip/UpdateTripRequest.php#L24-L25), [app/Http/Requests/Trip/AssignTripRequest.php](app/Http/Requests/Trip/AssignTripRequest.php#L20-L21), [app/Http/Requests/VehicleAssignment/StoreVehicleAssignmentRequest.php](app/Http/Requests/VehicleAssignment/StoreVehicleAssignmentRequest.php#L22)

**Vấn đề**:
- `driver_id` đang validate theo `employees` (sai bảng so với spec `drivers`).
- `exists:*` không scope `company_id`, cho phép tham chiếu bản ghi tenant khác.

**Fix**:
```php
Rule::exists('drivers', 'id')->where('company_id', $this->tenantCompanyId())
```

---

### [B-01] Trip COMPLETED vẫn có thể update qua CetaSpecController
**Hạng mục**: B · **Điểm KT**: B-01
**File**: [app/Http/Controllers/Api/CetaSpecController.php](app/Http/Controllers/Api/CetaSpecController.php#L135-L154)

**Vấn đề**:
- Update không chặn `status=completed` -> vi phạm R01.

**Fix**:
```php
if ($table === 'trips' && $this->findScopedRow($table, $id)->status === 'completed') {
    abort(422, 'Không thể sửa chuyến đã hoàn tất.');
}
```

---

### [B-03] GPLX hết hạn bị chặn (phải chỉ cảnh báo)
**Hạng mục**: B · **Điểm KT**: B-03
**File**: [app/Services/Trip/TripService.php](app/Services/Trip/TripService.php#L73)

**Vấn đề**:
- Spec R03 yêu cầu cảnh báo + audit log, nhưng code đang `abort(422)`.

**Fix**:
```php
// Log cảnh báo + cho phép tiếp tục
```

---

### [B-07] Reconciliation locked không bị chặn
**Hạng mục**: B · **Điểm KT**: B-07
**File**: [app/Http/Controllers/Api/CetaSpecController.php](app/Http/Controllers/Api/CetaSpecController.php#L240), [database/migrations/2026_04_30_210000_apply_strict_spec_master_migration.php](database/migrations/2026_04_30_210000_apply_strict_spec_master_migration.php#L512-L536)

**Vấn đề**:
- Code check `locked_at` nhưng schema chỉ có `status=locked` -> update vẫn chạy.

**Fix**:
```php
->where('status', 'locked')
```

---

### [B-08] Xóa customer chỉ chặn trips “active”
**Hạng mục**: B · **Điểm KT**: B-08
**File**: [app/Http/Controllers/Api/CetaSpecController.php](app/Http/Controllers/Api/CetaSpecController.php#L166)

**Vấn đề**:
- R08 yêu cầu chặn khi có bất kỳ trip nào, không chỉ active.

**Fix**:
```php
$hasTrips = DB::table('trips')
  ->where('customer_id', $id)
  ->where('company_id', $this->companyId())
  ->exists();
```

---

### [B-10] `total_revenue` không auto-calc theo R10
**Hạng mục**: B · **Điểm KT**: B-10
**File**: [app/Http/Controllers/Api/TripController.php](app/Http/Controllers/Api/TripController.php#L439)

**Vấn đề**:
- Tính `total_revenue` dựa trên `price` và chỉ chạy khi complete.
- Không cập nhật khi `trip_surcharges` thay đổi.

**Fix**:
```php
// Observer: trips.total_revenue = base_price + surcharge_amount
```

---

### [H-01] Cron cảnh báo lệch spec WF-05
**Hạng mục**: H · **Điểm KT**: H-01
**File**: [app/Console/Commands/SendExpiryAlerts.php](app/Console/Commands/SendExpiryAlerts.php#L79-L109)

**Vấn đề**:
- Dùng `due_date`, `contract_expiry_date`, và `invoices` thay vì các field trong spec.
- Thiếu kiểm tra `drivers.expired_date`, `maintenance_schedules.next_due_km`, `payment_terms_days`.

**Fix**:
```php
// Align theo WF-05 + alert_before_days
```

---

**🟡 MEDIUM — Backlog**

| # | Vấn đề | File | Dòng | KT | Fix ngắn |
|---|--------|------|------|----|----------|
| 1 | Response format có `success/message` (khác spec) | [app/Http/Controllers/Api/BaseController.php](app/Http/Controllers/Api/BaseController.php#L22) | L22 | E-01 | Trả `{data, meta}` chuẩn spec |
| 2 | DELETE trả 200 + body | [app/Http/Controllers/Api/CetaSpecController.php](app/Http/Controllers/Api/CetaSpecController.php#L176), [app/Http/Controllers/Api/VehicleController.php](app/Http/Controllers/Api/VehicleController.php#L170) | L176/L170 | E-03 | Dùng `response()->noContent()` |
| 3 | Message lỗi tiếng Anh | [app/Http/Controllers/Api/VehicleController.php](app/Http/Controllers/Api/VehicleController.php#L100), [app/Http/Middleware/HandleApiErrors.php](app/Http/Middleware/HandleApiErrors.php#L34) | L100/L34 | E-05 | Đổi sang tiếng Việt |
| 4 | `changeVehicle` dùng `$request->validate()` | [app/Http/Controllers/Api/TripController.php](app/Http/Controllers/Api/TripController.php#L530) | L530 | F-01 | Tạo FormRequest riêng |
| 5 | Enum `vehicles.status` lệch spec | [app/Http/Requests/Vehicle/StoreVehicleRequest.php](app/Http/Requests/Vehicle/StoreVehicleRequest.php#L26), [app/Http/Requests/Vehicle/UpdateVehicleRequest.php](app/Http/Requests/Vehicle/UpdateVehicleRequest.php#L31) | L26/L31 | D-05 | Đồng bộ active/maintenance/inactive/broken |
| 6 | `driver_work_schedules.status` = `scheduled` | [app/Console/Commands/GenerateWorkSchedules.php](app/Console/Commands/GenerateWorkSchedules.php#L46) | L46 | D-05 | Dùng `draft/submitted/approved/locked` |

---

**🟢 LOW — Cải thiện dần**

| # | Vấn đề | File | Gợi ý |
|---|--------|------|-------|
| 1 | `dispatchDueAlerts` là stub | [app/Services/NotificationAlertService.php](app/Services/NotificationAlertService.php#L14-L16) | Implement logic hoặc xóa command |

---

**⚠️ MISSING — Chưa có, cần bổ sung**

| # | Thiếu gì | Mức độ | Ghi chú |
|---|----------|--------|---------|
| 1 | Cảnh báo GPLX theo `drivers.expired_date` | 🟠 High | WF-05 yêu cầu |
| 2 | Kiểm tra `maintenance_schedules.next_due_km` | 🟠 High | WF-05 yêu cầu |
| 3 | Công nợ quá hạn theo `payment_terms_days` | 🟠 High | WF-05 yêu cầu |
| 4 | Queue cho notification email/SMS | 🟡 Medium | Hiện tạo sync trong command |

---

**🗺️ ROADMAP**

**Sprint tới (Critical + Security)**
- [ ] Fix fallback tenant + ép `company_id` theo tenant
- [ ] Scope tất cả DB::table('trips') theo company_id
- [ ] Đồng bộ state machine trips theo enum spec

**2–4 tuần**
- [ ] Implement permission check trong FormRequest
- [ ] Enforce R01/R08/R07/R10 tại Service/Observer
- [ ] Chuẩn hóa response format + HTTP status code

**Backlog**
- [ ] Hoàn thiện WF-05 alerts + queue notifications
- [ ] Dọn controller legacy (TripController vs CetaSpecController)

---

**✅ ĐIỂM MẠNH**
- Có `BelongsToTenant` global scope cho phần lớn model chính.
- `TrackUserActions` tự động ghi audit log cho hầu hết API request.
- `AuthService` có refresh token + revoke token rõ ràng.
