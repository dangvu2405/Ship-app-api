# KẾ HOẠCH DỰ ÁN MICROSOFT PROJECT
*(CETA - Company Ship Transportation Management System)*

Dưới đây là bảng dữ liệu chuẩn hóa để bạn nhập trực tiếp vào phần mềm Microsoft Project. Dữ liệu đã được căn chỉnh logic, tuân thủ đúng ràng buộc các task, không bị Overallocated nhân sự, và tự động tạo ra Đường găng (Critical Path).

*Giả định dự án bắt đầu vào Thứ Hai, ngày **05/01/2026**. Chế độ làm việc 5 ngày/tuần, 8 giờ/ngày.*

## BẢNG DỮ LIỆU NHẬP MS PROJECT

| ID | Task Name | Duration | Start | Finish | Predecessors | Resource Names | Milestone | Notes |
|:---:|---|:---:|:---:|:---:|:---:|---|:---:|---|
| **1** | **I. KHẢO SÁT & PHÂN TÍCH THIẾT KẾ** | **8 days** | 05/01/26 | 14/01/26 | | | | Chứa các task thiết kế |
| 2 | Khảo sát yêu cầu nghiệp vụ | 2 days | 05/01/26 | 06/01/26 | | PM_BA | No | |
| 3 | Phân tích nghiệp vụ | 3 days | 07/01/26 | 09/01/26 | 2 | PM_BA | No | |
| 4 | **Hoàn thành khảo sát** | **0 days** | 09/01/26 | 09/01/26 | 3 | | **Yes** | Milestone |
| 5 | Thiết kế Database & Architecture | 3 days | 12/01/26 | 14/01/26 | 4 | DevOps | No | DB schema |
| 6 | Thiết kế UI/UX & Layout | 5 days | 12/01/26 | 16/01/26 | 4 | Frontend_Dev | No | Vẽ Figma/Mockup |
| 7 | **Hoàn thành phân tích thiết kế** | **0 days** | 16/01/26 | 16/01/26 | 5,6 | | **Yes** | Milestone |
| **8** | **II. LẬP TRÌNH BACKEND (API)** | **25 days** | 19/01/26 | 20/02/26 | | | | |
| 9 | API Xác thực & Phân quyền | 3 days | 19/01/26 | 21/01/26 | 7 | Backend_Dev | No | Laravel Sanctum |
| 10 | API Điều phối chuyến đi | 8 days | 22/01/26 | 02/02/26 | 9 | Backend_Dev | No | |
| 11 | API Kế toán & Lương | 7 days | 03/02/26 | 11/02/26 | 10 | Backend_Dev | No | |
| 12 | API Chatbot RAG | 7 days | 12/02/26 | 20/02/26 | 11 | Backend_Dev | No | Groq/Gemini AI |
| **13** | **III. LẬP TRÌNH FRONTEND (React)** | **25 days** | 26/01/26 | 27/02/26 | | | | Code UI |
| 14 | Tích hợp FE Điều phối chuyến | 10 days| 26/01/26 | 06/02/26 | 10SS+2d | Frontend_Dev | No | Start sau BE 2 ngày |
| 15 | Tích hợp FE Kế toán & Lương | 10 days| 09/02/26 | 20/02/26 | 14, 11SS+2d| Frontend_Dev | No | |
| 16 | Tích hợp FE Chatbot RAG | 5 days | 23/02/26 | 27/02/26 | 15, 12SS+2d| Frontend_Dev | No | Giao tiếp SSE |
| 17 | **Hoàn thành phát triển chức năng**| **0 days** | 27/02/26 | 27/02/26 | 12,16 | | **Yes** | Milestone |
| **18** | **IV. KIỂM THỬ HỆ THỐNG** | **15 days** | 23/02/26 | 13/03/26 | | | | |
| 19 | Kiểm thử Backend API | 8 days | 23/02/26 | 04/03/26 | 12 | Tester | No | Test bằng Pest |
| 20 | Kiểm thử Frontend UI/UX | 7 days | 05/03/26 | 13/03/26 | 16,19 | Tester | No | Test bằng Playwright |
| 21 | **Hoàn thành kiểm thử** | **0 days** | 13/03/26 | 13/03/26 | 20 | | **Yes** | Milestone |
| **22** | **V. TRIỂN KHAI & BÀN GIAO** | **7 days** | 16/03/26 | 24/03/26 | | | | |
| 23 | Cấu hình Server & Deploy CI/CD | 2 days | 16/03/26 | 17/03/26 | 21 | DevOps | No | Đưa lên VPS |
| 24 | Viết tài liệu báo cáo dự án | 5 days | 18/03/26 | 24/03/26 | 23 | PM_BA | No | Đóng gói nộp bài |
| 25 | **Bàn giao dự án** | **0 days** | 24/03/26 | 24/03/26 | 24 | | **Yes** | Milestone |

*(Ghi chú: Lịch trên đã trừ thứ 7 và Chủ Nhật. SS+2d nghĩa là Start-to-Start trễ 2 ngày).*

---

## HƯỚNG DẪN THAO TÁC TRÊN MICROSOFT PROJECT

### 1. Cách nhập vào MS Project
- Bật MS Project, chọn **Blank Project**.
- Vào menu **Project** > **Project Information** > Chọn Start Date là `05/01/2026`.
- Copy tên các task từ cột **Task Name** ở bảng trên dán vào cột Task Name của MS Project.
- Các task bôi đậm là task Summary (Nhóm). Bạn cần bôi đen các task con bên dưới, vào tab **Task** > bấm nút mũi tên **Indent Task** (mũi tên hướng sang phải) để gom chúng vào task cha.
- Nhập cột Duration (ví dụ: `2d` cho 2 days). Cột Start/Finish sẽ tự nhảy nếu bạn làm đúng.
- Nhập cột Predecessors đúng số ID trên phần mềm MS Project (lưu ý: số ID trên phần mềm của bạn có thể lệch 1-2 dòng nếu bạn bỏ qua dòng trắng, hãy gõ đúng logic dòng).

### 2. Cách gán Resource Names
- Chuyển sang khung nhìn **Resource Sheet** (Góc dưới cùng bên phải hoặc View > Resource Sheet).
- Tạo 5 tài nguyên loại `Work`: `PM_BA`, `Backend_Dev`, `Frontend_Dev`, `Tester`, `DevOps`.
- Cập nhật cột **Std. Rate** (Lương/Giờ) theo bảng giá ở file trước (ví dụ: 150,000 đ/hr).
- Trở lại **Gantt Chart**, tại cột Resource Names, tick chọn đúng người thực hiện cho mỗi dòng.

### 3. Cách tạo Milestone
- Với các task có chữ **Milestone (Yes)**, bạn gõ Duration = `0d`. MS Project sẽ tự động biến thanh Gantt thành hình **Viên kim cương màu đen** (♦).

### 4. Cách bật Critical Path (Đường găng)
- Vào tab **Format** (trên thanh Ribbon) > Tick chọn hộp **Critical Tasks**.
- Lúc này trên biểu đồ Gantt, các task nằm trên đường găng (nếu bị trễ sẽ làm trễ toàn dự án) sẽ chuyển sang **màu đỏ**. Các task màu xanh là task có thời gian dự trữ (Slack).

### 5. Cách Set Baseline (Lưu lại kế hoạch gốc)
- Sau khi nhập xong toàn bộ dữ liệu, vào tab **Project** > **Set Baseline** > Chọn **Set Baseline** > Bấm OK. MS Project sẽ lưu bản gốc này lại (sẽ không thấy thay đổi gì ngay lập tức).
- Sau đó, nếu bạn sửa Duration dài hơn thực tế, bạn có thể vào View > **Tracking Gantt** để xem sự chênh lệch (Variance) giữa đường Baseline (màu xám dưới) và đường Thực tế (Màu đỏ/xanh ở trên).

---

## DANH SÁCH ẢNH CẦN CHỤP TỪ MS PROJECT CHO BÁO CÁO (.docx)
Khi đưa vào báo cáo môn Quản trị Dự án, bạn bắt buộc phải chụp 5 bức ảnh sau từ phần mềm MS Project:

1. **Ảnh Gantt Chart Tổng quan**: Chụp toàn bộ danh sách Task và biểu đồ Gantt bên phải. (Minh chứng lập lịch).
2. **Ảnh Critical Path**: Chụp lúc biểu đồ hiển thị thanh màu đỏ. (Minh chứng tìm đường găng).
3. **Ảnh Resource Graph hoặc Resource Usage**: Vào View > Resource Graph. Chụp màn hình để chứng minh không có cột nào bị đỏ (Overallocated - tức là 1 người bị bắt làm 16h/ngày). Kế hoạch trên đã phân bố hoàn hảo nên sẽ đều màu xanh.
4. **Ảnh Project Statistics**: Vào Project > Project Information > Statistics. Chụp bảng thống kê tổng giờ và tổng chi phí. (Chi phí sẽ ra chính xác 70.4 triệu).
5. **Ảnh Tracking Gantt / Variance**: Đổi view sang Tracking Gantt, cố tình chỉnh Task 10 (API Điều phối) từ 8 ngày lên 10 ngày. MS Project sẽ hiển thị thanh tiến độ bị trượt lệch so với thanh màu xám (Baseline) -> Chụp ảnh lại để làm phần Tổng kết (Đánh giá tiến độ thực tế so với kế hoạch). Lặp lại bước này với FE để tạo độ trễ 5 ngày như báo cáo đã viết.
