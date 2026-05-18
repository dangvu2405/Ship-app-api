# DỮ LIỆU CONTEXT DỰ ÁN HỆ THỐNG QUẢN LÝ VẬN TẢI / LOGISTICS (CETA / COMPANY SHIP)

File này chứa toàn bộ context kỹ thuật và quản trị dự án được trích xuất từ source code `ship-app` (Frontend) và `ship-app-api` (Backend), cung cấp dữ liệu cho ChatGPT viết báo cáo môn Quản trị Dự án CNTT.

---

## 1. THÔNG TIN TỔNG QUAN DỰ ÁN
- **Tên dự án**: Company Ship (CETA) - Hệ thống Quản lý Vận tải (Transportation Management System - TMS).
- **Mục tiêu dự án**: Chuyển đổi số quy trình vận tải, quản lý tập trung đơn hàng, đội xe, tài xế, tài chính và cung cấp hệ thống trợ lý ảo AI hỗ trợ nghiệp vụ.
- **Bài toán nghiệp vụ**: Tối ưu hóa việc phân công xe/tài xế cho chuyến đi, theo dõi trạng thái chuyến, quản lý chi phí phát sinh, công nợ khách hàng và tính lương tự động cho tài xế/nhân viên.
- **Đối tượng sử dụng**: Quản trị viên (Admin), Điều phối viên (Dispatcher), Kế toán (Accountant), Quản lý đội xe (Fleet Manager), Tài xế (Driver), Khách hàng (Customer).
- **Phạm vi hệ thống**: Frontend SPA cho người dùng nội bộ, RESTful API Backend, Tích hợp AI Chatbot RAG, Hệ thống quản lý tài liệu và kế toán.
- **Các chức năng chính đã hoàn thiện**:
  - Đăng nhập, phân quyền Role/Permission.
  - Quản lý chuyến đi (Trips) và chi phí chuyến đi.
  - Quản lý danh mục: Xe, Tài xế, Khách hàng, Bảng giá.
  - Dispatch Board (Bảng điều phối).
  - Quản lý Kế toán: Hóa đơn (Invoices), Đối soát (Reconciliations), Thanh toán.
  - Quản lý Lương (Payrolls).
  - Trợ lý AI (Chatbot RAG) tích hợp kiến thức nội bộ.
- **Các chức năng đang lỗi hoặc chưa hoàn thiện**: 
  - Tích hợp thiết bị GPS phần cứng thật (chưa có, đang quản lý trạng thái thủ công).
  - Module `salary-adjustments` chỉ đang có stubs (trả về 501 Not Implemented).
  - Legacy workforce (chưa migrate hoàn toàn).

---

## 2. THÔNG TIN CÔNG NGHỆ
- **Frontend**: React 18, TypeScript 5.5.
- **Backend**: Laravel 12, PHP 8.2.
- **Database**: SQLite (dùng cho local/dev) / MySQL (dự kiến cho Production).
- **Thư viện UI**: Ant Design 5 (`antd`), Tailwind CSS (`tailwindcss`).
- **Thư viện xác thực**: Laravel Sanctum (Cookie-based, Stateful SPA Authentication), Axios Interceptor xử lý CSRF.
- **Thư viện quản lý state**: Zustand, TanStack React Query.
- **Thư viện gửi email**: SMTP (cấu hình qua Mailer của Laravel, máy chủ `mail93142.maychuemail.com`).
- **Thư viện gọi API AI**: API của **Groq** (`llama-3.3-70b-versatile`) và **Google Gemini API** (`gemini-2.0-flash`).
- **Công cụ build/deploy**: Vite (Frontend), Playwright (E2E Test).
- **File cấu hình quan trọng**: 
  - Backend: `.env` (chứa khóa API, config SMTP, cấu hình DB).
  - Frontend: `package.json`, `vite.config.ts`, `tailwind.config.js`.

---

## 3. KIẾN TRÚC HỆ THỐNG
- **Kiến trúc tổng thể**: Client-Server (SPA - Single Page Application gọi RESTful API).
- **Luồng Frontend gọi Backend**: 
  1. Frontend (React) sử dụng Axios gửi request.
  2. Axios Interceptors tự động đính kèm thông tin xác thực (`X-XSRF-TOKEN` cookie).
  3. Xử lý lỗi toàn cục (Global Error Handling) đưa ra Toast notification.
- **Luồng Backend xử lý nghiệp vụ**: 
  1. Router phân giải URL.
  2. Middleware (`auth:sanctum`, `tenant.context`) kiểm tra quyền.
  3. Controller nhận request, ủy quyền cho FormRequest để Validate.
  4. Service Layer (`ChatService`, `TripService`,...) xử lý nghiệp vụ lõi.
  5. Repository / Eloquent Model kết nối Database để thao tác dữ liệu.
  6. Controller trả về JSON Resource.
- **Luồng Backend kết nối Database**: Sử dụng Eloquent ORM, Migration để tạo bảng, Seeder để nạp dữ liệu mẫu (BulkDataSeeder).
- **Luồng Email OTP**: User nhấn quên mật khẩu -> Backend tạo mã OTP lưu vào cache/database -> Backend qua SMTP gửi mail chứa OTP -> User nhập OTP -> Trả về token khôi phục -> Cập nhật mật khẩu mới.
- **Luồng Chatbot RAG**: 
  1. User nhập câu hỏi vào cửa sổ Chat.
  2. Request gửi đến `ChatController@store` hoặc `stream`.
  3. `ChatService` gọi `ChatRagService` truy vấn Vector Index (`rag_index`) hoặc văn bản (`knowledge_articles`) để tìm Context liên quan.
  4. Nối Context + Câu hỏi tạo thành Prompt.
  5. Gửi Prompt tới Groq API / Gemini API.
  6. Nhận kết quả và stream trả về Frontend qua giao thức SSE (Server-Sent Events).
- **Sơ đồ kiến trúc đề xuất cho báo cáo**: 
  - Mô hình 3 lớp MVC kết hợp Service Repository.
  - Cụm Frontend (React) <---> Cụm API Server (Laravel) <---> Cụm Database & Cụm 3rd Party APIs (Groq/Gemini, SMTP).

---

## 4. DANH SÁCH MODULE / CHỨC NĂNG

| STT | Module | Chức năng | Actor | File frontend liên quan | API liên quan | Controller/Service liên quan | Bảng database liên quan | Trạng thái |
|---|---|---|---|---|---|---|---|---|
| 1 | Xác thực | Đăng nhập, Đăng xuất, Khôi phục mật khẩu | Mọi user | `src/pages/auth/*` | `/api/auth/login`, `/api/auth/logout`, `/api/auth/forgot-password` | `AuthController.php` | `users`, `personal_access_tokens` | Hoàn thiện |
| 2 | Người dùng | Quản lý User, cập nhật trạng thái | Admin | `src/pages/users/*` | `/api/users` | `UserController.php` | `users`, `user_roles` | Hoàn thiện |
| 3 | Phân quyền | Cấp quyền Role, Permission | Admin | `src/pages/settings/*` | `/api/users/{user}/permissions` | `UserController.php` | `roles`, `permissions`, `role_permissions` | Hoàn thiện |
| 4 | Vận hành | Quản lý Chuyến đi (Trips), gán xe, tài xế, đổi trạng thái | Điều phối | `src/pages/trips/*`, `src/pages/dispatch/*` | `/api/trips`, `/api/trips/{id}/assign` | `TripController.php`, `DispatchBoardController.php` | `trips`, `trip_status_histories` | Hoàn thiện |
| 5 | Đội xe | Quản lý xe (Vehicles), loại xe, phân công xe | Quản lý đội xe | `src/pages/vehicles/*` | `/api/vehicles`, `/api/vehicle-assignments` | `VehicleController.php`, `VehicleAssignmentController.php` | `vehicles`, `vehicle_assignments` | Hoàn thiện |
| 6 | Tài xế | Quản lý tài xế, giấy phép, điểm danh | Quản lý | `src/pages/drivers/*`, `src/pages/operations/*` | `/api/drivers`, `/api/attendance` | `DriverController.php`, `AttendanceController.php` | `drivers`, `attendances` | Hoàn thiện |
| 7 | Khách hàng | Quản lý khách hàng, nhóm KH, bảng giá | Kế toán, Admin | `src/pages/customers/*` | `/api/customers`, `/api/price-lists` | `CustomerController.php`, `PriceListController.php` | `customers`, `price_lists` | Hoàn thiện |
| 8 | Lịch làm việc | Quản lý lịch tài xế, HOS, xin nghỉ phép | Tài xế, Quản lý | `src/pages/schedules/*`, `src/pages/leave/*` | `/api/driver-work-schedules`, `/api/leave-requests` | `DriverWorkScheduleController.php`, `LeaveController.php` | `driver_work_schedules`, `leave_requests` | Hoàn thiện |
| 9 | Chatbot RAG | Trợ lý ảo tư vấn nghiệp vụ, tra cứu kiến thức | Mọi user | `FloatingChatAssistant.tsx` | `/api/chat/messages/stream` | `ChatController.php`, `ChatService.php` | `chat_messages`, `knowledge_articles`, `rag_index` | Hoàn thiện |
| 10 | Kế toán | Quản lý hóa đơn, đối soát chi phí, lương | Kế toán | `src/pages/accounting/*`, `src/pages/invoices/*` | `/api/invoices`, `/api/payrolls`, `/api/reconciliations` | `InvoiceController.php`, `PayrollsController.php` | `invoices`, `payrolls`, `reconciliations` | Hoàn thiện |
| 11 | Báo cáo | Dashboard thống kê doanh thu, chuyến đi | Admin, Quản lý | `src/pages/dashboard/*`, `src/pages/reports/*` | `/api/reports/*` | `ReportsController.php` | Các bảng tổng hợp | Hoàn thiện |

---

## 5. API BACKEND NỔI BẬT

*Hệ thống có khoảng 100+ endpoints (RESTful routing), dưới đây là các API tiêu biểu:*

| Method | Endpoint | Chức năng | Request body | Response chính | Middleware/Auth | Controller xử lý |
|---|---|---|---|---|---|---|
| POST | `/api/auth/login` | Đăng nhập | `{email, password}` | User info, Session cookie | `throttle:5,1` | `AuthController@login` |
| POST | `/api/auth/forgot-password` | Quên mật khẩu | `{email}` | Success message | `throttle:3,1` | `AuthController@forgotPassword` |
| GET | `/api/users` | Danh sách User | Params: `page, limit` | List User JSON | `auth:sanctum`, `tenant` | `UserController@index` |
| POST | `/api/trips` | Tạo chuyến đi mới | `{customer_id, pickup_location, dropoff_location, cargo_type_id, ...}` | Trip JSON | `auth:sanctum` | `TripController@store` |
| PATCH | `/api/trips/{id}/assign` | Phân công tài xế/xe | `{driver_id, vehicle_id}` | Trip JSON | `auth:sanctum` | `TripController@assign` |
| GET | `/api/dispatch/board` | Lấy dữ liệu bảng điều phối | Date params | Trips & Vehicles Data | `auth:sanctum` | `DispatchBoardController@board` |
| POST | `/api/chat/messages/stream` | Hỏi Chatbot RAG | `{message, session_id}` | SSE Text Stream | `auth:sanctum` | `ChatController@stream` |
| GET | `/api/invoices` | Danh sách hóa đơn | Params | List Invoice JSON | `auth:sanctum` | `InvoiceController@index` |
| POST | `/api/payrolls/generate` | Chạy bảng lương | `{period_start, period_end}` | Payroll JSON | `auth:sanctum` | `PayrollsController@generate` |

**Ghi chú lỗi/đặc biệt**: API `/api/salary-adjustments` (Stubs) hiện đang trả về HTTP 501 Not Implemented để Frontend xử lý thông báo "Tính năng đang phát triển".

---

## 6. DATABASE SCHEMA

Hệ thống có khoảng hơn 50 bảng trong cơ sở dữ liệu. Dưới đây là các bảng cốt lõi:

| Tên bảng | Mục đích | Cột chính | Quan hệ (Khóa ngoại) | Module sử dụng |
|---|---|---|---|---|
| `users` | Lưu thông tin tài khoản | `id`, `name`, `email`, `password`, `is_active` | 1-n với `roles` | Xác thực, Phân quyền |
| `companies` | Quản lý công ty / chi nhánh | `id`, `name`, `tax_code`, `address` | 1-n `users`, `trips` | Đa chi nhánh (Multi-tenant) |
| `drivers` | Thông tin tài xế | `id`, `user_id`, `license_number`, `status` | `user_id` -> `users` | Quản lý đội xe |
| `vehicles` | Thông tin phương tiện | `id`, `plate_number`, `vehicle_type_id`, `status` | `vehicle_type_id` | Quản lý đội xe |
| `trips` | Đơn vận chuyển / Chuyến đi | `id`, `code`, `customer_id`, `driver_id`, `vehicle_id`, `status`, `total_amount` | Khách hàng, Tài xế, Xe | Vận hành chuyến đi |
| `invoices` | Hóa đơn tính cước | `id`, `trip_id`, `total_amount`, `status` | `trip_id` -> `trips` | Kế toán |
| `payrolls` | Bảng lương tài xế/nhân viên | `id`, `user_id`, `period`, `net_pay` | `user_id` -> `users` | Kế toán lương |
| `chat_messages` | Lịch sử chat RAG AI | `id`, `user_id`, `session_id`, `message`, `response` | `user_id` -> `users` | Chatbot |
| `knowledge_articles`| Dữ liệu kiến thức RAG | `id`, `title`, `content`, `category` | | Chatbot RAG |

---

## 7. WORKFLOW NGHIỆP VỤ (Quy trình tiêu biểu)

### Workflow Quản lý Chuyến đi (Trip Lifecycle)
1. **Actor**: Điều phối viên (Dispatcher), Tài xế (Driver).
2. **Điều kiện bắt đầu**: Có yêu cầu vận chuyển từ khách hàng.
3. **Luồng xử lý**:
   - Điều phối viên tạo `Trip` mới (Trạng thái: DRAFT).
   - Điều phối viên phân công Xe và Tài xế (Trạng thái: ASSIGNED).
   - Tài xế xác nhận nhận chuyến trên app (Trạng thái: IN_PROGRESS).
   - Tài xế đến điểm giao, chụp ảnh xác nhận (Trạng thái: DELIVERED).
   - Kế toán kiểm tra chứng từ chi phí và đóng chuyến (Trạng thái: COMPLETED).
4. **Trường hợp lỗi**: Hỏng xe dọc đường -> Đổi xe (Trạng thái: CANCELLED hoặc gán lại).

### Workflow Chatbot RAG (AI Assistant)
1. **Actor**: Người dùng hệ thống.
2. **Điều kiện bắt đầu**: Người dùng mở cửa sổ ChatAssistant và gửi câu hỏi.
3. **Luồng xử lý**:
   - Hệ thống quét câu hỏi.
   - Truy vấn Vector database (`knowledge_articles`) tìm tài liệu nghiệp vụ (VD: Quy trình đóng gói).
   - Nén tài liệu + câu hỏi gửi lên Groq/Gemini API.
   - Nhận Stream response hiển thị real-time cho user.
4. **Trường hợp lỗi**: Lỗi API Groq (Rate limit 429) -> Trả về câu trả lời Fallback nội bộ.

---

## 8. DỮ LIỆU CHO BIỂU ĐỒ UML

| Tên biểu đồ | Mục đích | Actor | Thành phần | Luồng chính |
|---|---|---|---|---|
| Use Case Tổng Quát | Mô tả các tính năng chính | Admin, Kế toán, Điều phối, Tài xế | Các module (Quản lý chuyến, Kế toán, Báo cáo, Hệ thống) | User -> Đăng nhập -> Thao tác theo phân quyền. |
| Activity: Tạo chuyến đi | Mô phỏng quy trình điều phối | Điều phối viên | Màn hình List Trip -> Form Create Trip -> DB | Nhập thông tin -> Validate -> Chọn Xe/Tài xế -> Lưu Database. |
| Sequence: Đăng nhập | Trình tự gọi API Login | User | Frontend, API Server, Database | Gửi email/pass -> Xác thực Hash -> Cấp Cookie Sanctum -> Chuyển hướng Dashboard. |
| Sequence: Chatbot RAG | Trình tự sinh câu trả lời AI | User | Chat UI, ChatService, RAG DB, Groq API | Hỏi -> Get Context -> Gọi LLM -> SSE Stream -> Hiển thị text. |
| ERD (Database Diagram)| Thể hiện cấu trúc CSDL | - | Bảng Users, Trips, Invoices, Vehicles, Drivers | Nêu rõ liên kết khóa ngoại (Trips trung tâm). |

---

## 9. ĐỀ XUẤT CHO BÁO CÁO QUẢN TRỊ DỰ ÁN

### Bảng WBS Đề Xuất (Work Breakdown Structure)
| Mã | Tên công việc | Mức độ | Trạng thái | Ước lượng (Giờ) | Phụ thuộc |
|---|---|---|---|---|---|
| 1.0 | Khảo sát và Phân tích thiết kế | Giai đoạn | Hoàn thành | 80 | |
| 2.0 | Thiết kế Database & UI/UX | Giai đoạn | Hoàn thành | 120 | 1.0 |
| 3.0 | Lập trình Backend (API, Laravel) | Giai đoạn | Hoàn thành | 200 | 2.0 |
| 3.1 | Module Xác thực & Phân quyền | Task | Hoàn thành | 24 | |
| 3.2 | Module Điều phối (Trips, Fleet) | Task | Hoàn thành | 64 | 3.1 |
| 3.3 | Module Kế toán (Invoice, Payroll)| Task | Hoàn thành | 56 | 3.2 |
| 3.4 | Module Chatbot RAG API | Task | Hoàn thành | 40 | 3.1 |
| 4.0 | Lập trình Frontend (React, AntD) | Giai đoạn | Hoàn thành | 240 | 2.0 |
| 4.1 | UI Layout & Auth | Task | Hoàn thành | 40 | |
| 4.2 | UI Dispatch Board & Trips | Task | Hoàn thành | 80 | 4.1 |
| 4.3 | UI Dashboard & Kế toán | Task | Hoàn thành | 80 | 4.1 |
| 4.4 | UI Tích hợp Chatbot Assistant | Task | Hoàn thành | 40 | 4.1 |
| 5.0 | Kiểm thử (Unit, E2E) | Giai đoạn | Hoàn thành | 80 | 3.0, 4.0 |
| 6.0 | Triển khai & Viết báo cáo | Giai đoạn | Đang làm | 40 | 5.0 |

### Bảng Man-Month
| Nhóm công việc | Số giờ | Man-Day | Man-Month |
|---|---|---|---|
| Khảo sát & Thiết kế | 200 | 25 | 1.14 |
| Lập trình Backend | 200 | 25 | 1.14 |
| Lập trình Frontend | 240 | 30 | 1.36 |
| Kiểm thử & Bàn giao | 120 | 15 | 0.68 |
| **Tổng cộng** | **760** | **95** | **4.32** |

### Quản trị Rủi ro
| Rủi ro | Nhóm rủi ro | Xác suất (1-5) | Ảnh hưởng (1-5) | Điểm | Chiến lược ứng phó |
|---|---|---|---|---|---|
| API Groq/Gemini bị sập/Rate Limit | Tích hợp | 3 | 4 | 12 | Có cơ chế Fallback trả lời bằng Data cứng cục bộ. |
| Thay đổi requirement phức tạp bảng lương | Phạm vi | 4 | 4 | 16 | Chốt cứng scope, làm bản MVP lương cơ bản trước. |
| Lỗi bảo mật token API | Bảo mật | 2 | 5 | 10 | Dùng Laravel Sanctum với Cookie (HTTPOnly), chống CSRF. |
| Trễ tiến độ Frontend | Tiến độ | 3 | 3 | 9 | Dùng sẵn thư viện UI Ant Design để tiết kiệm thời gian vẽ UI. |

### Quản trị Nguồn lực & Chi phí
- **Nguồn lực**: 1 Project Manager (kiêm BA), 1 Backend Dev, 1 Frontend Dev, 1 Tester.
- **Chi phí dự kiến**: 
  - Nhân sự (Nội bộ sinh viên thực tập): ~ 0 VNĐ. (Nếu quy đổi lương: ~ 60 triệu VNĐ)
  - Hosting/VPS: 3.000.000 VNĐ / năm.
  - Domain + SSL: 500.000 VNĐ.
  - API Groq / Gemini: Bản Free Tier (0 VNĐ).
  - Chi phí dự phòng: 2.000.000 VNĐ.

---

## 10. TESTCASE (Ví dụ cho báo cáo)
| ID | Chức năng | Dữ liệu đầu vào | Kết quả mong đợi | Trạng thái |
|---|---|---|---|---|
| TC_01 | Đăng nhập đúng | Email: `admin@...`, Pass: `123456` | Cấp Cookie, vào Dashboard | Pass |
| TC_02 | Đăng nhập sai | Email: `admin...`, Pass: `sai` | Báo lỗi "Tài khoản hoặc mật khẩu không đúng" | Pass |
| TC_03 | Chatbot RAG | Nhập: "Quy trình đóng gói là gì?" | AI trả lời theo nội dung đã huấn luyện trong Database | Pass |
| TC_04 | Tạo chuyến đi | Bỏ trống điểm nhận hàng | Frontend báo lỗi Validate Required | Pass |

---

## 11. DANH SÁCH ẢNH CẦN CHỤP ĐỂ ĐƯA VÀO BÁO CÁO (.docx)
1. Ảnh Giao diện đăng nhập.
2. Ảnh Dashboard thống kê tổng quan.
3. Ảnh Màn hình Dispatch Board (Bảng điều phối chuyến đi).
4. Ảnh Màn hình Thêm mới Chuyến đi (Create Trip).
5. Ảnh Màn hình Chatbot AI đang trả lời câu hỏi.
6. Ảnh Màn hình Danh sách Hóa đơn Kế toán.
7. Ảnh từ phần mềm MS Project (Gantt Chart, Network Diagram, Resource Usage).
8. Ảnh kết quả chạy lệnh test `npm run test:e2e` (Playwright) hoặc giao diện API Test.

---

## 12. DANH SÁCH FILE CẦN NỘP CUỐI KỲ
1. File Báo cáo `.docx` hoàn chỉnh.
2. Bảng dự toán và MS Project (`.mpp`).
3. Source code đóng gói (`ship-app` và `ship-app-api` nén `.zip`).
4. File Database export (`.sql` hoặc `.sqlite`).

---
*(End of Context File)*
