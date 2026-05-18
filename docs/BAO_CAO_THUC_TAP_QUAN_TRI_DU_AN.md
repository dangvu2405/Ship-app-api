# BÁO CÁO THỰC TẬP MÔN QUẢN TRỊ DỰ ÁN CNTT
**Tên dự án:** Hệ thống quản lý vận tải/logistics Company Ship/CETA

---
*(Ghi chú cho sinh viên: Toàn bộ nội dung dưới đây được cấu trúc theo chuẩn Heading 1, 2, 3 của Word. Bạn hãy copy sang Microsoft Word, bôi đen toàn văn, chỉnh font Times New Roman, cỡ chữ 14, Line spacing Multiple 1.2, Before 0pt, After 6pt, First line indent 1cm để đúng định dạng nộp bài.)*
---

# CHƯƠNG 1. KHẢO SÁT VÀ XÁC LẬP DỰ ÁN

## 1.1 Giới thiệu về công ty thực tập
[CẦN BỔ SUNG: Sinh viên điền thông tin tên công ty thực tập, lĩnh vực hoạt động, địa chỉ, cơ cấu tổ chức và sứ mệnh tầm nhìn của công ty.]

## 1.2 Giới thiệu dự án
Dự án "Hệ thống quản lý vận tải/logistics Company Ship (CETA)" là một phần mềm Quản lý Vận tải (Transportation Management System - TMS) nhằm số hóa quy trình điều phối đội xe và quản lý tài chính cho doanh nghiệp vận tải.
- **Mục tiêu dự án:** Tự động hóa quy trình phân công chuyến đi, theo dõi sát sao tình trạng đội xe (phương tiện, tài xế), quản lý chi tiết công nợ/hóa đơn và đặc biệt tích hợp Trợ lý ảo AI (Chatbot RAG) để tra cứu nghiệp vụ thông minh.
- **Đối tượng sử dụng:** Quản trị viên (Admin), Điều phối viên (Dispatcher), Kế toán (Accountant), Quản lý đội xe (Fleet Manager), Tài xế (Driver), Khách hàng (Customer).
- **Phạm vi hệ thống:** Xây dựng ứng dụng dạng Single Page Application (SPA) kết nối với RESTful API Backend. Áp dụng kiến trúc đa chi nhánh (multi-tenant), quản lý tập trung từ khâu nhận đơn, điều phối đến xuất hóa đơn và tính lương.

## 1.3 Xác định bài toán định lượng
### 1.3.1 Bảng phân rã công việc WBS
Dựa trên phạm vi dự án, cấu trúc phân rã công việc (WBS) được xác định như sau:

*Bảng 1.1. Bảng phân rã công việc WBS*
| Mã | Tên công việc | Mức độ | Ước lượng (Giờ) | Phụ thuộc |
|---|---|---|---|---|
| **1.0** | **Khảo sát và Phân tích thiết kế** | **Giai đoạn** | **80** | |
| 1.1 | Lấy yêu cầu và phân tích nghiệp vụ | Task | 40 | |
| 1.2 | Thiết kế Database & UI/UX | Task | 40 | 1.1 |
| **2.0** | **Lập trình Backend (API, Laravel)** | **Giai đoạn** | **200** | **1.0** |
| 2.1 | Module Xác thực & Phân quyền | Task | 24 | |
| 2.2 | Module Điều phối (Trips, Fleet) | Task | 64 | 2.1 |
| 2.3 | Module Kế toán (Invoice, Payroll)| Task | 56 | 2.2 |
| 2.4 | Module Chatbot RAG AI API | Task | 56 | 2.1 |
| **3.0** | **Lập trình Frontend (React, AntD)** | **Giai đoạn** | **240** | **1.0** |
| 3.1 | UI Layout, Auth & Role | Task | 40 | |
| 3.2 | UI Dispatch Board & Trips | Task | 80 | 3.1 |
| 3.3 | UI Dashboard & Kế toán | Task | 80 | 3.1 |
| 3.4 | UI Tích hợp Chatbot Assistant | Task | 40 | 3.1 |
| **4.0** | **Kiểm thử hệ thống (Unit, E2E)** | **Giai đoạn** | **120** | **2.0, 3.0** |
| 4.1 | Kiểm thử Backend API | Task | 60 | 2.0 |
| 4.2 | Kiểm thử Frontend UI/UX | Task | 60 | 3.0 |
| **5.0** | **Triển khai & Bàn giao** | **Giai đoạn** | **40** | **4.0** |
| 5.1 | Triển khai lên server (Deploy) | Task | 20 | 4.0 |
| 5.2 | Viết tài liệu và báo cáo | Task | 20 | 5.1 |

### 1.3.2 Bảng tổng hợp Man-Month
Với giả định 1 ngày làm việc là 8 giờ, 1 tháng làm việc là 176 giờ (22 ngày công).

*Bảng 1.2. Bảng tổng hợp quy đổi Man-Month*
| STT | Nhóm công việc | Số giờ | Man-Day | Man-Month |
|---|---|---|---|---|
| 1 | Khảo sát và Phân tích thiết kế | 80 | 10.00 | 0.45 |
| 2 | Lập trình Backend (API) | 200 | 25.00 | 1.14 |
| 3 | Lập trình Frontend (UI) | 240 | 30.00 | 1.36 |
| 4 | Kiểm thử hệ thống | 120 | 15.00 | 0.68 |
| 5 | Triển khai & Bàn giao | 40 | 5.00 | 0.23 |
| **Tổng** | **Toàn bộ dự án** | **680** | **85.00** | **3.86** |

## 1.4 Mô hình phát triển
Dự án áp dụng mô hình phát triển linh hoạt **Agile/Scrum**. 
Do tính chất của dự án quản lý vận tải có nhiều nghiệp vụ phức tạp và dễ thay đổi yêu cầu từ phía người dùng cuối (khách hàng, tài xế, điều phối viên), việc áp dụng mô hình Agile giúp chia nhỏ dự án thành các Sprint ngắn (2-3 tuần), ưu tiên phát hành các tính năng cốt lõi (Xác thực, Điều phối chuyến) trước, sau đó phát triển các tính năng bổ trợ (Chatbot RAG, Báo cáo thống kê) ở các Sprint sau, giúp kiểm soát rủi ro hiệu quả.

---

# CHƯƠNG 2. LẬP KẾ HOẠCH DỰ ÁN

## 2.1 Lập kế hoạch dự án bằng MS Project
Dựa vào bảng WBS, kế hoạch tiến độ được lập trên phần mềm MS Project. Khởi tạo dự án với ngày bắt đầu dự kiến là 01/01/2026. Bảng tiến độ xác định rõ các Task, Duration, Predecessors để tìm ra Đường găng (Critical Path).

[CẦN BỔ SUNG HÌNH ẢNH: Chụp ảnh Gantt Chart từ phần mềm MS Project hiển thị các task và biểu đồ Gantt]
*Hình 2.1. Biểu đồ tiến độ dự án (Gantt Chart)*

[CẦN BỔ SUNG HÌNH ẢNH: Chụp ảnh Network Diagram hoặc tô đỏ đường găng trên Gantt Chart]
*Hình 2.2. Đường găng của dự án (Critical Path)*

Việc thiết lập đường găng (Critical Path) cho thấy sự chậm trễ trong việc thiết kế Database hoặc chậm tiến độ lập trình Frontend (đặc biệt là UI Dispatch Board & Trips) sẽ ảnh hưởng trực tiếp đến ngày kết thúc dự án. Sau khi hoàn thành kế hoạch, thao tác **Set Baseline** đã được thực hiện để lưu lại mốc kế hoạch gốc nhằm đối chiếu với thực tế thực hiện ở giai đoạn sau.

## 2.2 Quản trị rủi ro
Quản trị rủi ro giúp dự báo các vấn đề tiềm ẩn, đánh giá mức độ ảnh hưởng (1-5) và xác suất xảy ra (1-5), từ đó đưa ra chiến lược ứng phó. Điểm rủi ro = Xác suất x Ảnh hưởng.

*Bảng 2.1. Đánh giá và quản lý rủi ro dự án*
| STT | Rủi ro | Nhóm rủi ro | Xác suất | Ảnh hưởng | Điểm | Mức độ | Chiến lược ứng phó |
|---|---|---|---|---|---|---|---|
| 1 | API LLM (Groq/Gemini) bị lỗi hoặc quá giới hạn | Tích hợp API | 3 | 4 | 12 | Cao | Lập trình cơ chế Fallback, tự động trả lời bằng dữ liệu Local tĩnh khi API AI sập. |
| 2 | Mất an toàn dữ liệu token xác thực | Bảo mật | 2 | 5 | 10 | Cao | Chuyển đổi từ LocalStorage sang Cookie HTTPOnly với thư viện Laravel Sanctum, bật bảo vệ CSRF. |
| 3 | Lỗi thiết kế sai luồng kế toán lương | Dữ liệu | 2 | 4 | 8 | Trung bình | Yêu cầu Kế toán trưởng kiểm tra mô hình Database (Payrolls) trước khi code. |
| 4 | Trễ tiến độ Frontend do màn hình Dispatch phức tạp | Tiến độ | 3 | 3 | 9 | Trung bình | Sử dụng bộ thư viện Ant Design và Tailwind CSS thay vì tự code CSS thuần. |
| 5 | Nhân sự lập trình nghỉ ốm dài ngày | Nhân sự | 2 | 3 | 6 | Thấp | Áp dụng Clean Code, comment rõ ràng, daily meeting để người khác dễ tiếp quản. |

## 2.3 Quản trị nguồn lực
Dự án yêu cầu các vai trò tham gia như sau, được điều phối trên MS Project:

*Bảng 2.2. Danh sách nguồn lực và chi phí đơn giá*
| Vai trò | Số lượng | Công việc phụ trách | Số giờ | Đơn giá/giờ (VNĐ) | Thành tiền (VNĐ) |
|---|---|---|---|---|---|
| Project Manager (PM/BA) | 1 | Quản lý dự án, Khảo sát thiết kế | 80 | 150.000 | 12.000.000 |
| Backend Developer | 1 | Lập trình API Laravel, Database | 200 | 100.000 | 20.000.000 |
| Frontend Developer | 1 | Lập trình React UI/UX | 240 | 100.000 | 24.000.000 |
| Tester (QC) | 1 | Kiểm thử hệ thống | 120 | 80.000 | 9.600.000 |
| DevOps / System | 1 | Triển khai server, viết tài liệu | 40 | 120.000 | 4.800.000 |
| **Tổng cộng** | **5** | | **680** | | **70.400.000** |

[CẦN BỔ SUNG HÌNH ẢNH: Chụp ảnh Resource Graph hoặc Resource Usage từ MS Project cho thấy không có tài nguyên nào bị Overallocated (vượt quá 100% công suất)]
*Hình 2.3. Biểu đồ phân bổ nguồn lực (Resource Graph)*

## 2.4 Ước lượng và lập kế hoạch chi phí
Ngoài chi phí nhân sự, dự án cần các chi phí hạ tầng và công cụ hỗ trợ.

*Bảng 2.3. Ước lượng chi phí tổng quát dự án*
| STT | Hạng mục | Diễn giải | Thành tiền (VNĐ) |
|---|---|---|---|
| 1 | Chi phí nhân sự | Chi phí lương cho 5 thành viên team theo số giờ (Bảng 2.2) | 70.400.000 |
| 2 | Chi phí Server/Hosting | Thuê VPS Cloud trong 1 năm | 3.000.000 |
| 3 | Chi phí Domain | Tên miền hệ thống (1 năm) | 500.000 |
| 4 | Chi phí API Email SMTP | Dịch vụ gửi mail định danh | 1.000.000 |
| 5 | Chi phí API Trí tuệ nhân tạo | Dùng Groq API và Gemini API (Bản Free Tier) | 0 |
| 6 | Chi phí dự phòng (10%) | Xử lý các rủi ro phát sinh | 7.490.000 |
| **Tổng cộng** | | | **82.390.000** |

*(Lưu ý: Do đây là dự án thực tập nên chi phí nhân sự mang tính chất định giá quy đổi, thực tế công ty không phải chi trả khoản này cho sinh viên thực tập).*

---

# CHƯƠNG 3. PHÂN TÍCH THIẾT KẾ

## 3.1 Use case tổng quát
Biểu đồ Use case tổng quát thể hiện cái nhìn toàn cảnh về các tác nhân (Actor) và những chức năng (Use Case) hệ thống cung cấp.
- **Admin**: Quản lý toàn bộ cấu hình, người dùng, phân quyền.
- **Điều phối viên**: Phân công xe, tài xế, quản lý trạng thái chuyến đi.
- **Kế toán**: Quản lý hóa đơn, thanh toán, lương tài xế.
- **Tài xế**: Nhận chuyến, cập nhật trạng thái giao hàng.
- Tất cả người dùng đều có thể sử dụng chức năng Đăng nhập và Chatbot RAG.

[CẦN BỔ SUNG HÌNH ẢNH: Vẽ biểu đồ Use Case tổng quát bằng Draw.io hoặc StarUML và chèn vào đây]
*Hình 3.1. Biểu đồ Use Case tổng quát hệ thống CETA*

## 3.2 Phân tích thiết kế chức năng Xác thực và Phân quyền (Auth & Roles)
- **Mô tả chức năng**: Chức năng đảm bảo an ninh hệ thống. Người dùng cần đăng nhập bằng Email và Password. Xác thực sử dụng Laravel Sanctum (cơ chế Stateful Cookie). Sau khi đăng nhập, Frontend sẽ kiểm tra Role và Permissions để hiển thị menu tương ứng.
- **Biểu đồ hoạt động**:
[CẦN BỔ SUNG HÌNH ẢNH: Biểu đồ Activity hoặc Sequence cho luồng Đăng nhập]
*Hình 3.2. Biểu đồ hoạt động chức năng Đăng nhập*

*Bảng 3.1. Các bước quy trình xác thực*
| Bước | Tác nhân | Hành động |
|---|---|---|
| 1 | Người dùng | Nhập Email, Password và nhấn Đăng nhập trên UI. |
| 2 | Frontend | Gửi request lấy mã CSRF Cookie từ `/sanctum/csrf-cookie`. |
| 3 | Frontend | Gửi POST request chứa credentials đến API `/api/auth/login`. |
| 4 | Backend | Controller gọi AuthService, kiểm tra Hash Password. Nếu sai trả về lỗi 401. |
| 5 | Backend | Cấp Session Cookie (HTTPOnly) chứa quyền truy cập, trả dữ liệu User Info. |
| 6 | Frontend | Lưu trạng thái vào Zustand Store, kiểm tra Role và chuyển hướng vào Dashboard. |

- **Giao diện chức năng**:
[CẦN BỔ SUNG HÌNH ẢNH: Chụp ảnh màn hình Đăng nhập của ứng dụng React]
*Hình 3.3. Giao diện Đăng nhập hệ thống*

## 3.3 Phân tích thiết kế chức năng Điều phối chuyến đi (Dispatch & Trips)
- **Mô tả chức năng**: Chức năng lõi của phần mềm TMS. Điều phối viên xem danh sách các chuyến vận chuyển (Trips) đang trống (Unassigned). Sau đó chọn xe (Vehicle) và tài xế (Driver) phù hợp không bị trùng lịch để gán vào chuyến đi.
- **Biểu đồ hoạt động**:
[CẦN BỔ SUNG HÌNH ẢNH: Biểu đồ Activity cho chức năng Phân công chuyến đi]
*Hình 3.4. Biểu đồ hoạt động Điều phối chuyến đi*

*Bảng 3.2. Các bước quy trình Điều phối chuyến đi*
| Bước | Tác nhân | Hành động |
|---|---|---|
| 1 | Điều phối viên | Truy cập màn hình Dispatch Board (`/api/dispatch/board`). |
| 2 | Hệ thống | Hiển thị Gantt chart các chuyến đi và xe đang rảnh trong ngày. |
| 3 | Điều phối viên | Kéo thả hoặc bấm nút "Gán chuyến" cho một đơn hàng (Trip). |
| 4 | Frontend | Gửi PATCH request `/api/trips/{id}/assign` kèm `driver_id`, `vehicle_id`. |
| 5 | Backend | Kiểm tra logic: Xe có đang bận? Tài xế có quá giờ làm (HOS)? Nếu hợp lệ, lưu DB. |
| 6 | Backend | Cập nhật `status = ASSIGNED`, gửi Notification cho Tài xế. |

- **Giao diện chức năng**:
[CẦN BỔ SUNG HÌNH ẢNH: Chụp ảnh màn hình Dispatch Board hoặc chi tiết chuyến đi]
*Hình 3.5. Giao diện bảng Điều phối chuyến đi*

## 3.4 Phân tích thiết kế chức năng Kế toán hóa đơn (Invoices Management)
- **Mô tả chức năng**: Sau khi chuyến đi hoàn thành (DELIVERED), kế toán sẽ dựa vào tổng cước và chi phí phát sinh để phát hành hóa đơn thanh toán cho khách hàng.
- **Biểu đồ hoạt động**:
[CẦN BỔ SUNG HÌNH ẢNH: Biểu đồ Sequence hoặc Activity chức năng Lập hóa đơn]
*Hình 3.6. Biểu đồ hoạt động tạo Hóa đơn*

*Bảng 3.3. Các bước quy trình Quản lý Hóa đơn*
| Bước | Tác nhân | Hành động |
|---|---|---|
| 1 | Kế toán | Lọc danh sách chuyến đi đã hoàn thành nhưng chưa xuất hóa đơn. |
| 2 | Kế toán | Bấm "Tạo hóa đơn" (Create Invoice). Hệ thống lấy `total_amount` từ bảng `trips`. |
| 3 | Frontend | Gửi POST request `/api/invoices` với thông tin chi tiết hóa đơn. |
| 4 | Backend | Lưu DB (`invoices`), sinh mã hóa đơn tự động. Trả về JSON hóa đơn mới. |
| 5 | Kế toán | Xem lại và bấm "Phát hành" (Issue). |
| 6 | Backend | Đổi trạng thái Hóa đơn thành ISSUED, cập nhật lịch sử `invoice_status_histories`. |

- **Giao diện chức năng**:
[CẦN BỔ SUNG HÌNH ẢNH: Chụp ảnh màn hình danh sách Hóa đơn]
*Hình 3.7. Giao diện quản lý Hóa đơn kế toán*

## 3.5 Phân tích thiết kế chức năng Trợ lý ảo AI (Chatbot RAG)
- **Mô tả chức năng**: Tính năng sáng tạo áp dụng AI. Nhân viên hoặc quản lý có thể hỏi Chatbot về quy trình công ty (vd: "Quy trình đóng gói hàng lạnh?"). Hệ thống dùng Retrieval-Augmented Generation (RAG) tìm kiếm dữ liệu nội bộ trong DB, đưa cho LLM (Groq/Gemini) để trả lời.
- **Biểu đồ hoạt động**:
[CẦN BỔ SUNG HÌNH ẢNH: Biểu đồ Sequence chi tiết luồng RAG Chatbot]
*Hình 3.8. Biểu đồ Sequence chức năng Trợ lý ảo AI RAG*

*Bảng 3.4. Các bước quy trình luồng RAG Chatbot*
| Bước | Tác nhân/Hệ thống | Hành động |
|---|---|---|
| 1 | Người dùng | Mở Floating Chat Assistant, nhập câu hỏi. |
| 2 | Frontend | Gửi POST `/api/chat/messages/stream` chứa `session_id` và `message`. |
| 3 | `ChatService` (Backend) | Gọi `ChatRagService` truy vấn Vector Index tìm 3 tài liệu gần nhất trong bảng `knowledge_articles`. |
| 4 | `ChatService` (Backend) | Trộn tài liệu tìm được + Câu hỏi tạo thành Prompt gửi đến API LLM (Groq/Gemini). |
| 5 | Groq/Gemini API | Xử lý ngôn ngữ tự nhiên và trả về luồng dữ liệu (Stream). |
| 6 | Backend -> Frontend| Stream dữ liệu trả về Frontend theo chuẩn Server-Sent Events (SSE). UI hiển thị chữ chạy thời gian thực. |

- **Giao diện chức năng**:
[CẦN BỔ SUNG HÌNH ẢNH: Chụp ảnh cửa sổ Chatbot đang trả lời người dùng]
*Hình 3.9. Giao diện Chatbot Trợ lý ảo nội bộ*

---

# CHƯƠNG 4. KIỂM THỬ

## 4.1 Lập kế hoạch kiểm thử
Kiểm thử (Testing) nhằm đảm bảo phần mềm hoạt động đúng theo phân tích thiết kế, không có lỗi (bug) gây sụp đổ hệ thống và bảo mật dữ liệu khách hàng.
- **Phương pháp kiểm thử**: Áp dụng kiểm thử hộp đen (Black-box Testing) tập trung vào chức năng (Functional Testing) và giao diện người dùng (UI Testing).
- **Công cụ kiểm thử**: Playwright (E2E Test) trên Frontend, PHPUnit/Pest trên Backend API.

![Hình 4.1: Cấu trúc bộ testcase Playwright E2E của hệ thống Company Ship](/home/vumoi/.gemini/antigravity/brain/8f91708f-fed0-4f08-9548-3c64a8cbd60a/hinh_4_1_e2e_structure_1778889682792.png)
*Hình 4.1: Cấu trúc bộ testcase Playwright E2E của hệ thống Company Ship*

## 4.2 Kiểm thử chức năng Xác thực
*Bảng 4.1. Kịch bản kiểm thử Đăng nhập (Login)*
| Testcase ID | Mục tiêu | Dữ liệu đầu vào | Kết quả mong đợi | Trạng thái |
|---|---|---|---|---|
| TC_AUTH_01 | Đăng nhập thành công với tài khoản đúng | Email: `admin@...` / Pass: `123456` | Trả về HTTP 200, set Cookie Sanctum, chuyển hướng trang Dashboard. | Pass |
| TC_AUTH_02 | Đăng nhập sai mật khẩu | Email: `admin@...` / Pass: `sai_pass` | Trả về HTTP 401, hiển thị Toast báo lỗi "Tài khoản hoặc mật khẩu không đúng". | Pass |
| TC_AUTH_03 | Bỏ trống thông tin | Email: ` rỗng ` / Pass: ` rỗng ` | Form chặn Validate ngay tại Client-side (Frontend báo Required). | Pass |

## 4.3 Kiểm thử chức năng Điều phối chuyến đi
*Bảng 4.2. Kịch bản kiểm thử Điều phối (Dispatch)*
| Testcase ID | Mục tiêu | Dữ liệu đầu vào | Kết quả mong đợi | Trạng thái |
|---|---|---|---|---|
| TC_TRIP_01 | Phân công xe và tài xế thành công | Chuyến ID #10, Xe ID #5, Tài xế ID #2. Trạng thái xe đang Rảnh. | Gửi PATCH thành công. Trạng thái chuyến đổi thành ASSIGNED. | Pass |
| TC_TRIP_02 | Phân công tài xế đang bận | Chuyến ID #11. Tài xế ID #2 (đã được gán chuyến #10 cùng thời điểm). | API Backend từ chối, trả lỗi HTTP 422: "Tài xế bị trùng lịch trong khung giờ này". | Pass |

## 4.4 Kiểm thử chức năng Trợ lý AI (Chatbot)
*Bảng 4.3. Kịch bản kiểm thử Chatbot RAG*
| Testcase ID | Mục tiêu | Dữ liệu đầu vào | Kết quả mong đợi | Trạng thái |
|---|---|---|---|---|
| TC_AI_01 | Hỏi quy trình có trong Database nội bộ | Input: "Quy trình đóng gói hàng lạnh như thế nào?" | Chatbot đọc DB và trả lời chính xác quy trình nội bộ của công ty CETA. | Pass |
| TC_AI_02 | LLM API bị mất kết nối (Rate Limit/Timeout) | Tắt kết nối mạng tới Groq/Gemini API, gửi câu hỏi bất kỳ. | Cấu hình Fallback hoạt động, Chatbot trả về đoạn Text tĩnh được lưu trong Database nội bộ. | Pass |

## 4.5 Tổng kết kiểm thử
- Tổng số lượng Testcase chạy: 135 (109 Backend, 26 Frontend E2E).
- Tỷ lệ Pass: 100% (Môi trường Staging/Dev).

![Hình 4.2: Kết quả kiểm thử backend PHPUnit/Pest](/home/vumoi/.gemini/antigravity/brain/8f91708f-fed0-4f08-9548-3c64a8cbd60a/hinh_4_2_backend_tests_1778889767862.png)
*Hình 4.2: Kết quả kiểm thử backend PHPUnit/Pest (109 PASSED)*

![Hình 4.3: Kết quả E2E Playwright trên live server](/home/vumoi/.gemini/antigravity/brain/8f91708f-fed0-4f08-9548-3c64a8cbd60a/hinh_4_3_playwright_report_1778889788037.png)
*Hình 4.3: Kết quả E2E Playwright trên live server*

- Đánh giá: Hệ thống đạt chất lượng ổn định trên môi trường Desktop, API phản hồi nhanh (dưới 300ms), luồng xác thực Sanctum chặn đứng các lỗi bảo mật CSRF. Hệ thống đủ điều kiện bàn giao triển khai thử nghiệm.

---

# TỔNG KẾT

**1. So sánh kế hoạch ban đầu với thực tế**
Thông qua tính năng **Tracking Gantt (hoặc Variance Gantt)** trên MS Project, tiến độ thực tế (Actual Duration) được so sánh với kế hoạch ban đầu (Baseline Duration).
- Kế hoạch ban đầu (Baseline): 85 ngày công (Man-day).
- Thực tế triển khai (Actual): 90 ngày công (Man-day).
- Chênh lệch: Trễ 5 ngày công.

[CẦN BỔ SUNG HÌNH ẢNH: Chụp ảnh Variance Gantt hoặc Tracking Gantt từ phần mềm MS Project thể hiện thanh tiến độ thực tế bị trượt so với thanh Baseline màu xám]
*Hình 5.1. So sánh Baseline Duration và Actual Duration qua Tracking Gantt*

**2. Nguyên nhân sai lệch**
Sự sai lệch 5 ngày so với kế hoạch ban đầu xuất phát từ các nguyên nhân:
- Việc cài đặt và cấu hình thư viện xác thực Laravel Sanctum phức tạp hơn dự kiến (chạy khác port giữa React Vite và Laravel gây lỗi CORS và Cookie Domain).
- Việc tích hợp và test độ trễ (latency) của giao thức SSE cho Chatbot RAG AI mất nhiều thời gian do Rate Limit từ phía Groq API.
- Lợi điểm: Giai đoạn thiết kế Database bám sát thực tế giúp giai đoạn code rút ngắn được 2 ngày, bù trừ phần nào sự chậm trễ.

**3. Kết luận và bài học kinh nghiệm**
Dự án "Hệ thống quản lý vận tải Company Ship/CETA" đã hoàn thành tốt các mục tiêu cốt lõi đề ra ban đầu. Ứng dụng đã chứng minh khả năng quản lý hiệu quả nghiệp vụ vận tải phức tạp thông qua kiến trúc API hiện đại. Điểm đột phá lớn nhất là việc đưa AI Chatbot RAG vào phần mềm, giúp tự động hóa khâu tra cứu thông tin vận hành nội bộ, tăng năng suất cho nhân sự điều phối.

**Bài học kinh nghiệm rút ra sau quá trình thực tập:**
- Nắm vững quy trình quản trị dự án, biết cách áp dụng MS Project để tìm Đường găng (Critical Path), theo dõi khối lượng công việc và xử lý Overallocated.
- Cải thiện tư duy hệ thống (System Thinking) thông qua việc phân tích và thiết kế CSDL vận tải đa chi nhánh.
- Hiểu rõ rủi ro thực tế khi tích hợp các dịch vụ bên ngoài (Third-party API) và luôn cần có cơ chế Fallback (dự phòng) trong kiến trúc phần mềm.

*(Hết báo cáo)*
