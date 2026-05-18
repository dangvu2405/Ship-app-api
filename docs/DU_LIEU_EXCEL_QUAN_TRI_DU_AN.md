# DỮ LIỆU EXCEL CHO BÁO CÁO QUẢN TRỊ DỰ ÁN
*(Lưu ý: Bạn có thể copy trực tiếp các bảng dưới đây và dán (Paste) vào Excel. Dữ liệu các bảng đã được thiết kế khớp nhau 100% về số giờ, nhân sự và chi phí).*

---

## SHEET 1: WBS (Work Breakdown Structure & MS Project Data)

| Mã WBS | Tên công việc | Cấp độ | Trạng thái | Người thực hiện | Ước lượng giờ | Duration ngày | Predecessors | Ghi chú |
|---|---|---|---|---|---|---|---|---|
| **1.0** | **Khảo sát & Phân tích thiết kế** | **1** | Hoàn thành | | **64** | **8** | | Khởi tạo dự án |
| 1.1 | Lấy yêu cầu và phân tích nghiệp vụ | 2 | Hoàn thành | PM/BA | 40 | 5 | | |
| 1.2 | Thiết kế Database & Architecture | 2 | Hoàn thành | DevOps/Architect | 24 | 3 | 1.1 | |
| **2.0** | **Lập trình Backend (API Laravel)** | **1** | Hoàn thành | | **200** | **25** | **1.2** | Code song song với FE |
| 2.1 | Xây dựng API Xác thực & Phân quyền | 2 | Hoàn thành | Backend Dev | 24 | 3 | 1.2 | Cấu hình Sanctum |
| 2.2 | Xây dựng API Điều phối (Trips, Fleet) | 2 | Hoàn thành | Backend Dev | 64 | 8 | 2.1 | Logic cốt lõi TMS |
| 2.3 | Xây dựng API Kế toán (Invoice, Payroll) | 2 | Hoàn thành | Backend Dev | 56 | 7 | 2.2 | |
| 2.4 | Xây dựng API Chatbot RAG | 2 | Hoàn thành | Backend Dev | 56 | 7 | 2.1 | Gọi Groq/Gemini |
| **3.0** | **Lập trình Frontend (React SPA)** | **1** | Hoàn thành | | **240** | **30** | **1.2** | |
| 3.1 | Phát triển UI Layout, Auth & Role | 2 | Hoàn thành | Frontend Dev | 40 | 5 | 1.2 | Layout Ant Design |
| 3.2 | Phát triển UI Dispatch Board & Trips | 2 | Hoàn thành | Frontend Dev | 80 | 10 | 3.1 | Màn hình phức tạp |
| 3.3 | Phát triển UI Dashboard & Kế toán | 2 | Hoàn thành | Frontend Dev | 80 | 10 | 3.2 | |
| 3.4 | Phát triển UI Chatbot Assistant | 2 | Hoàn thành | Frontend Dev | 40 | 5 | 3.1 | Stream SSE response |
| **4.0** | **Kiểm thử hệ thống (Testing)** | **1** | Hoàn thành | | **120** | **15** | **2.0, 3.0** | Đợi code xong từng phần |
| 4.1 | Kiểm thử Backend API | 2 | Hoàn thành | Tester | 64 | 8 | 2.0 | Test bằng Postman/Pest |
| 4.2 | Kiểm thử Frontend UI/UX | 2 | Hoàn thành | Tester | 56 | 7 | 3.0 | Test luồng giao diện |
| **5.0** | **Triển khai & Bàn giao** | **1** | Đang làm | | **56** | **7** | **4.0** | |
| 5.1 | Cấu hình Server và Deploy CI/CD | 2 | Hoàn thành | DevOps/Architect | 16 | 2 | 4.0 | Đưa lên VPS Cloud |
| 5.2 | Viết tài liệu báo cáo dự án | 2 | Đang làm | PM/BA | 40 | 5 | 5.1 | Đóng gói nộp bài |

---

## SHEET 2: MAN-MONTH

| STT | Nhóm công việc | Tổng giờ | Man-Day | Man-Month | Ghi chú |
|---|---|---|---|---|---|
| 1 | Khảo sát & Phân tích thiết kế | 64 | 8.00 | 0.36 | Giai đoạn 1.0 |
| 2 | Lập trình Backend (API Laravel) | 200 | 25.00 | 1.14 | Giai đoạn 2.0 |
| 3 | Lập trình Frontend (React SPA) | 240 | 30.00 | 1.36 | Giai đoạn 3.0 |
| 4 | Kiểm thử hệ thống (Testing) | 120 | 15.00 | 0.68 | Giai đoạn 4.0 |
| 5 | Triển khai & Bàn giao | 56 | 7.00 | 0.32 | Giai đoạn 5.0 |
| **Tổng** | **Toàn bộ dự án** | **680** | **85.00** | **3.86** | **Tính trên định mức 8h/ngày, 22 ngày/tháng** |

---

## SHEET 3: RESOURCE

| STT | Vai trò | Người thực hiện | Công việc phụ trách | Tổng giờ | Đơn giá/giờ (VNĐ) | Thành tiền (VNĐ) |
|---|---|---|---|---|---|---|
| 1 | PM/BA | Nhân sự 1 | Phân tích nghiệp vụ, Viết tài liệu báo cáo | 80 | 150,000 | 12,000,000 |
| 2 | Backend Dev | Nhân sự 2 | Code API Xác thực, Trips, Kế toán, AI Chatbot | 200 | 100,000 | 20,000,000 |
| 3 | Frontend Dev | Nhân sự 3 | Thiết kế và code UI bằng React/Ant Design | 240 | 100,000 | 24,000,000 |
| 4 | Tester | Nhân sự 4 | Chạy kịch bản test API và test giao diện E2E | 120 | 80,000 | 9,600,000 |
| 5 | DevOps/Architect | Nhân sự 5 | Thiết kế Database, Deploy Server CI/CD | 40 | 120,000 | 4,800,000 |
| **Tổng**| | | | **680** | | **70,400,000** |

---

## SHEET 4: COST

| STT | Hạng mục chi phí | Diễn giải | Thành tiền (VNĐ) | Ghi chú |
|---|---|---|---|---|
| **I** | **Chi phí nhân sự** | **Chi phí trả lương team phát triển (Theo Sheet Resource)** | **70,400,000** | Quy đổi khối lượng công việc |
| **II** | **Chi phí hạ tầng và công nghệ** | | **4,500,000** | |
| 1 | Chi phí Server/VPS | Thuê VPS Cloud lưu trữ Backend/Frontend (1 năm) | 3,000,000 | Ubuntu 22.04 |
| 2 | Tên miền (Domain) | Đăng ký tên miền cho ứng dụng (1 năm) | 500,000 | Domain .com hoặc .vn |
| 3 | Dịch vụ API SMTP Email | Gói gửi email hệ thống khôi phục mật khẩu | 1,000,000 | |
| 4 | Dịch vụ API Trí tuệ nhân tạo | Gọi API Groq (Llama-3) và Google Gemini cho Chatbot | 0 | Đang dùng gói Miễn phí (Free Tier) |
| **III** | **Chi phí công cụ/phần mềm** | | **0** | |
| 1 | MS Project / Jira | Công cụ quản trị dự án | 0 | License sinh viên / Trial |
| 2 | Github / IDE code | Lưu trữ mã nguồn và trình soạn thảo | 0 | Open Source / Education |
| **IV** | **Chi phí dự phòng** | **Dự phòng rủi ro 10% (Của I + II + III)** | **7,490,000** | Bù đắp chậm trễ tiến độ, chi phí phát sinh |
| **V** | **TỔNG CỘNG** | **Tổng mức đầu tư cho toàn bộ dự án** | **82,390,000** | |

---

## SHEET 5: RISK

| STT | Mô tả rủi ro | Nhóm rủi ro | Xác suất (1-5) | Ảnh hưởng (1-5) | Điểm | Mức độ | Chiến lược ứng phó | Hành động cụ thể |
|---|---|---|---|---|---|---|---|---|
| 1 | API LLM (Groq/Gemini) bị giới hạn Rate Limit hoặc mất kết nối. | Tích hợp API | 3 | 4 | 12 | Cao | Giảm thiểu (Mitigate) | Code cơ chế Fallback ở Backend. Nếu API AI sập, hệ thống tự động trả lời bằng chuỗi dữ liệu hướng dẫn tĩnh (Local Data). |
| 2 | Lộ lọt Session Token dẫn đến bị hack tài khoản. | Bảo mật | 2 | 5 | 10 | Cao | Né tránh (Avoid) | Không dùng LocalStorage. Triển khai Laravel Sanctum dùng cookie HTTPOnly và bật bảo vệ CSRF (Cross-Site Request Forgery). |
| 3 | Trễ tiến độ Frontend do màn hình Dispatch Board (Bảng điều phối) có UI kéo thả quá phức tạp. | Tiến độ | 4 | 3 | 12 | Cao | Giảm thiểu (Mitigate) | Sử dụng thư viện UI có sẵn (Ant Design, Tailwind) để vẽ Component thay vì tự code CSS thuần để tiết kiệm thời gian. |
| 4 | Tính toán sai lệch dữ liệu bảng lương (Payroll) gây phàn nàn cho tài xế. | Dữ liệu | 2 | 4 | 8 | Trung bình | Né tránh (Avoid) | Tester kiểm tra kỹ các góc (Edge case) của API tính lương. Áp dụng Strict Typing trong PHP 8.2 để tính tiền. |
| 5 | Lập trình viên Backend bị ốm nghỉ phép dài ngày làm chậm tiến độ API. | Nhân sự | 2 | 3 | 6 | Thấp | Chấp nhận (Accept) | Áp dụng Clean Code, có Daily Meeting để Frontend Dev và Tester luôn nắm được tiến độ và có thể hỗ trợ nếu cần. |
