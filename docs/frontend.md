# Frontend Integration Guide (Company Ship ERP)

Tài liệu này được trích xuất từ Spec Hệ thống để giúp đội ngũ Frontend (React/Vite) làm việc với API hiệu quả, không phải tra cứu vào codebase của Backend.

---

## 1. Cấu hình môi trường (Config)

API được chạy dưới Docker. Do đó, Frontend cần proxy các request có đuôi `/api` tới Backend URL:
- **Local Dev URL:** `http://localhost:8080/api`
- Hãy cấu hình `VITE_API_ORIGIN=http://localhost:8080` trong `.env` của thư mục `ship-app`. Setup Vite proxy để tránh vi phạm CORS.

---

## 2. Chuẩn mực Dữ liệu (Response Envelope)

MỌI request đều sẽ trả về cấu trúc Envelope. Đừng truy cập data trực tiếp từ `response.data` của Axios mà chưa bóc tách lớp Envelope.

**Khi gọi thành công (HTTP 200, 201):**
```json
{
  "success": true,
  "message": "Thông báo thân thiện để bạn hiển thị Toast message",
  "data": { 
      // Các trường dữ liệu chính (hoặc mảng array)
      "id": 1,
      "name": "..." 
  }
}
```

**Khi có lỗi (HTTP 400, 401, 403, 422, 500):**
```json
{
  "success": false,
  "message": "Lỗi thao tác,...",
  "errors": {
      // Chi tiết lỗi (rất hữu ích khi form validation fail - mã HTTP 422)
      "email": ["Email format is invalid"],
      "password": ["Password is required"]
  }
}
```

---

## 3. Xác thực (Authentication) & Headers

1. **Đăng nhập:** `POST /api/v1/auth/login` với JSON `{ "email", "password" }`; response `data.user` và `data.token` (Bearer Sanctum). Chi tiết: [FRONTEND_LARK_CONFIG.md](./FRONTEND_LARK_CONFIG.md) §4.0.
2. **Lưu trữ:** Lưu `token` vào `localStorage` (hoặc HttpOnly Cookie tuỳ bảo mật của bạn).
3. **Mọi Request sau đó:** Phải Attach config cho Axios:
   ```js
   headers: {
       "Authorization": `Bearer ${localStorage.getItem('token')}`,
       "Content-Type": "application/json",
       "Accept": "application/json"
   }
   ```
4. Khi nhận mã `HTTP 401` từ bất kỳ request nào: Chặn người dùng và Redirect về trang `/login` ngay lập tức! (Định tuyến frontend).

---

## 4. Danh mục Vai trò (Roles) và Trạng thái UI

`User` object lấy được từ login sẽ chứa mảng `roles` và `permissions`. Frontend cần xây dựng AuthGuard/HOC để ẩn hoặc hiện các Module Menu, Nút điều hướng dựa vào các Role dưới đây:

| Role Code | Chức vụ | Quyền kiểm soát Menu / Nút bấm |
|-----------|---------|---------------------------------|
| `admin` | Quản trị Hệ thống | Nhìn thấy mọi thứ (Org, HR, Lương tổng hợp, Chuyến đi, v.v...) |
| `hr` | Nhân sự / HR | Chỉ xem Nhân viên, Chấm công, Cấu hình lương. |
| `accountant`| Kế toán | Tạo/Xem/Duyệt Lương; Xem hóa đơn; Xem Report hệ thống. |
| `coordinator`| Điều phối | Chỉ xem Menu Xe, Gán xe, Kiểm soát Chuyến đi. |
| `driver` | Tài xế | Cập nhật App di chuyển (Update Trạng thái chuyến đi của mình). |
| `staff` | Nhân viên thường | Hạn chế nhất: Chỉ xem `My Salary` và Profile. |

---

## 5. Nghiệp vụ theo dõi Trạng thái (Luồng chính)

Đừng hiển thị tuỳ tiện Nút bấm cho user. Cần đọc `status` của Object trả về.

**A. Payroll (Bảng Lương) Status Flow:**
`draft` → `approved` → `locked`
- Ở trạng thái **`draft`**: Được phép (hiển thị) update, xoá bảng lương. Được phép bấm "Duyệt".
- Ở trạng thái **`approved`**: Chỉ còn hiển thị nút "Khóa". KHÔNG ĐƯỢC CHỈNH SỬA DETAIL NỮA.
- Ở trạng thái **`locked`**: Tất cả form Input chuyển sang Read-only. Sinh nút "Xuất Excel".

**B. Trips (Chuyến đi) Status Flow:**
`pending` → `in_progress` → `completed`
- Frontend nên có UI kéo thả (Kanban) hoặc Dropdown thay đổi trạng thái theo thứ tự chiều tiến lên trên.
