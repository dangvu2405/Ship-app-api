# TÀI LIỆU ĐẶC TẢ SƠ ĐỒ TUẦN TỰ & HOẠT ĐỘNG
## Hệ thống: Company Ship ERP - Quản lý vận tải & nhân sự

---

## 1. Sơ đồ tuần tự (Sequence Diagram)

### SD-01: Luồng đăng nhập hệ thống

```plantuml
@startuml SD_Login
actor "Người dùng" as User
participant "Client\n(Frontend)" as Client
participant "AuthController\n/api/auth/login" as Auth
participant "UserModel" as UserModel
database "MySQL DB" as DB

User -> Client : Nhập email & password
Client -> Auth : POST /api/auth/login\n{email, password}
Auth -> Auth : Validate request\n(email required, password min:6)

alt Validation thất bại
  Auth --> Client : HTTP 422 - Validation Error
  Client --> User : Hiển thị lỗi nhập liệu
else Validation thành công
  Auth -> UserModel : User::where('email', email)\n.where('status', 'active').first()
  UserModel -> DB : SELECT * FROM users\nWHERE email=? AND status='active'
  DB --> UserModel : User record

  alt Tài khoản không tồn tại hoặc bị khóa
    Auth --> Client : HTTP 401 - Invalid credentials
    Client --> User : Hiển thị thông báo lỗi
  else Tài khoản hợp lệ
    Auth -> Auth : Hash::check(password, user.password)
    alt Mật khẩu sai
      Auth --> Client : HTTP 401 - Invalid credentials
    else Mật khẩu đúng
      Auth -> UserModel : createToken('auth-token')
      UserModel -> DB : INSERT INTO personal_access_tokens
      Auth -> UserModel : update({last_login_at: now()})
      Auth -> UserModel : load(['employee', 'roles.permissions'])
      UserModel -> DB : SELECT roles, permissions...
      DB --> UserModel : Roles & permissions data
      Auth --> Client : HTTP 200 - {user, token}
      Client --> User : Đăng nhập thành công\nLưu token vào localStorage
    end
  end
end
@enduml
```

---

### SD-02: Luồng tạo bảng lương

```plantuml
@startuml SD_GeneratePayroll
actor "Admin/Kế toán" as Admin
participant "Client" as Client
participant "PayrollController\n/api/payrolls" as Controller
participant "Sanctum\nMiddleware" as Middleware
participant "PayrollService" as Service
participant "PayrollModel" as PModel
participant "EmployeeModel" as EModel
participant "AttendanceModel" as AModel
database "MySQL DB" as DB

Admin -> Client : Chọn công ty, tháng, năm\nBấm "Tạo bảng lương"
Client -> Middleware : POST /api/payrolls\n{company_id, month, year}\n+ Bearer Token
Middleware -> Middleware : Xác thực token\nKiểm tra role:admin

alt Token không hợp lệ
  Middleware --> Client : HTTP 401 - Unauthenticated
else Token hợp lệ
  Middleware -> Controller : Chuyển request
  Controller -> Controller : StorePayrollRequest\nValidate input

  Controller -> Service : generatePayroll(company_id, month, year)

  Service -> PModel : Kiểm tra bảng lương\ntháng đó đã tồn tại chưa
  PModel -> DB : SELECT * FROM payrolls\nWHERE company_id=? AND month=? AND year=?
  DB --> PModel : result

  alt Đã tồn tại
    Service --> Controller : throw Exception('Payroll already exists')
    Controller --> Client : HTTP 422 - Error message
  else Chưa tồn tại
    Service -> EModel : Lấy danh sách nhân viên công ty
    EModel -> DB : SELECT * FROM employees\nWHERE office.company_id=?
    DB --> EModel : Employee list

    loop Mỗi nhân viên
      Service -> AModel : Tổng hợp chấm công tháng
      AModel -> DB : SELECT SUM(work_hours), COUNT(date)\nFROM attendances\nWHERE employee_id=? AND month=?
      DB --> AModel : Attendance summary

      Service -> DB : Lấy phụ cấp nhân viên\n(employee_allowances)
      DB --> Service : Allowance amounts

      Service -> DB : Lấy khấu trừ nhân viên\n(employee_deductions)
      DB --> Service : Deduction amounts

      Service -> Service : Tính lương:\nnet = base + overtime + bonus\n+ allowance - deduction - tax

      Service -> DB : INSERT INTO payroll_details\n{employee_id, base_salary, net_salary...}
    end

    Service -> DB : INSERT INTO payrolls\n{company_id, month, year, status='draft'}
    DB --> Service : Payroll record
    Service --> Controller : Payroll object

    Controller --> Client : HTTP 201 - Payroll created
    Client --> Admin : Hiển thị bảng lương mới tạo
  end
end
@enduml
```

---

### SD-03: Luồng duyệt & khóa bảng lương

```plantuml
@startuml SD_ApprovePayroll
actor "Kế toán" as Accountant
actor "Admin" as Admin
participant "Client" as Client
participant "PayrollController" as Controller
participant "PayrollService" as Service
database "MySQL DB" as DB

== Duyệt bảng lương ==
Accountant -> Client : Bấm "Duyệt bảng lương" (payroll id=5)
Client -> Controller : POST /api/payrolls/5/approve\n+ Bearer Token
Controller -> Service : approvePayroll(5)
Service -> DB : SELECT * FROM payrolls WHERE id=5
DB --> Service : Payroll {status:'draft'}

alt Bảng lương đã khóa
  Service --> Controller : throw Exception('Cannot approve locked payroll')
  Controller --> Client : HTTP 422 - Error
else Bảng lương hợp lệ
  Service -> DB : UPDATE payrolls SET\nstatus='approved',\napproved_by=user_id,\napproved_at=NOW()\nWHERE id=5
  DB --> Service : OK
  Controller --> Client : HTTP 200 - Payroll approved
  Client --> Accountant : Thông báo duyệt thành công
end

== Khóa bảng lương ==
Admin -> Client : Bấm "Khóa bảng lương" (payroll id=5)
Client -> Controller : POST /api/payrolls/5/lock\n+ Bearer Token
Controller -> Service : lockPayroll(5)
Service -> DB : SELECT * FROM payrolls WHERE id=5
DB --> Service : Payroll {status:'approved'}

alt Chưa được duyệt
  Service --> Controller : throw Exception('Must be approved before locking')
  Controller --> Client : HTTP 422 - Error
else Đã được duyệt
  Service -> DB : UPDATE payrolls SET\nstatus='locked',\nlocked_at=NOW()\nWHERE id=5
  DB --> Service : OK
  Controller --> Client : HTTP 200 - Payroll locked
  Client --> Admin : Thông báo khóa thành công
end
@enduml
```

---

### SD-04: Luồng tạo chuyến đi & hóa đơn

```plantuml
@startuml SD_Trip
actor "Điều phối" as Coord
actor "Kế toán" as Accountant
participant "Client" as Client
participant "TripController\n/api/trips" as TripCtrl
participant "InvoiceController\n/api/invoices" as InvoiceCtrl
database "MySQL DB" as DB

== Tạo chuyến đi ==
Coord -> Client : Nhập thông tin chuyến đi\n(khách hàng, tài xế, xe, tuyến đường)
Client -> TripCtrl : POST /api/trips\n{customer_id, driver_id, vehicle_id,\nstart_point, end_point, price, ...}
TripCtrl -> TripCtrl : Validate input
TripCtrl -> DB : Kiểm tra tài xế & xe còn khả dụng
DB --> TripCtrl : available

TripCtrl -> DB : INSERT INTO trips\n{code, customer_id, driver_id, vehicle_id,\nstatus='pending'}
DB --> TripCtrl : Trip record
TripCtrl --> Client : HTTP 201 - Trip created
Client --> Coord : Hiển thị chuyến đi mới

== Cập nhật trạng thái chuyến ==
Coord -> Client : Cập nhật trạng thái → 'in_progress'
Client -> TripCtrl : PUT /api/trips/{id}\n{status:'in_progress'}
TripCtrl -> DB : UPDATE trips SET status='in_progress'\nWHERE id=?
TripCtrl --> Client : HTTP 200 - Updated

Coord -> Client : Hoàn thành chuyến → 'completed'
Client -> TripCtrl : PUT /api/trips/{id}\n{status:'completed'}
TripCtrl -> DB : UPDATE trips SET status='completed',\nend_time=NOW() WHERE id=?
TripCtrl --> Client : HTTP 200 - Updated

== Tạo hóa đơn ==
Accountant -> Client : Tạo hóa đơn cho chuyến đi
Client -> InvoiceCtrl : POST /api/invoices\n{trip_id, customer_id, vat_rate}
InvoiceCtrl -> DB : SELECT * FROM trips WHERE id=trip_id
DB --> InvoiceCtrl : Trip + price info
InvoiceCtrl -> InvoiceCtrl : Tính subtotal, vat_amount, total_amount
InvoiceCtrl -> DB : INSERT INTO invoices\n{code, trip_id, customer_id,\nsubtotal, total_amount, status='draft'}
DB --> InvoiceCtrl : Invoice record
InvoiceCtrl --> Client : HTTP 201 - Invoice created
Client --> Accountant : Hiển thị hóa đơn mới
@enduml
```

---

### SD-05: Luồng gán xe - tài xế

```plantuml
@startuml SD_VehicleAssign
actor "Điều phối" as Coord
participant "Client" as Client
participant "VehicleAssignmentController" as Ctrl
database "MySQL DB" as DB

Coord -> Client : Chọn xe, tài xế, ngày bắt đầu/kết thúc
Client -> Ctrl : POST /api/vehicle_assignments\n{vehicle_id, driver_id, from_date, to_date}
Ctrl -> Ctrl : Validate request

Ctrl -> DB : Kiểm tra tài xế không bị gán xe\ntrong khoảng thời gian này
DB --> Ctrl : result

alt Bị conflict lịch
  Ctrl --> Client : HTTP 422 - Driver already assigned
  Client --> Coord : Thông báo lỗi xung đột lịch
else Không conflict
  Ctrl -> DB : INSERT INTO vehicle_assignments\n{vehicle_id, driver_id, from_date, to_date}
  DB --> Ctrl : Assignment record
  Ctrl --> Client : HTTP 201 - Assignment created
  Client --> Coord : Gán thành công
end
@enduml
```

---

## 2. Sơ đồ hoạt động (Activity Diagram)

### AD-01: Quy trình xử lý lương hàng tháng

```plantuml
@startuml AD_MonthlyPayroll
start
:Thu thập dữ liệu chấm công\n(attendances table);
:Tổng hợp ngày công, giờ OT\n(attendance_summaries);

if (Đủ dữ liệu?) then (có)
  :Tạo bảng lương (POST /payrolls)\nstatus = 'draft';
  :Kiểm tra dữ liệu lương\nmỗi nhân viên;
  
  if (Cần điều chỉnh?) then (có)
    :Thêm payroll_adjustments;
    :Cập nhật payroll_details;
  else (không)
  endif
  
  :Kế toán duyệt\n(POST /payrolls/{id}/approve)\nstatus = 'approved';
  
  if (Cần chỉnh sửa sau duyệt?) then (có)
    :Hủy duyệt, sửa lại;
    :Duyệt lại;
  else (không)
  endif
  
  :Admin khóa bảng lương\n(POST /payrolls/{id}/lock)\nstatus = 'locked';
  :Xuất Excel/JSON\n(GET /payrolls/{id}/export);
  :Thực hiện chi lương;
else (không)
  :Yêu cầu bổ sung dữ liệu chấm công;
  stop
endif

stop
@enduml
```

### AD-02: Quy trình quản lý chuyến đi

```plantuml
@startuml AD_TripProcess
start
:Điều phối nhận yêu cầu vận chuyển\ntừ khách hàng;
:Kiểm tra xe và tài xế sẵn sàng;

if (Có xe/tài xế?) then (có)
  :Tạo chuyến đi\n(POST /trips)\nstatus = 'pending';
  :Gán xe - tài xế\n(POST /vehicle_assignments);
  :Thông báo cho tài xế;
  
  :Tài xế bắt đầu chuyến\n(PUT /trips/{id})\nstatus = 'in_progress';
  
  :Ghi nhận chi phí phát sinh\n(POST /vehicle_expenses);
  
  :Tài xế hoàn thành\n(PUT /trips/{id})\nstatus = 'completed';
  
  :Kế toán tạo hóa đơn\n(POST /invoices);
  
  if (Khách hàng thanh toán?) then (có)
    :Cập nhật hóa đơn\nstatus = 'paid'\npaid_at = NOW();
  else (chưa)
    :Theo dõi công nợ;
    :Nhắc nhở thanh toán;
  endif
else (không)
  :Thông báo cho khách hàng;
  :Lên kế hoạch lại;
  stop
endif

stop
@enduml
```

---

## 3. Sơ đồ lớp đơn giản hóa (Simplified Class Diagram)

```plantuml
@startuml ClassDiagram
skinparam classAttributeIconSize 0

class User {
  +id: bigint PK
  +username: varchar
  +email: varchar
  +password: varchar
  +employee_id: bigint FK
  +status: enum
  ---
  +login()
  +logout()
  +hasRole(role): bool
  +hasPermission(perm): bool
}

class Employee {
  +id: bigint PK
  +code: varchar UNIQUE
  +name: varchar
  +department_id: bigint FK
  +position_id: bigint FK
  +office_id: bigint FK
  +status: enum
  ---
  +getPayrolls()
  +getAttendances()
}

class Payroll {
  +id: bigint PK
  +company_id: bigint FK
  +month: int
  +year: int
  +status: enum
  ---
  +approve()
  +lock()
  +export()
}

class Trip {
  +id: bigint PK
  +code: varchar UNIQUE
  +driver_id: bigint FK
  +vehicle_id: bigint FK
  +customer_id: bigint FK
  +status: enum
  ---
  +start()
  +complete()
  +generateInvoice()
}

class Vehicle {
  +id: bigint PK
  +plate_number: varchar
  +type: enum
  +status: enum
  ---
  +isAvailable(from, to): bool
}

class Role {
  +id: bigint PK
  +name: varchar UNIQUE
  ---
  +syncPermissions(perms[])
}

User "1" -- "0..1" Employee : linked to
User "N" -- "N" Role : has
Role "N" -- "N" Permission : has
Employee "1" -- "N" Payroll : included in
Employee "1" -- "1" Driver : can be
Driver "1" -- "N" Trip : drives
Vehicle "1" -- "N" Trip : used in
Vehicle "1" -- "N" VehicleAssignment : assigned via

@enduml
```
