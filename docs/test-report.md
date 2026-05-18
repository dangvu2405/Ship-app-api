# E2E Test Report

**Date:** 2026-05-14  
**Tool:** Playwright (Chromium)  
**Working directory:** `ship-app/`

## Summary

| Total | Passed | Failed | Skipped |
|-------|--------|--------|---------|
| 183   | 157    | 0      | 26      |

Duration: ~4.8 minutes

---

## Results by Spec

| Spec file | Tests | Status |
|-----------|-------|--------|
| `accounting.spec.ts` | — | ✅ |
| `auth.spec.ts` | — | ✅ |
| `auth-flows.spec.ts` | — | ✅ |
| `billing.spec.ts` | — | ✅ |
| `companies.spec.ts` | — | ✅ |
| `customers.spec.ts` | — | ✅ |
| `dashboard.spec.ts` | — | ✅ |
| `detail-pages.spec.ts` | — | ✅ |
| `dispatch.spec.ts` | — | ✅ |
| `driver-schedule.spec.ts` | — | ✅ |
| `drivers.spec.ts` | — | ✅ |
| `invoices.spec.ts` | — | ✅ |
| `navigation.spec.ts` | — | ✅ |
| `orders-pool.spec.ts` | — | ✅ |
| `payroll-adjustments.spec.ts` | — | ✅ |
| `payroll-hr.spec.ts` | — | ✅ |
| `permissions.spec.ts` | — | ✅ |
| `reports.spec.ts` | — | ✅ |
| `settings-profile.spec.ts` | — | ✅ |
| `trip-bonus-rules.spec.ts` | — | ✅ |
| `trips.spec.ts` | — | ✅ |
| `users.spec.ts` | — | ✅ |
| `vehicle-expenses.spec.ts` | — | ✅ |
| `vehicles.spec.ts` | 7 | ⚠️ flaky (xem bên dưới) |
| `violations.spec.ts` | — | ✅ |
| `workforce-ops.spec.ts` | — | ✅ |

---

## Flaky Tests — `vehicles.spec.ts`

Khi chạy isolated (`npx playwright test vehicles.spec.ts`), 2 tests bị fail do timeout:

### 1. `renders empty state when no vehicles`
- **Line:** [vehicles.spec.ts:84](ship-app/tests/e2e/vehicles.spec.ts#L84)
- **Lỗi:** `getByText(/không có dữ liệu|no data|chưa có bản ghi/i)` không tìm thấy trong 5000ms
- **Nguyên nhân có thể:** App dùng text khác cho empty state (e.g. "Không có xe", "Danh sách trống")
- **Gợi ý fix:** Kiểm tra actual empty state text trong component, mở rộng regex hoặc dùng `getByText(/không có/i)`

### 2. `opens create vehicle dialog`
- **Line:** [vehicles.spec.ts:110](ship-app/tests/e2e/vehicles.spec.ts#L110)
- **Lỗi:** Button `getByRole('button', { name: /tạo xe|tạo mới|thêm xe|thêm/i })` không tìm thấy
- **Nguyên nhân có thể:** Button label khác (e.g. "Thêm mới", "Tạo") hoặc user không có quyền tạo xe
- **Gợi ý fix:** Kiểm tra actual button text, mở rộng regex pattern

> Cả 2 tests đều **pass trong full suite run** — có thể do race condition với global setup hoặc thứ tự chạy.

---

## Notes

- 26 tests bị skip (chưa implement hoặc dùng `test.skip`)
- Tất cả tests đều mock API, không cần backend thật
- Global setup: auth state được lưu vào `playwright/.auth/admin.json`
