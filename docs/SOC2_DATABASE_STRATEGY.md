# SOC 2 — Chiến lược cơ sở dữ liệu & đa tenant

## Quyết định (đã xác nhận cho repo này)

| Môi trường | Lựa chọn | Ghi chú |
|------------|----------|---------|
| **Production hiện tại** | **MySQL** (`ship_db`, Docker) | Không có Row-Level Security (RLS). Cách ly tenant **ở tầng ứng dụng Laravel**: middleware `TenantContext` + global scope `BelongsToTenant` + cột **`company_id`** denormalized. |
| **Mục tiêu defense-in-depth** | **PostgreSQL** (tùy chọn triển khai) | Artefact SQL mẫu: [`postgresql_tenant_rls_vehicle_assignments.sql`](./sql/postgresql_tenant_rls_vehicle_assignments.sql). Khi chuyển PG: bật RLS + `SET LOCAL app.current_company_id` + policy `USING (company_id = …::bigint)`. |

**Lý do:** RLS, `EXCLUDE USING gist`, và extension `btree_gist` là **đặc thù PostgreSQL**. Giữ MySQL cho pipeline hiện tại; tài liệu + SQL mẫu hỗ trợ roadmap SOC2 khi đổi engine.

## Khóa tenant (PK)

- `companies.id` là **BIGINT** (không dùng UUID). Mọi policy / GUC trong SQL mẫu dùng **`::bigint`**, không dùng `::uuid`.

## Liên quan

- [SOC2_CONTROL_MAPPING.md](./SOC2_CONTROL_MAPPING.md)
- [SOC2_ENCRYPTION_AND_KMS.md](./SOC2_ENCRYPTION_AND_KMS.md)
- [NGHIEP_VU_DU_AN.md](./NGHIEP_VU_DU_AN.md)
