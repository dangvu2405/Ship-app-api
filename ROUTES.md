# API Routes Detail

Tài liệu tóm tắt các API chính đang dùng trong backend.
Prefix chuẩn nên dùng cho frontend: `/api/v1`.

## Base / Health

### `GET /api/v1/health`
- **Mục đích**: kiểm tra backend còn sống.
- **Auth**: không cần.
- **Response**:
  - `success`: `true`
  - `message`: `"API is running"`
  - `timestamp`: thời gian server.

### `GET /api`
- **Mục đích**: thông tin cơ bản API.
- **Auth**: không cần.

## Auth APIs (`/api/v1/auth`)

### `POST /api/v1/auth/social/login`
- **Mục đích**: đăng nhập bằng social (Google/Facebook/Apple).
- **Auth**: không cần.
- **Body**:
  - `provider`: `google | facebook | apple` (bắt buộc)
  - `access_token`: string (tùy provider)
  - `id_token`: string (tùy provider)
- **Response thành công**:
  - `data.user`: thông tin user
  - `data.token`: sanctum token

### `POST /api/v1/auth/forgot-password`
- **Mục đích**: gửi link reset mật khẩu.
- **Auth**: không cần.
- **Body**:
  - `email`: email tài khoản
- **Response thành công**:
  - `message`: `"Password reset link sent"`

### `POST /api/v1/auth/reset-password`
- **Mục đích**: đặt mật khẩu mới bằng token reset.
- **Auth**: không cần.
- **Body**:
  - `email`: email tài khoản
  - `token`: reset token
  - `password`: mật khẩu mới
  - `password_confirmation`: xác nhận mật khẩu
- **Response thành công**:
  - `message`: `"Password reset successful"`

### `POST /api/v1/auth/logout`
- **Mục đích**: đăng xuất tài khoản hiện tại.
- **Auth**: `Bearer token` bắt buộc.

### `POST /api/v1/auth/refresh`
- **Mục đích**: làm mới token.
- **Auth**: `Bearer token` bắt buộc.
- **Response**:
  - `data.token`: token mới.

### `GET /api/v1/auth/me`
- **Mục đích**: lấy profile user hiện tại.
- **Auth**: `Bearer token` bắt buộc.

### `POST /api/v1/auth/register`
- **Mục đích**: tạo user mới.
- **Auth**: `auth:sanctum` + role `admin`.

## User APIs

### `GET /api/v1/user`
- **Mục đích**: endpoint profile user đăng nhập (legacy alias).
- **Auth**: `Bearer token` bắt buộc.

### `GET|POST|PUT|PATCH|DELETE /api/v1/users...`
- **Mục đích**: CRUD user (admin).
- **Auth**: admin.

## Chat APIs

### `GET /api/v1/chat/sessions`
- **Mục đích**: danh sách phiên chat của user.
- **Auth**: `Bearer token`.

### `DELETE /api/v1/chat/sessions/{sessionId}`
- **Mục đích**: xóa phiên chat.
- **Auth**: `Bearer token`.

### `GET /api/v1/chat/messages`
- **Mục đích**: lấy lịch sử tin nhắn.
- **Auth**: `Bearer token`.

### `POST /api/v1/chat/messages`
- **Mục đích**: gửi tin nhắn thường.
- **Auth**: `Bearer token`.

### `POST /api/v1/chat/messages/stream`
- **Mục đích**: gửi tin nhắn dạng stream.
- **Auth**: `Bearer token`.

## Payroll / Report APIs

### `GET /api/v1/payrolls/my-salary`
- **Mục đích**: user xem lương của chính mình.
- **Auth**: `Bearer token`.

### `POST /api/v1/payrolls/{id}/approve`
### `POST /api/v1/payrolls/{id}/lock`
### `GET /api/v1/payrolls/{id}/export`
- **Mục đích**: tác vụ duyệt/chốt/xuất payroll.
- **Auth**: admin.

### `GET /api/v1/reports/dashboard`
### `GET /api/v1/reports/payroll-summary`
- **Mục đích**: số liệu tổng hợp.
- **Auth**: admin.

## Resource APIs (CRUD)

Các module có full REST (index/store/show/update/destroy) dưới `/api/v1`:

- `companies`
- `offices`
- `departments`
- `positions`
- `drivers`
- `vehicles`
- `vehicle_assignments`
- `vehicle_expenses`
- `customers`
- `trips`
- `trip_bonus_rules`
- `invoices`
- `payrolls`
- `roles`
- `permissions` (chủ yếu read + map quyền)

## Docs / System

- `GET /docs`: Swagger UI
- `GET /api/documentation`: OpenAPI JSON
- `GET /sanctum/csrf-cookie`: Sanctum cookie route
- `GET /up`: health check mặc định Laravel

## Ghi chú tương thích

- Legacy prefix `/api/...` vẫn tồn tại cho nhiều route.
- Frontend mới nên chuẩn hóa gọi qua `/api/v1/...`.

## Export full route list

```bash
php artisan route:list > route-list.txt
```
