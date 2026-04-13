# SOC 2 — Ánh xạ kiểm soát (CC6 / CC7) ↔ Company Ship API

Tài liệu này **ánh xạ** các biện pháp kỹ thuật trong repo với các chủ đề SOC 2 thường gặp (Trust Services Criteria). Đây không phải báo cáo kiểm toán hoàn chỉnh; phần vận hành (IAM, logging tập trung, DR, pentest) nằm ngoài phạm vi repo.

| Tiêu chí / chủ đề | Biện pháp trong codebase | Ghi chú vận hành |
|-------------------|---------------------------|------------------|
| **CC6.1 — Logical access** | `auth:sanctum` + middleware `role` / `permission` trên nhóm route admin (`routes/api.php`). | Quản lý user/role trên IdP hoặc quy trình onboarding/offboarding. |
| **CC6.6 — Authorization** | FormRequest cho mutating endpoints; policy-style checks trong request (trip FSM, overlap assignment). | Bổ sung policy classes theo từng domain khi phình quy mô. |
| **CC6.7 — Multi-tenant isolation (app layer)** | Cột `company_id` denormalized + `BelongsToTenant` + `EnsureTenantContext` middleware + đồng bộ `company_id` trong model `saving`. | DB hiện tại MySQL: không có RLS; xem chiến lược PG trong `docs/SOC2_DATABASE_STRATEGY.md` + SQL mẫu `docs/sql/postgresql_tenant_rls_vehicle_assignments.sql`. |
| **CC6.7 — Data integrity** | CHECK (MySQL/PG) trên `payroll_lines` cho các cột tiền ≥ 0; overlap assignment (FormRequest); FSM trip (`TripStatusRules` + `TripObserver` + `UpdateTripRequest`). | EXCLUDE gist chỉ trên PostgreSQL (migration `2026_04_20_120400_...`). |
| **CC6.8 — Immutability (payroll locked)** | `PayrollObserver` / `PayrollLineObserver` chặn mutate khi `status=locked`; `DriverPayrollCalculationService::lock` ghi `snapshot_json` + `meta_json.lock_snapshot`. | Cần backup/restore policy và quyền DBA. |
| **CC7.2 — Audit trail (CUD)** | Trait `HasAuditLogs` ghi `audit_logs` trên các model có trait. | Bảo vệ DB/table audit; retention theo chính sách. |
| **CC7.2 — Audit trail (READ nhạy cảm)** | Middleware `audit.sensitive_reads` + mở rộng schema `audit_logs` (`action=read`, `metadata`, `company_id`, …). | Tune pattern trong `config/ship.php` để tránh noise; gắn `X-Request-Id` từ edge/proxy. |
| **CC6.1 — Encryption (PII)** | `SHIP_ENCRYPT_PII` + cast `encrypted` (Driver) + tài liệu KMS/rotate trong `SOC2_ENCRYPTION_AND_KMS.md`. | Production: envelope + KMS, không chỉ `APP_KEY`. |

## File tham chiếu nhanh

- Tenancy: `app/Tenancy/TenantContext.php`, `app/Http/Middleware/EnsureTenantContext.php`, `app/Models/Concerns/BelongsToTenant.php`
- Audit read: `app/Http/Middleware/LogSensitiveResourceReads.php`, `database/migrations/2026_04_20_120100_soc2_extend_audit_logs.php`
- Payroll lock snapshot: `app/Services/DriverPayrollCalculationService.php`, `database/migrations/2026_04_20_120200_soc2_payrolls_snapshot_json.php`
- PostgreSQL RLS/EXCLUDE (mẫu + migration PG-only): `docs/sql/postgresql_tenant_rls_vehicle_assignments.sql`, `database/migrations/2026_04_20_120400_soc2_postgresql_vehicle_assignments_exclude.php`
