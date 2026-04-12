# Frontend ↔ Backend — Overview (ship-app-api)

Tài liệu trong repo backend dùng để **đồng bộ contract** với frontend (Refine / Ant Design / axios).

| Tài liệu | Nội dung |
|----------|-----------|
| [FRONTEND_API_ENDPOINTS.md](./FRONTEND_API_ENDPOINTS.md) | Liệt kê path HTTP (theo route Laravel hiện tại). |
| [FRONTEND_PAYLOAD_BY_SCREEN.md](./FRONTEND_PAYLOAD_BY_SCREEN.md) | Query list + JSON body theo từng màn (FE → BE). |
| [FRONTEND_MUST_HAVE_SCHEMA_HANDOFF.md](./FRONTEND_MUST_HAVE_SCHEMA_HANDOFF.md) | Schema MUST HAVE (leave, payroll lines, payslip, GL…) — payload §8, response §9. |

## Base URL & prefix

- **Laravel (`routes/api.php`):** mặc định prefix **`/api`** (vd. `POST /api/auth/login`, `GET /api/companies`).
- Frontend có thể cấu hình **`/api/v1`** qua gateway; khi đó cần **rewrite** hoặc đổi `baseURL` cho khớp server thật.

## Envelope JSON

Thành công / lỗi: `{ "success", "message", "data" | "errors" }` — xem rule dự án và `BaseController`.
