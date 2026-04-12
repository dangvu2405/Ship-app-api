# Frontend Lark Config Handoff

Tài liệu này dành cho team Frontend để cấu hình chuẩn khi tích hợp Ship App API với Lark.

## 1) Mục tiêu frontend cần đạt

- **Đăng nhập web** bằng **email + password** qua `POST /api/v1/auth/login`, giữ Bearer token (`data.token`) cho mọi request sau (§4.0).
- Cho phép admin map `Lark User ID` vào user nội bộ khi cần (bot / thông báo Lark nhận diện user).
- Hiển thị trạng thái tích hợp Lark (bật/tắt, đã map user hay chưa) nếu product cần.
- Gọi đúng API backend; không gọi trực tiếp Lark Open API từ frontend (trừ khi product tách hẳn).

## 2) Frontend env chuẩn

Thêm vào `.env` của frontend app:

```env
VITE_API_ORIGIN=http://localhost:8080
VITE_API_PREFIX=/api/v1
VITE_LARK_ENABLED=true
```

Quy tắc build base URL:

- `API_BASE_URL = ${VITE_API_ORIGIN}${VITE_API_PREFIX}`
- Ví dụ local: `http://localhost:8080/api/v1`

## 3) Header bắt buộc

Mọi request cần:

```http
Authorization: Bearer <token>
Content-Type: application/json
Accept: application/json
```

Envelope response luôn theo chuẩn:

- Success: `{ success: true, message: string, data: any }`
- Error: `{ success: false, message: string, errors?: object }`

## 4) Đăng nhập web và cấu hình Lark (webhook / map user)

### 4.0 Đăng nhập email + password

- `POST /api/v1/auth/login` — body JSON: `{ "email": "...", "password": "..." }`.
- Thành công: `data.user`, `data.token` (Bearer Sanctum); gửi `Authorization: Bearer <data.token>` cho các API sau (mục §3).
- `401`: sai thông tin hoặc tài khoản không active.
- `422`: validation (`errors` theo field).

(Có thể dùng thêm `POST /api/auth/login` không version — cùng handler.)

### 4.0.1 Webhook — lỗi "Challenge code didn't get response"

Khi cấu hình **Event subscription → Request URL**, Lark **POST** JSON kiểu `url_verification` và cần response JSON **`{"challenge":"..."}`**. Endpoint trong Ship App: **`POST https://<host>/api/lark/webhook`** (hoặc **`POST /api/v1/lark/webhook`**).

| Mục đích | Chỗ điền trên Lark Developer Console | URL ví dụ |
|----------|----------------------------------------|-----------|
| Bot / sự kiện | **Request URL** (Events) | `https://<host>/api/lark/webhook` |

Backend cần **`LARK_VERIFICATION_TOKEN`** trong `.env` trùng token trên console (và `php artisan config:clear` sau khi sửa). Nếu bật **Encrypt Key** trên Lark mà backend chưa giải mã, bước verify cũng sẽ fail. **Cloudflare trycloudflare** đôi khi chặn request máy chủ Lark — thử `curl` POST tới URL hoặc dùng domain ổn định.

### 4.1 Map user nội bộ với Lark user

Hiện tại dùng endpoint user hiện có:

- `GET /api/v1/users`
- `PUT /api/v1/users/{id}`

Field cần gửi khi update:

```json
{
  "lark_user_id": "ou_xxxxxxxxxxxxx"
}
```

Lưu ý:

- `lark_user_id` phải unique.
- Nếu backend trả 422, frontend hiển thị validation message từ `errors`.

### 4.2 Kiểm tra trạng thái backend

- `GET /api/health` hoặc `GET /api/v1/health`
- Mục đích: xác nhận backend online trước khi thao tác trang cấu hình Lark.

## 5) Quy ước UI cho trang cấu hình Lark

- Thêm cột `lark_user_id` trong bảng Users (admin).
- Trong form update user:
  - Input `Lark User ID`
  - Hint format: `ou_...`
- Badge trạng thái:
  - `Linked` khi có `lark_user_id`
  - `Unlinked` khi null/rỗng

## 6) Luồng xử lý lỗi chuẩn trên frontend

- `401`: clear token + redirect login.
- `403`: hiển thị thông báo không đủ quyền.
- `422`: map field errors vào form.
- `500`: toast lỗi chung, cho phép retry.

## 7) RBAC cần phản ánh trên UI

- Chỉ `admin` được sửa user mapping (`lark_user_id`).
- `driver` không được truy cập màn hình payroll quản trị.
- `hr` không hiển thị thao tác assign vehicle.

## 8) Checklist verify sau khi frontend cấu hình

1. Login bằng admin thành công.
2. Mở Users list thấy cột `lark_user_id`.
3. Cập nhật `lark_user_id` cho 1 user và lưu thành công.
4. Reload trang vẫn thấy mapping đã lưu.
5. Role không đủ quyền không thấy nút sửa mapping.
6. Health check hiển thị trạng thái online.

## 9) Những gì frontend không làm

- Không ký webhook signature ở frontend.
- Không gọi trực tiếp Lark Open API bằng secret.
- Không lưu Lark app secret trong frontend env.

Frontend chỉ làm UI + gọi backend API. Toàn bộ security/integration với Lark được xử lý ở backend.

## 10) Lark đã đảm nhiệm gì — lọc chức năng trùng trên Ship App

Nguyên tắc: **Ship App vẫn là nguồn dữ liệu và luật nghiệp vụ (source of truth)**. Lark là **kênh tương tác và thông báo**, không thay thế toàn bộ admin web.

### 10.1 Việc Lark đang (hoặc có thể) đảm nhiệm trong tích hợp hiện tại

| Khu vực | Lark làm gì | Ghi chú |
|--------|-------------|---------|
| Thông báo | Push tin vào group chat (trip tạo, payroll duyệt, gán xe) | Backend queue → Lark IM |
| Lệnh nhanh | Bot xử lý `/trip`, `/status`, `/payroll` trong chat | Chỉ user đã map `lark_user_id` |
| Đồng bộ mirror | Lark Base nhận bản sao employee/trip (nếu bật config) | Không phải hệ thống chính để sửa dữ liệu |
| Xác thực webhook | Challenge + chữ ký request | Chỉ backend |

### 10.2 Nên giữ nguyên trên Ship App (không “bỏ” vì Lark)

- Toàn bộ CRUD nghiệp vụ (nhân sự, chuyến, lương, xe, phân quyền).
- Luồng trạng thái payroll (`draft` → `approved` → `locked`) và kiểm soát sửa/xóa.
- Báo cáo, export, audit theo yêu cầu ERP.
- Đăng nhập web + RBAC theo role/permission.

### 10.3 Có thể lọc bớt / đơn giản hóa trên frontend (tránh trùng với Lark)

1. **Quick action trùng bot**
   - Nếu đội vận hành chủ yếu dùng Lark: có thể **ẩn hoặc thu gọn** các widget “xem nhanh trip / trạng thái tài xế / tóm tắt payroll theo tháng” trên web nếu nội dung trùng với `/trip`, `/status`, `/payroll`.
   - Hoặc giữ bản rút gọn cho user **chưa link Lark** (`lark_user_id` trống).

2. **Thông báo kép**
   - Nếu cùng một sự kiện đã push Lark group: cân nhắc **tắt** hoặc gom **in-app notification / email** cho cùng event (tránh spam). Cấu hình theo preference từng môi trường.

3. **Bảng mirror kiểu spreadsheet**
   - Nếu đã dùng Lark Base làm “bảng nhìn nhanh”: **không cần** xây thêm màn hình web clone chỉ để xem cùng bộ cột — ưu tiên deep-link hoặc hướng dẫn mở Base.

4. **Chat nội bộ trong app**
   - Nếu chat nghiệp vụ chuyển hết sang Lark: có thể **ẩn menu chat nội bộ** hoặc giữ chỉ **AI assistant** (nếu product tách bạch).

### 10.4 Không nên coi là “Lark đã làm” nếu chưa triển khai

- Phê duyệt payroll / khóa payroll **chỉ qua Lark** (hiện tại vẫn qua API web + admin).
- Reverse sync từ Lark Base về DB đã có ở chế độ **update-only** qua lệnh backend `php artisan lark:reverse-sync --entity=all --limit=200` khi `LARK_BASE_ENABLE_REVERSE_SYNC=true`; chưa bật auto-write mặc định và chưa tạo mới bản ghi thiếu trong DB.

Tóm lại: **lọc UI/feature trùng ở lớp “xem nhanh + thông báo + bảng mirror”**; **giữ đầy đủ luồng ERP và quyền trên Ship App**.
