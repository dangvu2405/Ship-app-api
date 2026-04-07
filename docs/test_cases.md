# BẢNG TEST CASE
## Hệ thống: Company Ship ERP
## Phiên bản: 1.0 | Ngày: 2026-03-29

---

## TC-01: Phân hệ Xác thực (Authentication)

| TC ID | Tên Test Case | Mô tả | Dữ liệu đầu vào (Input) | Kết quả mong đợi (Expected Output) | Kết quả thực tế (Actual Result) | Trạng thái |
|-------|--------------|-------|--------------------------|--------------------------------------|----------------------------------|------------|
| TC-01-001 | Đăng nhập thành công | Đăng nhập với tài khoản admin hợp lệ | `POST /api/auth/login`<br>`email: "admin@example.com"`<br>`password: "password123"` | HTTP 200<br>`{success: true, data: {user, token}}` | | ⬜ Chưa test |
| TC-01-002 | Đăng nhập sai mật khẩu | Nhập đúng email nhưng sai password | `POST /api/auth/login`<br>`email: "admin@example.com"`<br>`password: "wrongpass"` | HTTP 401<br>`{success: false, message: "Invalid credentials"}` | | ⬜ Chưa test |
| TC-01-003 | Đăng nhập tài khoản không tồn tại | Email không có trong hệ thống | `POST /api/auth/login`<br>`email: "notexist@x.com"`<br>`password: "abc123"` | HTTP 401<br>`{success: false, message: "Invalid credentials"}` | | ⬜ Chưa test |
| TC-01-004 | Đăng nhập tài khoản bị khóa | Tài khoản có status = `inactive` | `POST /api/auth/login`<br>`email: "locked@example.com"`<br>`password: "password123"` | HTTP 401<br>Không thể đăng nhập | | ⬜ Chưa test |
| TC-01-005 | Thiếu trường email | Không gửi field email | `POST /api/auth/login`<br>`password: "password123"` | HTTP 422<br>`{errors: {email: ["required"]}}` | | ⬜ Chưa test |
| TC-01-006 | Email không đúng định dạng | Gửi email sai format | `POST /api/auth/login`<br>`email: "notanemail"`<br>`password: "password123"` | HTTP 422<br>`{errors: {email: ["must be valid email"]}}` | | ⬜ Chưa test |
| TC-01-007 | Password quá ngắn | Password < 6 ký tự | `POST /api/auth/login`<br>`email: "admin@example.com"`<br>`password: "abc"` | HTTP 422<br>`{errors: {password: ["min:6"]}}` | | ⬜ Chưa test |
| TC-01-008 | Đăng xuất thành công | Gọi logout với token hợp lệ | `POST /api/auth/logout`<br>`Authorization: Bearer {valid_token}` | HTTP 200<br>`{success: true, message: "Logout successful"}` | | ⬜ Chưa test |
| TC-01-009 | Đăng xuất không có token | Gọi logout mà không có token | `POST /api/auth/logout`<br>Không có header Authorization | HTTP 401<br>`{message: "Unauthenticated"}` | | ⬜ Chưa test |
| TC-01-010 | Làm mới token | Gọi refresh với token hợp lệ | `POST /api/auth/refresh`<br>`Authorization: Bearer {valid_token}` | HTTP 200<br>`{data: {token: "new_token"}}` | | ⬜ Chưa test |
| TC-01-011 | Đăng ký tài khoản mới | Admin tạo tài khoản mới hợp lệ | `POST /api/auth/register`<br>`username: "newuser"`<br>`email: "new@example.com"`<br>`password: "pass123"`<br>`password_confirmation: "pass123"` | HTTP 201<br>`{data: {id, username, email}}` | | ⬜ Chưa test |
| TC-01-012 | Đăng ký email đã tồn tại | Dùng email đã có trong DB | `POST /api/auth/register`<br>`email: "admin@example.com"` (đã tồn tại) | HTTP 422<br>`{errors: {email: ["already taken"]}}` | | ⬜ Chưa test |
| TC-01-013 | Đăng ký không xác nhận password | `password != password_confirmation` | `POST /api/auth/register`<br>`password: "pass123"`<br>`password_confirmation: "diff456"` | HTTP 422<br>`{errors: {password: ["confirmation does not match"]}}` | | ⬜ Chưa test |
| TC-01-014 | Đăng ký không phải admin | User thường gọi endpoint register | `POST /api/auth/register`<br>`Authorization: Bearer {staff_token}` | HTTP 403<br>`{message: "Forbidden"}` | | ⬜ Chưa test |

---

## TC-02: Phân hệ Người dùng & Phân quyền

| TC ID | Tên Test Case | Mô tả | Dữ liệu đầu vào (Input) | Kết quả mong đợi (Expected Output) | Kết quả thực tế (Actual Result) | Trạng thái |
|-------|--------------|-------|--------------------------|--------------------------------------|----------------------------------|------------|
| TC-02-001 | Xem danh sách người dùng | Admin xem toàn bộ tài khoản | `GET /api/users`<br>`Authorization: Bearer {admin_token}` | HTTP 200<br>Danh sách users có pagination | | ⬜ Chưa test |
| TC-02-002 | Xem danh sách người dùng (không phải admin) | User thường xem danh sách users | `GET /api/users`<br>`Authorization: Bearer {staff_token}` | HTTP 403<br>Từ chối truy cập | | ⬜ Chưa test |
| TC-02-003 | Tạo vai trò mới | Admin tạo role mới | `POST /api/roles`<br>`{name: "hr_manager", description: "Quản lý HR"}` | HTTP 201<br>`{data: {id, name, description}}` | | ⬜ Chưa test |
| TC-02-004 | Gán quyền cho vai trò | Gán danh sách permissions vào role | `POST /api/roles/1/permissions`<br>`{permissions: [1,2,3,5]}` | HTTP 200<br>Role có 4 permissions được gán | | ⬜ Chưa test |
| TC-02-005 | Gán quyền - permission không tồn tại | Gán permission_id không có trong DB | `POST /api/roles/1/permissions`<br>`{permissions: [9999]}` | HTTP 422<br>`{errors: {permissions}}` | | ⬜ Chưa test |
| TC-02-006 | Xóa vai trò | Admin xóa role không còn dùng | `DELETE /api/roles/3`<br>`Authorization: Bearer {admin_token}` | HTTP 200<br>`{message: "Deleted successfully"}` | | ⬜ Chưa test |

---

## TC-03: Phân hệ Nhân sự (HR)

| TC ID | Tên Test Case | Mô tả | Dữ liệu đầu vào (Input) | Kết quả mong đợi (Expected Output) | Kết quả thực tế (Actual Result) | Trạng thái |
|-------|--------------|-------|--------------------------|--------------------------------------|----------------------------------|------------|
| TC-03-001 | Tạo nhân viên mới | Thêm nhân viên hợp lệ | `POST /api/employees`<br>`{code:"EMP001", name:"Nguyễn Văn A", email:"nva@co.com", department_id:1, position_id:1, office_id:1}` | HTTP 201<br>Employee được tạo | | ⬜ Chưa test |
| TC-03-002 | Tạo nhân viên - mã trùng | code đã tồn tại trong DB | `POST /api/employees`<br>`{code:"EMP001"}` (đã có) | HTTP 422<br>`{errors: {code: ["unique violation"]}}` | | ⬜ Chưa test |
| TC-03-003 | Tạo nhân viên - email trùng | Email đã tồn tại trong DB | `POST /api/employees`<br>`{email:"nva@co.com"}` (đã có) | HTTP 422<br>`{errors: {email: ["unique violation"]}}` | | ⬜ Chưa test |
| TC-03-004 | Cập nhật thông tin nhân viên | Sửa phòng ban của nhân viên | `PUT /api/employees/1`<br>`{department_id: 2}` | HTTP 200<br>Employee có department_id = 2 | | ⬜ Chưa test |
| TC-03-005 | Xóa nhân viên | Xóa mềm nhân viên | `DELETE /api/employees/5` | HTTP 200<br>deleted_at được set | | ⬜ Chưa test |
| TC-03-006 | Tạo tài xế - liên kết nhân viên | Tạo hồ sơ tài xế cho nhân viên | `POST /api/drivers`<br>`{employee_id:1, license_no:"B2-123456", license_class:"B2", expired_date:"2027-12-31"}` | HTTP 201<br>Driver được tạo | | ⬜ Chưa test |
| TC-03-007 | Tạo tài xế - nhân viên đã là tài xế | employee_id đã có trong drivers | `POST /api/drivers`<br>`{employee_id:1}` (đã có) | HTTP 422<br>`{errors: {employee_id: ["unique violation"]}}` | | ⬜ Chưa test |
| TC-03-008 | Lọc nhân viên theo phòng ban | Tìm nhân viên của phòng ban số 2 | `GET /api/employees?department_id=2` | HTTP 200<br>Chỉ trả về nhân viên trong department 2 | | ⬜ Chưa test |
| TC-03-009 | Tạo phụ cấp mới | Thêm loại phụ cấp | `POST /api/allowances`<br>`{code:"AL001", name:"Phụ cấp xăng xe", default_amount:500000, taxable:0}` | HTTP 201<br>Allowance được tạo | | ⬜ Chưa test |

---

## TC-04: Phân hệ Phương tiện & Điều phối

| TC ID | Tên Test Case | Mô tả | Dữ liệu đầu vào (Input) | Kết quả mong đợi (Expected Output) | Kết quả thực tế (Actual Result) | Trạng thái |
|-------|--------------|-------|--------------------------|--------------------------------------|----------------------------------|------------|
| TC-04-001 | Tạo phương tiện mới | Thêm xe vào hệ thống | `POST /api/vehicles`<br>`{office_id:1, plate_number:"51A-12345", type:"truck", brand:"Hyundai", model:"HD120", year:2022, capacity:5000}` | HTTP 201<br>Vehicle được tạo với status='available' | | ⬜ Chưa test |
| TC-04-002 | Tạo xe - biển số trùng | Biển số đã tồn tại | `POST /api/vehicles`<br>`{plate_number:"51A-12345"}` (đã có) | HTTP 422<br>`{errors: {plate_number: ["unique"]}}` | | ⬜ Chưa test |
| TC-04-003 | Gán xe cho tài xế | Phân công xe cụ thể cho tài xế | `POST /api/vehicle_assignments`<br>`{vehicle_id:1, driver_id:1, from_date:"2026-04-01", to_date:"2026-04-30"}` | HTTP 201<br>Assignment được tạo | | ⬜ Chưa test |
| TC-04-004 | Tạo chuyến đi mới | Điều phối tạo chuyến | `POST /api/trips`<br>`{code:"TRIP001", customer_id:1, driver_id:1, vehicle_id:1, start_point:"HCM", end_point:"HN", distance_km:1700, price:5000000}` | HTTP 201<br>Trip với status='pending' | | ⬜ Chưa test |
| TC-04-005 | Cập nhật trạng thái chuyến - bắt đầu | Chuyển trip sang in_progress | `PUT /api/trips/1`<br>`{status:"in_progress"}` | HTTP 200<br>Trip có status='in_progress' | | ⬜ Chưa test |
| TC-04-006 | Cập nhật trạng thái chuyến - hoàn thành | Chuyển trip sang completed | `PUT /api/trips/1`<br>`{status:"completed"}` | HTTP 200<br>Trip có status='completed' | | ⬜ Chưa test |
| TC-04-007 | Ghi chi phí xe | Ghi nhận chi phí nhiên liệu | `POST /api/vehicle_expenses`<br>`{vehicle_id:1, driver_id:1, type:"fuel", amount:800000, expense_date:"2026-04-05"}` | HTTP 201<br>Expense được ghi nhận | | ⬜ Chưa test |
| TC-04-008 | Tạo hóa đơn | Kế toán tạo hóa đơn từ chuyến đi | `POST /api/invoices`<br>`{code:"INV001", trip_id:1, customer_id:1, subtotal:5000000, vat_rate:10}` | HTTP 201<br>Invoice với vat_amount=500000, total=5500000 | | ⬜ Chưa test |
| TC-04-009 | Tạo hóa đơn - chuyến đi không tồn tại | trip_id không có trong DB | `POST /api/invoices`<br>`{trip_id:9999}` | HTTP 422<br>`{errors: {trip_id}}` | | ⬜ Chưa test |
| TC-04-010 | Xem danh sách chuyến đi có lọc | Lọc theo trạng thái 'completed' | `GET /api/trips?status=completed` | HTTP 200<br>Chỉ trả về chuyến đã hoàn thành | | ⬜ Chưa test |

---

## TC-05: Phân hệ Chấm công

| TC ID | Tên Test Case | Mô tả | Dữ liệu đầu vào (Input) | Kết quả mong đợi (Expected Output) | Kết quả thực tế (Actual Result) | Trạng thái |
|-------|--------------|-------|--------------------------|--------------------------------------|----------------------------------|------------|
| TC-05-001 | Ghi chấm công hợp lệ | HR ghi giờ vào ra của nhân viên | `POST /api/attendances`<br>`{employee_id:1, date:"2026-04-05", check_in:"08:00", check_out:"17:00", work_hours:8}` | HTTP 201<br>Attendance được ghi nhận | | ⬜ Chưa test |
| TC-05-002 | Ghi chấm công - nhân viên không tồn tại | employee_id không có trong DB | `POST /api/attendances`<br>`{employee_id:9999}` | HTTP 422<br>`{errors: {employee_id}}` | | ⬜ Chưa test |
| TC-05-003 | Ghi chấm công có giờ OT | Nhân viên làm thêm 2 giờ | `POST /api/attendances`<br>`{employee_id:1, date:"2026-04-05", check_in:"08:00", check_out:"19:00", work_hours:8, overtime_hours:2}` | HTTP 201<br>Attendance với overtime_hours=2 | | ⬜ Chưa test |
| TC-05-004 | Xem danh sách chấm công theo nhân viên | Lọc chấm công của employee_id=1 | `GET /api/attendances?employee_id=1` | HTTP 200<br>Chỉ records của nhân viên 1 | | ⬜ Chưa test |
| TC-05-005 | Xóa bản ghi chấm công | Xóa record chấm công | `DELETE /api/attendances/10` | HTTP 200<br>`{message: "Deleted successfully"}` | | ⬜ Chưa test |

---

## TC-06: Phân hệ Bảng lương (Payroll)

| TC ID | Tên Test Case | Mô tả | Dữ liệu đầu vào (Input) | Kết quả mong đợi (Expected Output) | Kết quả thực tế (Actual Result) | Trạng thái |
|-------|--------------|-------|--------------------------|--------------------------------------|----------------------------------|------------|
| TC-06-001 | Tạo bảng lương mới | Admin tạo bảng lương tháng 4/2026 | `POST /api/payrolls`<br>`{company_id:1, month:4, year:2026}` | HTTP 201<br>Payroll với status='draft' và payroll_details cho từng NV | | ⬜ Chưa test |
| TC-06-002 | Tạo bảng lương tháng đã tồn tại | Cùng công ty+tháng đã có bảng lương | `POST /api/payrolls`<br>`{company_id:1, month:4, year:2026}` (lần 2) | HTTP 422<br>`{message: "Payroll already exists"}` | | ⬜ Chưa test |
| TC-06-003 | Thiếu trường company_id | Không gửi company_id | `POST /api/payrolls`<br>`{month:4, year:2026}` | HTTP 422<br>`{errors: {company_id: ["required"]}}` | | ⬜ Chưa test |
| TC-06-004 | Xem danh sách bảng lương | Xem các bảng lương có filter | `GET /api/payrolls?year=2026&status=draft` | HTTP 200<br>Danh sách payrolls có pagination | | ⬜ Chưa test |
| TC-06-005 | Xem chi tiết bảng lương | Xem bảng lương ID=1 | `GET /api/payrolls/1` | HTTP 200<br>Payroll + đầy đủ payroll_details | | ⬜ Chưa test |
| TC-06-006 | Xem bảng lương không tồn tại | ID không có trong DB | `GET /api/payrolls/9999` | HTTP 404<br>`{message: "Payroll not found"}` | | ⬜ Chưa test |
| TC-06-007 | Duyệt bảng lương | Kế toán duyệt bảng lương đang draft | `POST /api/payrolls/1/approve` | HTTP 200<br>Payroll có status='approved', approved_at được set | | ⬜ Chưa test |
| TC-06-008 | Duyệt bảng lương đã khóa | Cố duyệt bảng lương đã locked | `POST /api/payrolls/2/approve`<br>(payroll_id=2 đã locked) | HTTP 422<br>`{message: "Cannot approve locked payroll"}` | | ⬜ Chưa test |
| TC-06-009 | Khóa bảng lương | Admin khóa bảng lương đã approved | `POST /api/payrolls/1/lock` | HTTP 200<br>Payroll có status='locked', locked_at được set | | ⬜ Chưa test |
| TC-06-010 | Cập nhật bảng lương đã khóa | Cố sửa bảng lương đã locked | `PUT /api/payrolls/2`<br>`{status:"draft"}` (đã locked) | HTTP 422<br>`{message: "Payroll is locked and cannot be updated"}` | | ⬜ Chưa test |
| TC-06-011 | Xóa bảng lương đã khóa | Cố xóa bảng lương đã locked | `DELETE /api/payrolls/2` (đã locked) | HTTP 422<br>`{message: "Payroll is locked and cannot be deleted"}` | | ⬜ Chưa test |
| TC-06-012 | Xuất bảng lương | Xuất dữ liệu lương tháng 4 | `GET /api/payrolls/1/export` | HTTP 200<br>`{data: {payroll, details: [{employee_code, net_salary,...}]}}` | | ⬜ Chưa test |
| TC-06-013 | Xem lương cá nhân (nhân viên) | Nhân viên xem lương của mình | `GET /api/payrolls/my-salary?month=4&year=2026`<br>`Authorization: Bearer {staff_token}` | HTTP 200<br>Payroll chỉ chứa detail của nhân viên hiện tại | | ⬜ Chưa test |
| TC-06-014 | Xem lương cá nhân - không có employee | User không liên kết với employee nào | `GET /api/payrolls/my-salary`<br>`Authorization: Bearer {user_no_employee_token}` | HTTP 200<br>`{data: null, message: "No employee linked"}` | | ⬜ Chưa test |
| TC-06-015 | Lọc bảng lương theo tháng | Xem payrolls của tháng 3 | `GET /api/payrolls?month=3&year=2026` | HTTP 200<br>Chỉ trả về payrolls của tháng 3/2026 | | ⬜ Chưa test |

---

## TC-07: Phân hệ Báo cáo

| TC ID | Tên Test Case | Mô tả | Dữ liệu đầu vào (Input) | Kết quả mong đợi (Expected Output) | Kết quả thực tế (Actual Result) | Trạng thái |
|-------|--------------|-------|--------------------------|--------------------------------------|----------------------------------|------------|
| TC-07-001 | Xem Dashboard tổng quan | Admin xem dashboard | `GET /api/reports/dashboard`<br>`Authorization: Bearer {admin_token}` | HTTP 200<br>Dữ liệu tổng quan hệ thống | | ⬜ Chưa test |
| TC-07-002 | Báo cáo tổng hợp lương | Xem tổng hợp lương | `GET /api/reports/payroll-summary?month=4&year=2026` | HTTP 200<br>Tổng hợp lương theo từng phòng ban | | ⬜ Chưa test |
| TC-07-003 | Báo cáo không có token | Truy cập báo cáo mà không đăng nhập | `GET /api/reports/dashboard`<br>(Không có Authorization) | HTTP 401<br>`{message: "Unauthenticated"}` | | ⬜ Chưa test |

---

## TC-08: Kiểm thử bảo mật & phân quyền

| TC ID | Tên Test Case | Mô tả | Dữ liệu đầu vào (Input) | Kết quả mong đợi (Expected Output) | Kết quả thực tế (Actual Result) | Trạng thái |
|-------|--------------|-------|--------------------------|--------------------------------------|----------------------------------|------------|
| TC-08-001 | Truy cập API không có token | Gọi API bảo vệ không có token | `GET /api/employees`<br>(Không có header) | HTTP 401<br>`{message: "Unauthenticated"}` | | ⬜ Chưa test |
| TC-08-002 | Token hết hạn / không hợp lệ | Dùng token giả hoặc đã xóa | `GET /api/employees`<br>`Authorization: Bearer invalid_token_here` | HTTP 401<br>`{message: "Unauthenticated"}` | | ⬜ Chưa test |
| TC-08-003 | Nhân viên thường truy cập chức năng Admin | User thường gọi API quản lý users | `GET /api/users`<br>`Authorization: Bearer {staff_token}` | HTTP 403<br>`{message: "Forbidden"}` | | ⬜ Chưa test |
| TC-08-004 | Nhân viên thường xem lương người khác | Cố gắng override my-salary | `GET /api/payrolls/my-salary`<br>`Authorization: Bearer {staff_token}` | HTTP 200<br>Chỉ trả về lương của nhân viên đang đăng nhập | | ⬜ Chưa test |
| TC-08-005 | API Health Check (public) | Endpoint công khai, không cần token | `GET /api/health` | HTTP 200<br>`{success: true, message: "API is running"}` | | ⬜ Chưa test |

---

## Ghi chú trạng thái

| Ký hiệu | Ý nghĩa |
|---------|---------|
| ⬜ Chưa test | Test case chưa được thực hiện |
| ✅ Passed | Test chạy thành công, kết quả đúng mong đợi |
| ❌ Failed | Test thất bại, kết quả không đúng mong đợi |
| ⏭️ Skip | Bỏ qua tạm thời |
| 🔄 Đang test | Đang trong quá trình thực hiện |

---

## Tổng kết

| Phân hệ | Tổng TC | Passed | Failed | Skip | Chưa test |
|---------|---------|--------|--------|------|-----------|
| TC-01: Xác thực | 14 | 0 | 0 | 0 | 14 |
| TC-02: Người dùng & Phân quyền | 6 | 0 | 0 | 0 | 6 |
| TC-03: Nhân sự (HR) | 9 | 0 | 0 | 0 | 9 |
| TC-04: Phương tiện & Điều phối | 10 | 0 | 0 | 0 | 10 |
| TC-05: Chấm công | 5 | 0 | 0 | 0 | 5 |
| TC-06: Bảng lương | 15 | 0 | 0 | 0 | 15 |
| TC-07: Báo cáo | 3 | 0 | 0 | 0 | 3 |
| TC-08: Bảo mật | 5 | 0 | 0 | 0 | 5 |
| **Tổng cộng** | **67** | **0** | **0** | **0** | **67** |
