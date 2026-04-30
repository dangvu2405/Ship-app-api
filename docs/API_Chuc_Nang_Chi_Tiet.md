# API Chức Năng Chi Tiết (Backend Hiện Tại)

Tài liệu này tổng hợp chi tiết các API hiện có trong `routes/api.php`, theo nhóm nghiệp vụ để BA/QA/PO/FE dễ tra cứu.

## 1) Quy ước chung

### 1.1 Envelope response

- Đa số API trả về dạng:
  - `success`: `true/false`
  - `message`: thông điệp
  - `data`: dữ liệu nghiệp vụ

### 1.2 Middleware và phạm vi truy cập

| Nhóm route | Middleware | Đối tượng truy cập |
| :-- | :-- | :-- |
| Public | (không yêu cầu auth) | Mọi client |
| Authenticated | `auth:sanctum`, `tenant.context`, `track.actions` | User đã đăng nhập |
| Admin | `auth:sanctum`, `tenant.context`, `track.actions`, `role:admin` | User role admin |

Lưu ý:
- Một số hành động workforce mutate cần thêm permission `schedule.approve`.
- Tất cả endpoint dưới đây dùng prefix `/api`.

### 1.3 Quy ước REST cho `apiResource`

Với các module dùng `Route::apiResource`, bộ endpoint mặc định gồm:
- `GET /resource` (index)
- `POST /resource` (store)
- `GET /resource/{id}` (show)
- `PUT/PATCH /resource/{id}` (update)
- `DELETE /resource/{id}` (destroy)

## 2) Public APIs

### 2.1 System

| Method | Endpoint | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- |
| GET | `/api/` | closure | Kiểm tra thông tin API |
| GET | `/api/health` | closure | Health check nhanh |

### 2.2 Auth public

| Method | Endpoint | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- |
| POST | `/api/auth/login` | `AuthController@login` | Đăng nhập tài khoản |
| POST | `/api/auth/social/login` | `AuthController@socialLogin` | Đăng nhập social provider |
| POST | `/api/auth/refresh-token` | `AuthController@refreshByToken` | Làm mới token bằng refresh token |
| POST | `/api/auth/forgot-password` | `AuthController@forgotPassword` | Gửi OTP/yêu cầu reset |
| POST | `/api/auth/check-otp` | `AuthController@checkOtp` | Xác minh OTP |
| POST | `/api/auth/reset-password` | `AuthController@resetPassword` | Đặt lại mật khẩu |

Ghi chú:
- Các endpoint này đang được gắn throttle khác nhau để hạn chế abuse.

## 3) Authenticated APIs (user đã đăng nhập)

## 3.1 Auth session và thông tin tài khoản

| Method | Endpoint | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- |
| POST | `/api/auth/logout` | `AuthController@logout` | Đăng xuất session hiện tại |
| POST | `/api/auth/refresh` | `AuthController@refresh` | Refresh access token |
| GET | `/api/auth/logs` | `AuthController@logs` | Lấy nhật ký auth |
| GET | `/api/auth/actions` | `AuthController@actions` | Lấy lịch sử hành động bảo mật |
| GET | `/api/auth/sessions` | `AuthController@sessions` | Danh sách session đang tồn tại |
| GET | `/api/auth/sessions/summary` | `AuthController@sessionsSummary` | Tổng quan session |
| POST | `/api/auth/sessions/{sessionId}/revoke` | `AuthController@revokeSession` | Thu hồi session cụ thể |
| POST | `/api/auth/sessions/{sessionId}/lock-account` | `AuthController@lockAccountForSession` | Khóa tài khoản liên quan session |
| GET | `/api/auth/me` | closure | Trả user hiện tại + tenants |
| GET | `/api/user` | closure | Alias của `/api/auth/me` |

## 3.2 Tiện ích user

| Method | Endpoint | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- |
| POST | `/api/upload` | `UploadController@store` | Upload file đính kèm |
| GET | `/api/payrolls/my-salary` | `PayrollController@mySalary` | Lấy thông tin lương của user đang đăng nhập |

## 3.3 Chat

| Method | Endpoint | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- |
| GET | `/api/chat/sessions` | `ChatController@sessions` | Lấy danh sách phiên chat |
| DELETE | `/api/chat/sessions/{sessionId}` | `ChatController@destroySession` | Xóa một phiên chat |
| GET | `/api/chat/messages` | `ChatController@index` | Lấy danh sách tin nhắn |
| POST | `/api/chat/messages` | `ChatController@store` | Gửi tin nhắn thường |
| POST | `/api/chat/messages/stream` | `ChatController@stream` | Gửi tin nhắn stream |

## 3.4 Notifications

| Method | Endpoint | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- |
| GET | `/api/notifications/unread-count` | `NotificationController@unreadCount` | Đếm thông báo chưa đọc |
| POST | `/api/notifications/read-all` | `NotificationController@markAllRead` | Đánh dấu đọc tất cả |
| POST | `/api/notifications/{id}/read` | `NotificationController@markRead` | Đánh dấu đọc 1 thông báo |
| GET | `/api/notifications` | `NotificationController@index` | Lấy danh sách thông báo |

## 3.5 Workforce

| Method | Endpoint | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- |
| GET | `/api/workforce/driver-schedules` | `WorkforceController@schedules` | Xem lịch tài xế |
| GET | `/api/workforce/leave-requests` | `WorkforceController@leaveRequests` | Xem danh sách đơn nghỉ |
| GET | `/api/workforce/absences` | `WorkforceController@absences` | Xem danh sách vắng mặt |
| PUT | `/api/workforce/driver-schedules/{id}/approve` | `WorkforceController@approveSchedule` | Duyệt lịch (cần `schedule.approve`) |
| PUT | `/api/workforce/driver-schedules/{id}/lock` | `WorkforceController@lockSchedule` | Khóa lịch (cần `schedule.approve`) |

## 3.6 Public holidays

| Method | Endpoint | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- |
| GET | `/api/public-holidays` | `PublicHolidayController@index` | Lấy ngày nghỉ lễ |

## 4) Admin APIs

## 4.1 Auth quản trị

| Method | Endpoint | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- |
| POST | `/api/auth/register` | `AuthController@register` | Tạo tài khoản mới (admin) |

## 4.2 Master Data và CRUD chính

### 4.2.1 Danh sách module dùng `apiResource`

| Resource | Endpoint base | Ghi chú |
| :-- | :-- | :-- |
| Companies | `/api/companies` | CRUD công ty |
| Work schedule templates | `/api/work-schedule-templates` | Bỏ qua `create`, `edit` |
| Offices | `/api/offices` | CRUD văn phòng |
| Departments | `/api/departments` | CRUD phòng ban |
| Positions | `/api/positions` | CRUD vị trí |
| Drivers | `/api/drivers` | CRUD tài xế |
| Vehicles | `/api/vehicles` | CRUD phương tiện |
| Vehicle assignments | `/api/vehicle_assignments` | CRUD gán xe |
| Vehicle expenses | `/api/vehicle_expenses` | CRUD chi phí xe |
| Customers | `/api/customers` | CRUD khách hàng |
| Trips | `/api/trips` | CRUD chuyến xe |
| Trip bonus rules | `/api/trip_bonus_rules` | CRUD quy tắc thưởng |
| Invoices | `/api/invoices` | CRUD hóa đơn |
| Payrolls | `/api/payrolls` | CRUD kỳ lương |
| Driver schedules | `/api/driver-schedules` | CRUD lịch tài xế |
| Users | `/api/users` | CRUD user |
| Roles | `/api/roles` | CRUD role |

### 4.2.2 Endpoint bổ sung

| Method | Endpoint | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- |
| POST | `/api/offices/{office}/apply-schedule` | `OfficeApplyScheduleController@store` | Áp template lịch cho office |

### 4.2.3 Chi tiết nghiệp vụ trọng tâm: Drivers, Vehicles, Vehicle assignments

Phần này mô tả chi tiết hơn 3 module vận hành lõi để BA/QA/FE có thể bám theo khi viết tài liệu test case và UAT.

#### A) Drivers (`/api/drivers`) - Quản lý hồ sơ tài xế

**Mục tiêu nghiệp vụ**
- Quản lý đầy đủ hồ sơ tài xế phục vụ phân công chuyến, tính lương và theo dõi nhân sự.
- Tài xế là thực thể trung tâm liên kết với office/department/position và phát sinh dữ liệu ở trips, attendance, payroll.

**CRUD API (apiResource)**
- `GET /api/drivers`: Danh sách tài xế (lọc/tìm kiếm theo implementation hiện tại của controller).
- `POST /api/drivers`: Tạo mới tài xế.
- `GET /api/drivers/{id}`: Xem chi tiết tài xế.
- `PUT/PATCH /api/drivers/{id}`: Cập nhật hồ sơ tài xế.
- `DELETE /api/drivers/{id}`: Xóa tài xế (thực tế có thể là soft delete tùy model/controller).

**Dữ liệu nghiệp vụ cốt lõi cần quan tâm**
- Định danh: mã tài xế, họ tên, thông tin liên hệ.
- Tổ chức: `office_id`, `department_id`, `position_id`, `company_id` (nếu có trong model/logic).
- Trạng thái: active/inactive hoặc trạng thái nghiệp vụ tương đương.

**Quy tắc nghiệp vụ khuyến nghị khi vận hành**
- Không cho phân công chuyến nếu tài xế không ở trạng thái hoạt động.
- Không cho phép xóa tài xế trong nghiệp vụ vận hành.
- Tài xế nghỉ việc phải chuyển trạng thái (inactive/nghỉ việc) thay vì xóa bản ghi.
- Khi tài xế nghỉ việc, xe đang phụ trách phải được trả về trạng thái trống/chưa gán.
- Khi đổi office/department giữa kỳ, cần kiểm tra ảnh hưởng báo cáo và payroll.

**Checklist test nhanh cho QA**
- Tạo tài xế hợp lệ -> trả thành công và có dữ liệu liên kết tổ chức đúng.
- Tạo/cập nhật với tham chiếu không tồn tại (`office_id`, `department_id`, `position_id`) -> trả lỗi validate.
- Thao tác xóa tài xế -> bị chặn theo policy nghiệp vụ.
- Chuyển tài xế sang nghỉ việc -> xe phụ trách không còn gắn tài xế.

#### B) Vehicles (`/api/vehicles`) - Quản lý phương tiện

**Mục tiêu nghiệp vụ**
- Quản lý danh mục phương tiện để phục vụ phân công chuyến, quản lý chi phí, theo dõi vận hành.
- Là thực thể đầu vào cho modules trips, vehicle_expenses và vehicle_assignments.

**CRUD API (apiResource)**
- `GET /api/vehicles`: Danh sách phương tiện.
- `POST /api/vehicles`: Tạo phương tiện mới.
- `GET /api/vehicles/{id}`: Chi tiết phương tiện.
- `PUT/PATCH /api/vehicles/{id}`: Cập nhật thông tin phương tiện.
- `DELETE /api/vehicles/{id}`: Xóa phương tiện (thường nên theo hướng an toàn dữ liệu lịch sử).

**Dữ liệu nghiệp vụ cốt lõi cần quan tâm**
- Định danh xe: biển số, mã xe (nếu có), loại xe.
- Tổ chức: `office_id`, `company_id`.
- Trạng thái vận hành: sẵn sàng, bảo trì, ngừng hoạt động (tùy cấu trúc hiện tại).

**Quy tắc nghiệp vụ khuyến nghị khi vận hành**
- Không gán chuyến mới cho xe đang bảo trì/ngừng hoạt động.
- Không cho phép xóa xe trong nghiệp vụ vận hành.
- Xe chỉ được đổi trạng thái theo vòng đời vận hành: đang vận hành, hỏng, bảo trì, ngừng hoạt động (tùy cấu hình hệ thống).
- Cập nhật trạng thái xe cần đồng bộ với màn hình điều phối để tránh gán nhầm.

**Checklist test nhanh cho QA**
- Tạo xe với dữ liệu hợp lệ -> thành công.
- Tạo xe trùng định danh quan trọng (ví dụ biển số nếu có unique rule) -> trả lỗi đúng.
- Thao tác xóa xe -> bị chặn theo policy nghiệp vụ.
- Chuyển trạng thái xe (hỏng/bảo trì/đang vận hành) -> điều phối phản ánh đúng và chỉ cho phép thao tác hợp lệ.

#### C) Vehicle assignments (`/api/vehicle_assignments`) - Gán xe cho tài xế

**Mục tiêu nghiệp vụ**
- Quản lý quan hệ gán xe - tài xế theo thời điểm/khoảng thời gian.
- Là lớp dữ liệu giúp điều phối biết tài xế nào đang dùng xe nào, phục vụ vận hành và đối soát.

**CRUD API (apiResource)**
- `GET /api/vehicle_assignments`: Danh sách bản ghi gán xe.
- `POST /api/vehicle_assignments`: Tạo bản ghi gán xe mới.
- `GET /api/vehicle_assignments/{id}`: Chi tiết một bản ghi gán xe.
- `PUT/PATCH /api/vehicle_assignments/{id}`: Cập nhật bản ghi gán xe.
- `DELETE /api/vehicle_assignments/{id}`: Hủy/xóa bản ghi gán xe.

**Dữ liệu nghiệp vụ cốt lõi cần quan tâm**
- Tham chiếu chính: `vehicle_id`, `driver_id`.
- Mốc thời gian hiệu lực gán (nếu hệ thống lưu theo ngày/ca/khoảng thời gian).
- Trạng thái gán: đang hiệu lực/hết hiệu lực (nếu có).

**Quy tắc nghiệp vụ khuyến nghị khi vận hành**
- Không cho 1 xe gán chồng chéo cho nhiều tài xế trong cùng khoảng thời gian.
- Không cho 1 tài xế nhận nhiều xe trong cùng khoảng thời gian nếu policy không cho phép.
- Khi cập nhật assignment, bắt buộc re-check toàn bộ điều kiện trùng/xung đột.

**Checklist test nhanh cho QA**
- Tạo assignment hợp lệ -> thành công.
- Tạo assignment trùng thời gian với cùng xe -> trả lỗi xung đột đúng.
- Cập nhật assignment gây trùng -> bị chặn theo rule.
- Xóa assignment đang được tham chiếu bởi nghiệp vụ khác -> xử lý đúng policy.

**Gợi ý UAT cho 3 module kết hợp**
- Luồng chuẩn: Tạo tài xế -> tạo xe -> gán xe -> tạo chuyến và chọn đúng cặp driver-vehicle.
- Luồng lỗi: Đưa tài xế về inactive hoặc xe về maintenance rồi thử gán/chạy chuyến -> hệ thống phản hồi đúng kỳ vọng nghiệp vụ.

## 4.3 Trips - lifecycle operations

| Method | Endpoint | Route name | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- | :-- |
| POST | `/api/trips/{id}/assign` | `trips.assign` | `TripController@assign` | Gán tài xế/xe cho chuyến |
| POST | `/api/trips/{id}/start` | `trips.start` | `TripController@start` | Bắt đầu chuyến |
| POST | `/api/trips/{id}/pickup` | `trips.pickup` | `TripController@pickup` | Xác nhận đón hàng |
| POST | `/api/trips/{id}/transit` | `trips.transit` | `TripController@transit` | Cập nhật đang vận chuyển |
| POST | `/api/trips/{id}/arrive` | `trips.arrive` | `TripController@arrive` | Xác nhận đến nơi |
| POST | `/api/trips/{id}/complete` | `trips.complete` | `TripController@complete` | Hoàn thành chuyến |
| POST | `/api/trips/{id}/cancel` | `trips.cancel` | `TripController@cancel` | Hủy chuyến |
| POST | `/api/trips/{id}/delay` | `trips.delay` | `TripController@delay` | Trì hoãn chuyến |
| POST | `/api/trips/{id}/resume` | `trips.resume` | `TripController@resume` | Tiếp tục sau trì hoãn |

### 4.3.1 Flow nghiệp vụ của bảng `trips`

**Mục tiêu:** mô tả đầy đủ vòng đời dữ liệu của một bản ghi trip để BA/QA/Dev dùng chung một chuẩn.

**Trạng thái chuẩn**
- `planned`: chuyến mới tạo, chưa gán tài nguyên.
- `assigned`: đã gán xe + tài xế.
- `started`: bắt đầu chạy chuyến.
- `pickup`: đã lấy hàng.
- `transit`: đang vận chuyển.
- `arrived`: đã đến điểm giao.
- `completed`: hoàn tất chuyến.
- `cancelled`: chuyến bị hủy.
- `delayed`: chuyến bị tạm hoãn.

**Flow chính**
`planned -> assigned -> started -> pickup -> transit -> arrived -> completed`

**Flow ngoại lệ**
- `planned/assigned/started/pickup/transit/arrived -> cancelled`
- `assigned/started/pickup/transit/arrived -> delayed -> resumed (về trạng thái trước khi delay theo logic controller)`

**Ma trận chuyển trạng thái (gợi ý kiểm soát)**

| Từ trạng thái | Sang trạng thái | Endpoint | Điều kiện chặn thường gặp |
| :-- | :-- | :-- | :-- |
| `planned` | `assigned` | `POST /api/trips/{id}/assign` | Thiếu xe/tài xế hợp lệ, xung đột assignment |
| `assigned` | `started` | `POST /api/trips/{id}/start` | Driver chưa check-in, xe chưa sẵn sàng |
| `started` | `pickup` | `POST /api/trips/{id}/pickup` | Thiếu xác nhận lấy hàng/chứng từ bắt buộc |
| `pickup` | `transit` | `POST /api/trips/{id}/transit` | Sai thứ tự thao tác nghiệp vụ |
| `transit` | `arrived` | `POST /api/trips/{id}/arrive` | Chưa đủ điều kiện xác nhận đến điểm giao |
| `arrived` | `completed` | `POST /api/trips/{id}/complete` | Thiếu POD/chữ ký nếu policy yêu cầu |
| `*` (trừ `completed`) | `cancelled` | `POST /api/trips/{id}/cancel` | Không cho hủy nếu đã khóa theo policy tài chính |
| `assigned/started/pickup/transit/arrived` | `delayed` | `POST /api/trips/{id}/delay` | Thiếu lý do trì hoãn |
| `delayed` | `resumed` | `POST /api/trips/{id}/resume` | Chưa đủ điều kiện mở lại chuyến |

**Rule dữ liệu quan trọng cho bảng `trips`**
- Không cho xóa cứng trip; dùng state transition (`cancelled/completed`) theo chính sách xóa an toàn.
- Trip đã `completed` hoặc đã phát sinh hóa đơn/lương thì thao tác xóa phải trả `OPERATION_NOT_ALLOWED` hoặc `DEPENDENCY_RESTRICTION`.
- Nếu sau này áp dụng Phase 1-3 đầy đủ, `TripController@store` cần ràng buộc tạo trip từ request/quotation đã `approved`.

**Checklist QA nhanh cho flow trip**
- Đi đúng flow chính từ `planned` đến `completed` phải thành công từng bước.
- Gọi endpoint sai thứ tự trạng thái phải bị chặn và trả lỗi rõ ràng.
- Delay/resume nhiều lần phải không làm sai trạng thái cuối.
- Trip đã `completed` thử `DELETE` phải bị chặn theo delete policy.

## 4.4 Invoices - lifecycle operations

| Method | Endpoint | Route name | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- | :-- |
| POST | `/api/invoices/{id}/issue` | `invoices.issue` | `InvoiceController@issue` | Phát hành hóa đơn |
| POST | `/api/invoices/{id}/mark-paid` | `invoices.mark-paid` | `InvoiceController@markPaid` | Đánh dấu đã thu tiền |
| POST | `/api/invoices/{id}/send-cqt` | `invoices.send-cqt` | `InvoiceController@sendCqt` | Gửi hóa đơn đến CQT |
| POST | `/api/invoices/{id}/cancel` | `invoices.cancel` | `InvoiceController@cancel` | Hủy hóa đơn |

## 4.5 Payroll adjustments

| Method | Endpoint | Route name | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- | :-- |
| GET | `/api/payroll-adjustments` | `payroll-adjustments.index` | `PayrollAdjustmentController@index` | Danh sách điều chỉnh |
| POST | `/api/payroll-adjustments` | `payroll-adjustments.store` | `PayrollAdjustmentController@store` | Tạo điều chỉnh |
| GET | `/api/payroll-adjustments/{id}` | `payroll-adjustments.show` | `PayrollAdjustmentController@show` | Chi tiết |
| PUT | `/api/payroll-adjustments/{id}` | `payroll-adjustments.update` | `PayrollAdjustmentController@update` | Cập nhật |
| PATCH | `/api/payroll-adjustments/{id}` | `payroll-adjustments.patch` | `PayrollAdjustmentController@update` | Cập nhật một phần |
| DELETE | `/api/payroll-adjustments/{id}` | `payroll-adjustments.destroy` | `PayrollAdjustmentController@destroy` | Xóa |
| POST | `/api/payroll-adjustments/{id}/approve` | `payroll-adjustments.approve` | `PayrollAdjustmentController@approve` | Duyệt điều chỉnh |
| POST | `/api/payroll-adjustments/{id}/reject` | `payroll-adjustments.reject` | `PayrollAdjustmentController@reject` | Từ chối điều chỉnh |

## 4.6 Payrolls

| Method | Endpoint | Route name | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- | :-- |
| POST | `/api/payrolls/{id}/approve` | `payrolls.approve` | `PayrollController@approve` | Duyệt kỳ lương |
| POST | `/api/payrolls/{id}/lock` | `payrolls.lock` | `PayrollController@lock` | Khóa kỳ lương |
| POST | `/api/payrolls/{id}/mark-paid` | `payrolls.mark-paid` | `PayrollController@markPaid` | Đánh dấu đã chi trả |
| GET | `/api/payrolls/{id}/export` | `payrolls.export` | `PayrollController@export` | Export 1 kỳ lương |
| GET | `/api/payrolls/driver/{driverId}` | `payrolls.driver-monthly` | `PayrollController@driverMonthlySalary` | Lương theo tài xế |

## 4.7 Driver schedules

| Method | Endpoint | Route name | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- | :-- |
| POST | `/api/driver-schedules/{driverWorkSchedule}/submit` | `driver-schedules.submit` | `DriverScheduleController@submit` | Gửi lịch để duyệt |
| POST | `/api/driver-schedules/{driverWorkSchedule}/approve` | `driver-schedules.approve` | `DriverScheduleController@approve` | Duyệt lịch |
| POST | `/api/driver-schedules/{driverWorkSchedule}/reject` | `driver-schedules.reject` | `DriverScheduleController@reject` | Từ chối lịch |
| POST | `/api/driver-schedules/{driverWorkSchedule}/lock` | `driver-schedules.lock` | `DriverScheduleController@lock` | Khóa lịch |
| POST | `/api/driver-schedules/{driverWorkSchedule}/override` | `driver-schedules.override` | `DriverScheduleController@override` | Override lịch đã lập |
| GET | `/api/driver-schedules/{driverWorkSchedule}/hos-check` | `driver-schedules.hos-check` | `DriverScheduleController@hosCheck` | Kiểm tra giờ lái xe (HOS) |
| POST | `/api/driver-schedules/{driverWorkSchedule}/hos-check` | `driver-schedules.hos-check.post` | `DriverScheduleController@hosCheck` | Kiểm tra HOS bằng POST |

## 4.8 Attendance

| Method | Endpoint | Route name | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- | :-- |
| POST | `/api/attendance/check-in` | `attendance.check-in` | `AttendanceController@checkIn` | Chấm công vào ca |
| POST | `/api/attendance/check-out` | `attendance.check-out` | `AttendanceController@checkOut` | Chấm công ra ca |
| PATCH | `/api/attendance/{id}/adjust` | `attendance.adjust` | `AttendanceController@adjust` | Điều chỉnh chấm công |
| GET | `/api/attendance` | `attendance.index` | `AttendanceController@index` | Danh sách chấm công |

### 4.8.1 Legacy aliases cho FE

| Method | Endpoint | Route name | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- | :-- |
| GET | `/api/attendances` | `attendances.index` | `AttendanceController@index` | Alias index attendance |
| POST | `/api/attendances/check-in` | `attendances.check-in` | `AttendanceController@checkIn` | Alias check-in |
| POST | `/api/attendances/check-out` | `attendances.check-out` | `AttendanceController@checkOut` | Alias check-out |
| PATCH | `/api/attendances/{id}/adjust` | `attendances.adjust` | `AttendanceController@adjust` | Alias adjust |
| GET | `/api/attendances/late` | `attendances.late` | `AttendanceController@late` | Danh sách đi trễ |
| GET | `/api/attendances/late/list` | `attendances.late.list` | `AttendanceController@late` | Alias danh sách đi trễ |
| POST | `/api/attendances/late/notify` | `attendances.late.notify` | `AttendanceController@notifyLate` | Gửi thông báo đi trễ |

## 4.9 Leave

| Method | Endpoint | Route name | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- | :-- |
| GET | `/api/leave/types` | `leave.types` | `LeaveController@types` | Danh mục loại nghỉ |
| POST | `/api/leave/{leaveRequest}/approve` | `leave.approve` | `LeaveController@approve` | Duyệt đơn nghỉ |
| POST | `/api/leave/{leaveRequest}/reject` | `leave.reject` | `LeaveController@reject` | Từ chối đơn nghỉ |
| POST | `/api/leave/{leaveRequest}/cancel` | `leave.cancel` | `LeaveController@cancel` | Hủy đơn nghỉ |
| GET | `/api/leave` | (resource) | `LeaveController@index` | Danh sách đơn nghỉ |
| POST | `/api/leave` | (resource) | `LeaveController@store` | Tạo đơn nghỉ |
| GET | `/api/leave/{id}` | (resource) | `LeaveController@show` | Chi tiết đơn nghỉ |

## 4.10 Overtime

| Method | Endpoint | Route name | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- | :-- |
| POST | `/api/overtime/{overtimeRequest}/approve` | `overtime.approve` | `OvertimeController@approve` | Duyệt OT |
| POST | `/api/overtime/{overtimeRequest}/reject` | `overtime.reject` | `OvertimeController@reject` | Từ chối OT |
| GET | `/api/overtime` | (resource) | `OvertimeController@index` | Danh sách OT |
| POST | `/api/overtime` | (resource) | `OvertimeController@store` | Tạo OT |
| GET | `/api/overtime/{id}` | (resource) | `OvertimeController@show` | Chi tiết OT |

## 4.11 Violations

| Method | Endpoint | Route name | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- | :-- |
| POST | `/api/violations/{violation}/confirm` | `violations.confirm` | `ViolationController@confirm` | Xác nhận vi phạm |
| POST | `/api/violations/{violation}/dispute` | `violations.dispute` | `ViolationController@dispute` | Tạo khiếu nại |
| POST | `/api/violations/{violation}/resolve-dispute` | `violations.resolve-dispute` | `ViolationController@resolveDispute` | Xử lý khiếu nại |
| POST | `/api/violations/{violation}/waive` | `violations.waive` | `ViolationController@waive` | Miễn/giảm vi phạm |
| GET | `/api/violations` | (resource) | `ViolationController@index` | Danh sách vi phạm |
| POST | `/api/violations` | (resource) | `ViolationController@store` | Tạo vi phạm |
| GET | `/api/violations/{id}` | (resource) | `ViolationController@show` | Chi tiết vi phạm |

## 4.12 RBAC

| Method | Endpoint | Route name | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- | :-- |
| POST | `/api/roles/{role}/permissions` | `roles.permissions` | `RoleController@syncPermissions` | Đồng bộ quyền cho role |
| GET | `/api/permissions` | (none) | `PermissionController@index` | Danh sách quyền |
| GET | `/api/permissions/{permission}` | (none) | `PermissionController@show` | Chi tiết quyền |

Ghi chú:
- `users` và `roles` dùng `apiResource` để quản trị đầy đủ.

## 4.13 Reports

| Method | Endpoint | Route name | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- | :-- |
| GET | `/api/reports/dashboard` | (none) | `ReportsController@dashboard` | Tổng quan dashboard |
| GET | `/api/reports/payroll-summary` | (none) | `ReportsController@payrollSummary` | Tổng hợp lương |
| GET | `/api/reports/revenue-summary` | (none) | `ReportsController@revenueSummary` | Tổng hợp doanh thu |
| GET | `/api/reports/exports/revenue` | `reports.exports.revenue` | `ReportsController@exportRevenue` | Export CSV doanh thu |
| GET | `/api/reports/exports/trips` | `reports.exports.trips` | `ReportsController@exportTrips` | Export CSV chuyến |
| GET | `/api/reports/exports/payroll` | `reports.exports.payroll` | `ReportsController@exportPayroll` | Export CSV payroll |

Ghi chú:
- Nhóm export reports đã sử dụng Form Request riêng để validate tham số.
- `exportPayroll` có xử lý trường hợp không tồn tại kỳ lương (trả not found).

## 4.14 AI assistant

| Method | Endpoint | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- |
| POST | `/api/ai/business-assist` | `AiAdvisorController@businessAssist` | Hỗ trợ trợ lý nghiệp vụ |

## 4.15 Legacy compatibility

| Method | Endpoint | Controller@action | Mục đích |
| :-- | :-- | :-- | :-- |
| GET | `/api/documentation` | closure | Trả thông tin link docs |
| GET | `/api/employees` | closure | Alias employees -> drivers |
| GET | `/api/allowances` | closure | Endpoint legacy phụ cấp |
| GET | `/api/deductions` | closure | Endpoint legacy khấu trừ |

## 5) Checklist QA/PO để sử dụng tài liệu này

Khi review 1 module, nên đối chiếu 5 điểm:

- Endpoint đã có đủ method/path chưa.
- Middleware đúng với role truy cập chưa (public/auth/admin/permission).
- Route name đã ổn định để FE gọi chưa (với endpoint có đặt tên).
- Có test Feature cho happy path + validation + permission chưa.
- Có cần giữ legacy alias hay có thể deprecate chưa.

## 6) Lưu ý phạm vi

- Tài liệu này mô tả API đã khai báo trong route hiện tại (as-is).
- Các đề xuất nâng cao (SoD, pre-lock gate, tax finalization, fuel reconcile sâu...) thuộc tài liệu nghiệp vụ to-be, không mặc định đã có endpoint.

## 6.1 Khớp lại Phase 1-3 (Request -> Pricing -> Approval)

Mục này chốt rõ phần chênh lệch giữa workflow mới và backend hiện tại, đồng thời đưa ra bộ API đề xuất để triển khai.

| Phase | Trạng thái backend hiện tại (As-is) | Gap nghiệp vụ | API đề xuất cần bổ sung (To-be) | Ưu tiên |
| :-- | :-- | :-- | :-- | :-- |
| Phase 1 - Tiếp nhận yêu cầu vận chuyển | Chưa có module request/quotation intake riêng | Dispatcher chưa có endpoint chuẩn để tạo yêu cầu vận chuyển đầu vào | `POST /api/transport-requests`, `GET /api/transport-requests`, `GET /api/transport-requests/{id}`, `PATCH /api/transport-requests/{id}` | P1 |
| Phase 2 - Pricing engine | Chưa có endpoint tính giá/áp surcharge/discount/margin check | Chưa chuẩn hóa công thức giá và trạng thái `Pending Pricing -> Pending Approval` | `POST /api/quotations/{id}/calculate-pricing`, `PATCH /api/quotations/{id}/pricing`, `GET /api/quotations/{id}/pricing-breakdown` | P1 |
| Phase 3 - Duyệt báo giá | Chưa có luồng approve/reject quotation | Chưa có quyền duyệt + lý do từ chối + trạng thái approved/rejected | `POST /api/quotations/{id}/approve`, `POST /api/quotations/{id}/reject`, `GET /api/quotations/{id}/approval-logs` | P1 |

### Đề xuất mô hình trạng thái để khớp workflow mới

`transport_request.status`:
- `draft`
- `pending_pricing`
- `pending_approval`
- `approved`
- `rejected`
- `expired`

`quotation.status` (nếu tách riêng quotation khỏi request):
- `pending_pricing`
- `need_manual_pricing`
- `pending_approval`
- `approved`
- `rejected`

### Rule validate cốt lõi cần có ở API mới

- Phase 1:
  - `customer_id` bắt buộc và tồn tại.
  - `pickup_location` khác `delivery_location`.
  - `cargo_weight` > 0.
  - `requested_delivery_date >= today`.
  - customer blacklist: chặn tạo request.
- Phase 2:
  - Nếu không có pricing rule phù hợp: chuyển `need_manual_pricing`.
  - discount vượt ngưỡng policy: bắt buộc manager approve.
  - margin âm: bắt buộc cấp duyệt cao hơn.
- Phase 3:
  - Chỉ được duyệt khi trạng thái `pending_approval`.
  - reject bắt buộc `rejection_reason`.
  - ghi audit đầy đủ `who/when/reason/before-after`.

### Liên kết với Phase 4 hiện có (Trip)

- Bổ sung rule trong `TripController@store`:
  - Chỉ cho tạo trip khi quotation/request đã `approved`.
  - Không cho tạo trùng trip từ cùng một quotation đã bind.
  - Nếu quotation bị `rejected/expired`: trả lỗi `OPERATION_NOT_ALLOWED`.

## 7) Chính sách xóa dữ liệu an toàn (Data Deletion Policy)

**Đối tượng áp dụng:** Backend, BA, QA, FE.  
**Mục tiêu:** Đảm bảo tính toàn vẹn dữ liệu, không làm mất dấu vết (audit trail), bảo vệ dữ liệu tài chính và vận hành.

### 7.1 Triết lý thiết kế: "Xóa nhưng không mất"

- Hệ thống ưu tiên **Soft Delete** và **trạng thái nghiệp vụ** thay vì xóa vật lý khỏi database.
- Dữ liệu lịch sử: các thực thể đã phát sinh giao dịch (chuyến xe, lương, hóa đơn) **không được xóa cứng**.
- Ràng buộc khóa ngoại (Foreign Key): ưu tiên `RESTRICT` thay vì `CASCADE` để ngăn xóa bản ghi cha khi bản ghi con còn tồn tại.

### 7.2 Ma trận tác động và điều kiện chặn (Guard Clauses)

Khi thực hiện lệnh xóa, hệ thống phải kiểm tra chéo theo bảng sau:

| Thực thể | Nếu xóa sẽ ảnh hưởng đến... | Điều kiện CHẶN xóa (phải xử lý trước) |
| :-- | :-- | :-- |
| Driver (Tài xế) | Trips, Payroll, Attendance, Assignments, Violations | 1) Có chuyến xe chưa hoàn thành (`status != completed/cancelled`). 2) Có kỳ lương đang mở hoặc chưa chi trả. 3) Đang có xe được gán (Vehicle Assignment) còn hiệu lực. |
| Vehicle (Xe) | Trips, Assignments, Expenses, Maintenance | 1) Xe đang trong hành trình (`in-transit`). 2) Có các khoản chi phí xe chưa quyết toán. 3) Đang được gán cho tài xế. |
| Assignment (Gán xe) | Lịch sử vận hành, trách nhiệm pháp lý | Không cho phép xóa nếu bản ghi đã bắt đầu hiệu lực; chỉ cho phép thiết lập End Date để kết thúc gán. |
| Office/Department | Driver, User, Work Schedule | Còn nhân sự hoặc tài xế đang thuộc văn phòng/phòng ban này. |

### 7.3 Quy trình xử lý xóa 3 bước (Standard Workflow)

Để đảm bảo nghiêm ngặt về dữ liệu, backend xử lý theo 3 bước:

1. **Kiểm tra dependencies (sự phụ thuộc):** quét các bảng liên quan trước khi cho phép thao tác.
   - Ví dụ: trước khi xóa tài xế A, kiểm tra tài xế đó có xuất hiện trong `trips` với trạng thái active hay không.
2. **Chuyển đổi trạng thái trung gian:** thay vì biến mất, bản ghi chuyển về trạng thái ngưng hoạt động.
   - Drivers: `Resigned` hoặc `Inactive`.
   - Vehicles: `Liquidated` hoặc `Out of Service`.
3. **Đánh dấu soft delete:** cập nhật `deleted_at`.
   - Bản ghi được ẩn khỏi màn hình làm việc thông thường nhưng vẫn tồn tại để phục vụ báo cáo tháng/quý/năm và kiểm toán.

### 7.4 Quy chuẩn phản hồi API khi chặn xóa

Khi xóa bị chặn, API phải trả lỗi rõ nguyên nhân (không trả lỗi chung chung), để FE hiển thị cụ thể cho người dùng.

Ví dụ response khi xóa tài xế thất bại:

```json
{
  "success": false,
  "message": "Không thể xóa tài xế này.",
  "errors": {
    "code": "DEPENDENCY_RESTRICTION",
    "details": [
      "Tài xế đang có 02 chuyến xe chưa hoàn thành (ID: TRP-101, TRP-105).",
      "Tài xế còn 01 kỳ lương tháng 04/2026 chưa chốt."
    ]
  }
}
```

### 7.5 Lưu ý đặc biệt cho QA/Tester

Khi kiểm thử nghiệp vụ xóa, cần có các kịch bản "phá hủy":

- **Xóa tài xế đang lái xe:** hệ thống phải chặn và báo lỗi rõ ràng.
- **Kiểm tra tính kế thừa dữ liệu:** sau khi soft delete tài xế, mở báo cáo lương cũ; nếu tên tài xế thành `N/A` hoặc lỗi `500` là thất bại.
- **Xóa xe có chi phí:** thử xóa xe đã có dữ liệu xăng dầu; hệ thống phải yêu cầu quyết toán chi phí trước khi cho phép ẩn xe.

### 7.6 Áp dụng chính sách "xóa an toàn" cho tất cả module

Quy ước thao tác:
- **BLOCK_DELETE**: không cho xóa qua API.
- **SOFT_DELETE_ONLY**: chỉ cho soft delete sau khi qua guard clauses.
- **END_DATE_ONLY**: không xóa, chỉ kết thúc hiệu lực.
- **STATE_TRANSITION_ONLY**: không xóa, chỉ đổi trạng thái nghiệp vụ.

| Module/Thực thể | Endpoint base | Chiến lược xóa | Guard clauses bắt buộc |
| :-- | :-- | :-- | :-- |
| Companies | `/api/companies` | BLOCK_DELETE | Còn office/driver/vehicle/customer/trip/invoice/payroll thuộc công ty. |
| Offices | `/api/offices` | SOFT_DELETE_ONLY | Còn department/driver/vehicle/user/work schedule active. |
| Departments | `/api/departments` | SOFT_DELETE_ONLY | Còn driver/user active thuộc phòng ban. |
| Positions | `/api/positions` | SOFT_DELETE_ONLY | Còn driver active đang dùng vị trí. |
| Drivers | `/api/drivers` | STATE_TRANSITION_ONLY | Không cho xóa; chuyển `inactive/resigned`; phải tháo gán xe còn hiệu lực. |
| Vehicles | `/api/vehicles` | STATE_TRANSITION_ONLY | Không cho xóa; chuyển `maintenance/out_of_service/liquidated`; không còn chuyến in-transit. |
| Vehicle assignments | `/api/vehicle_assignments` | END_DATE_ONLY | Không xóa assignment đã hiệu lực; chỉ cập nhật ngày kết thúc (`end_date`). |
| Vehicle expenses | `/api/vehicle_expenses` | SOFT_DELETE_ONLY | Chưa quyết toán/chưa khóa kỳ báo cáo thì không cho xóa. |
| Customers | `/api/customers` | SOFT_DELETE_ONLY | Còn trip/invoice active hoặc công nợ chưa xử lý. |
| Trips | `/api/trips` | STATE_TRANSITION_ONLY | Không xóa cứng; dùng vòng đời `cancelled/completed`; cấm xóa chuyến đã phát sinh invoice/payroll. |
| Invoices | `/api/invoices` | STATE_TRANSITION_ONLY | Không xóa cứng; dùng trạng thái `cancel`; cấm xóa invoice đã issue/paid. |
| Payrolls | `/api/payrolls` | BLOCK_DELETE | Không cho xóa kỳ lương; chỉ state transition (`draft/approved/locked/paid`) theo policy. |
| Payroll adjustments | `/api/payroll-adjustments` | SOFT_DELETE_ONLY | Chỉ xóa mềm khi chưa approve/reject và chưa ảnh hưởng bút toán. |
| Driver schedules | `/api/driver-schedules` | SOFT_DELETE_ONLY | Không cho xóa bản ghi đã lock/đã được dùng để phân công chuyến. |
| Work schedule templates | `/api/work-schedule-templates` | SOFT_DELETE_ONLY | Không cho xóa nếu đang áp dụng cho office/schedule active. |
| Leave requests | `/api/leave` | STATE_TRANSITION_ONLY | Không xóa cứng; dùng trạng thái `cancel/reject/approved`. |
| Overtime requests | `/api/overtime` | STATE_TRANSITION_ONLY | Không xóa cứng; dùng trạng thái `reject/approved`. |
| Violations | `/api/violations` | STATE_TRANSITION_ONLY | Không xóa cứng; dùng trạng thái `waive/dispute/resolved`. |
| Users | `/api/users` | SOFT_DELETE_ONLY | Không xóa hard nếu còn lịch sử action/audit; ưu tiên khóa tài khoản. |
| Roles | `/api/roles` | SOFT_DELETE_ONLY | Không xóa role đang gán cho user; phải gỡ mapping trước. |

### 7.7 Chuẩn hành vi API cho thao tác xóa

- Nếu thực thể thuộc `BLOCK_DELETE` hoặc `STATE_TRANSITION_ONLY`, endpoint `DELETE` phải trả lỗi nghiệp vụ rõ ràng:
  - `code`: `OPERATION_NOT_ALLOWED` hoặc `DEPENDENCY_RESTRICTION`
  - `details`: liệt kê điều kiện chưa thỏa.
- Nếu thuộc `SOFT_DELETE_ONLY`, backend chỉ set `deleted_at`, không xóa vật lý.
- Nếu thuộc `END_DATE_ONLY`, backend trả hướng dẫn chuyển sang thao tác kết thúc hiệu lực thay vì xóa.

Ví dụ response chung:

```json
{
  "success": false,
  "message": "Thao tác xóa không được phép.",
  "errors": {
    "code": "OPERATION_NOT_ALLOWED",
    "details": [
      "Thực thể này thuộc nhóm STATE_TRANSITION_ONLY.",
      "Vui lòng dùng thao tác cập nhật trạng thái thay cho xóa."
    ]
  }
}
```
