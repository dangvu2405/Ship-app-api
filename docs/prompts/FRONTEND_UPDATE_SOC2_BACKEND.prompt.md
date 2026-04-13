# Prompt (copy-paste) — Cập nhật Frontend theo backend SOC2 / multi-tenant

Dán toàn bộ khối dưới vào chat agent (Cursor / ChatGPT) kèm **repo frontend** và nhánh đang làm việc.

---

Bạn là senior frontend engineer (React/Next hoặc stack dự án của tôi). Hãy cập nhật ứng dụng admin/ship theo **hợp đồng API mới** từ backend Laravel.

**Nguồn sự thật (đọc trước khi sửa):** trong repo API `ship-app-api`, file `docs/FRONTEND_SOC2_BACKEND_CHANGES.md` — tuân thủ đúng header, query, field JSON và mã lỗi 422 mô tả ở đó.

**Yêu cầu cụ thể:**

1. **HTTP client (axios/fetch wrapper):**
   - Với mọi request tới prefix `/api` và `/api/v1` mà user đã đăng nhập và có role **admin**, gắn header `X-Company-Id` từ state “công ty đang chọn” (số nguyên dương). Nếu không có công ty được chọn, không gửi header (để backend dùng tenant mặc định từ `user.driver.office` hoặc null).
   - Tuỳ chọn: gửi `X-Request-Id` (UUID mới mỗi request hoặc theo page load) để audit.

2. **State quản lý công ty:**
   - Thêm UI chọn công ty (dropdown) khi user admin và có quyền xem nhiều công ty; persist lựa chọn (localStorage hoặc store) và refetch danh sách sau khi đổi.

3. **TypeScript types / OpenAPI:**
   - Bổ sung `company_id` (number) cho các entity: Driver, Vehicle, Trip, VehicleAssignment, VehicleExpense, PayrollLine; Payroll thêm `snapshot_json` (object \| null).

4. **Form tạo/sửa chuyến (trips) và phân công xe (vehicle_assignments):**
   - Sau khi chọn `driver_id`, filter danh sách `vehicle_id` chỉ còn xe cùng `company_id` với tài xế (dùng dữ liệu đã load hoặc gọi API vehicles với cùng tenant/header).
   - Hiển thị thân thiện lỗi 422 khi `errors.vehicle_id` chứa thông báo cùng công ty.

5. **Màn payroll:**
   - Khi `payroll.status === 'locked'`, ẩn/disable mọi thao tác chỉnh sửa line; hiển thị panel read-only từ `payroll.snapshot_json` và `lines[].meta_json.lock_snapshot` nếu có.

6. **Danh sách trips:**
   - Nếu UI có filter theo công ty, dùng query `company_id` — backend lọc theo `trips.company_id`.

7. **Kiểm tra:** chạy build/lint/tests frontend; rà soát không còn giả định “một tenant ngầm” mà không gửi `X-Company-Id` khi admin cần làm việc đa công ty.

**Không** đổi base URL hay format envelope JSON (`success`, `message`, `data`) trừ khi file doc API nói khác.

---

*File này chỉ là prompt; chi tiết kỹ thuật nằm trong `docs/FRONTEND_SOC2_BACKEND_CHANGES.md`.*
