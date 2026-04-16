# Use Case Diagram Specification - Company Ship Management System

## 1) Thong tin he thong

**Ten he thong:** Company Ship Management System

**Mo ta ngan:**  
He thong quan ly van hanh van tai cho doanh nghiep, bao gom quan ly tai xe, phuong tien, chuyen di, cham cong, nghi phep, tang ca, vi pham va tinh luong. He thong ho tro phan quyen theo vai tro, giam sat thao tac nguoi dung, bao cao quan tri va tro ly AI cho nghiep vu. Dung cho ca tac nghiep hang ngay va kiem soat noi bo.

**Pham vi he thong (System Scope):**
- **Bao gom:** Backend nghiep vu, Web Admin, giao dien tac nghiep cho nhan su van hanh, chuc nang nghiep vu cho tai xe (qua ung dung/kenh tich hop), bao cao quan tri.
- **Khong bao gom:** He thong ngan hang, nha cung cap dang nhap xa hoi, nen tang chat ben ngoai, he thong email/SMS ben thu ba (chi tich hop su dung).

---

## 2) Actor (Tac nhan)

- **Administrator**
  - **Type:** Primary
  - **Description:** Quan tri toan he thong, cau hinh danh muc, phan quyen, giam sat van hanh.
- **HR Staff**
  - **Type:** Primary
  - **Description:** Quan ly ho so tai xe, nghi phep, cham cong, du lieu luong.
- **Operations Staff**
  - **Type:** Primary
  - **Description:** Quan ly lich lam viec, dieu phoi chuyen di, theo doi vi pham.
- **Payroll Staff**
  - **Type:** Primary
  - **Description:** Tinh luong, duyet/khoa ky luong, xuat bang luong.
- **Approver (Manager)**
  - **Type:** Primary
  - **Description:** Duyet cac yeu cau (lich, nghi phep, tang ca, vi pham, luong).
- **Driver**
  - **Type:** Primary
  - **Description:** Thuc hien cham cong, gui yeu cau nghi phep/tang ca, theo doi luong, phan hoi vi pham.
- **Auditor/Compliance**
  - **Type:** Primary
  - **Description:** Theo doi nhat ky thao tac, kiem soat phan tach nhiem vu.
- **Customer Service Staff**
  - **Type:** Primary
  - **Description:** Quan ly khach hang, hoa don va ho tro doi soat dich vu.

- **Identity Provider (Google/Facebook/Apple/Lark)**
  - **Type:** Secondary
  - **Description:** Cung cap xac thuc dang nhap ben ngoai.
- **AI Assistant Service**
  - **Type:** Secondary
  - **Description:** Cung cap tu van nghiep vu thong minh.
- **Notification Service**
  - **Type:** Secondary
  - **Description:** Gui thong bao nghiep vu toi nguoi dung.

**Generalization (Actor):**
- **Staff** (abstract)
  - Ke thua: `HR Staff`, `Operations Staff`, `Payroll Staff`, `Customer Service Staff`, `Approver (Manager)`
- **Authenticated User** (abstract)
  - Ke thua: `Administrator`, toan bo `Staff`, `Driver`, `Auditor/Compliance`

---

## 3) Use Case (Chuc nang)

### Domain: Identity & Access
- **Authenticated User**
  - Sign In
  - Sign Out
  - Refresh Session
  - View My Profile
- **Administrator**
  - Register User
  - Manage Roles
  - Assign Permissions
  - View User Sessions
  - Revoke Session
  - Lock Account

### Domain: Master Data & Organization
- **Administrator**
  - Manage Company
  - Manage Office
  - Manage Department
  - Manage Position
  - Manage User
- **HR Staff**
  - Manage Driver Profile
- **Operations Staff**
  - Manage Vehicle
  - Assign Vehicle

### Domain: Operations (Trip & Invoice)
- **Operations Staff**
  - Manage Trip
  - Update Trip Status
- **Customer Service Staff**
  - Manage Customer
  - Manage Invoice
  - Update Invoice Status

### Domain: Workforce (Schedule, Attendance, Leave, Overtime)
- **Operations Staff**
  - Create Work Schedule
  - Submit Work Schedule
- **Approver (Manager)**
  - Approve Work Schedule
  - Reject Work Schedule
  - Lock Work Schedule Period
  - Override Work Schedule Lock
- **Driver**
  - Check In
  - Check Out
  - Submit Leave Request
  - Cancel Leave Request
  - Submit Overtime Request
- **HR Staff**
  - Adjust Attendance
  - Review Late Attendance
- **Approver (Manager)**
  - Approve Leave Request
  - Reject Leave Request
  - Approve Overtime Request
  - Reject Overtime Request

### Domain: Compliance (Violation & Dispute)
- **Operations Staff**
  - Report Violation
  - Confirm Violation
  - Waive Violation
- **Driver**
  - Submit Violation Dispute
- **Approver (Manager)**
  - Resolve Violation Dispute
- **Auditor/Compliance**
  - Monitor User Actions
  - Review Separation of Duties Breach

### Domain: Payroll & Reporting
- **Payroll Staff**
  - Calculate Payroll
  - Approve Payroll
  - Lock Payroll
  - Export Payroll
- **Driver**
  - View My Salary
- **Administrator / Auditor/Compliance**
  - View Dashboard Report
  - View Payroll Summary Report
  - View Audit Action Log

### Domain: Smart Assistant
- **Administrator / Staff**
  - Request Business Advice

---

## 4) Quan he giua cac Use Case

### <<include>> (bat buoc xay ra)
- `(Sign Out) <<include>> (Record User Action)`  
  Ly do: dang xuat luon phai duoc ghi nhan.
- `(Refresh Session) <<include>> (Record User Action)`  
  Ly do: lam moi phien la hanh dong bao mat can log.
- `(Approve Work Schedule) <<include>> (Check Separation of Duties)`  
  Ly do: nguoi tao khong duoc tu duyet.
- `(Approve Leave Request) <<include>> (Check Separation of Duties)`
- `(Approve Overtime Request) <<include>> (Check Separation of Duties)`
- `(Confirm Violation) <<include>> (Check Separation of Duties)`
- `(Approve Payroll) <<include>> (Check Separation of Duties)`
- `(Lock Payroll) <<include>> (Check Separation of Duties)`
- `(Calculate Payroll) <<include>> (Validate Attendance Data)`
- `(Calculate Payroll) <<include>> (Apply Overtime Policy)`
- `(Calculate Payroll) <<include>> (Apply Leave Rules)`
- `(Calculate Payroll) <<include>> (Apply Violation Deduction)`
- `(Export Payroll) <<include>> (Record User Action)`
- `(Monitor User Actions) <<include>> (View Audit Action Log)`

### <<extend>> (xay ra co dieu kien)
- `(Lock Work Schedule Period) <<extend>> (Approve Work Schedule)`  
  Dieu kien: ky da duyet va den thoi diem khoa.
- `(Override Work Schedule Lock) <<extend>> (Lock Work Schedule Period)`  
  Dieu kien: co quyen dac biet va co ly do override.
- `(Reject Work Schedule) <<extend>> (Submit Work Schedule)`  
  Dieu kien: lich khong hop le hoac xung dot.
- `(Reject Leave Request) <<extend>> (Submit Leave Request)`  
  Dieu kien: thieu quota/ho so khong hop le.
- `(Reject Overtime Request) <<extend>> (Submit Overtime Request)`  
  Dieu kien: vuot han muc hoac sai chinh sach.
- `(Submit Violation Dispute) <<extend>> (Confirm Violation)`  
  Dieu kien: tai xe khong dong y quyet dinh vi pham.
- `(Waive Violation) <<extend>> (Confirm Violation)`  
  Dieu kien: co can cu mien phat.
- `(Lock Account) <<extend>> (View User Sessions)`  
  Dieu kien: phat hien rui ro bao mat.
- `(Revoke Session) <<extend>> (View User Sessions)`  
  Dieu kien: phien bat thuong hoac yeu cau bao mat.

### Generalization
- `(Manage Master Data)` la use case tong quat cua:
  - `(Manage Company)`, `(Manage Office)`, `(Manage Department)`, `(Manage Position)`
- `(Manage Workforce)` la use case tong quat cua:
  - `(Create Work Schedule)`, `(Adjust Attendance)`, `(Submit Leave Request)`, `(Submit Overtime Request)`

---

## 5) Business Rules (Luat nghiep vu)

- Nguoi dung phai dang nhap moi thuc hien nghiep vu.
- Chi nguoi co vai tro phu hop moi duoc duyet/khoa du lieu.
- Ap dung Separation of Duties: nguoi tao khong duoc tu duyet hanh dong nhay cam.
- Ky luong theo luong trang thai: `draft -> approved -> locked`.
- Khi ky luong da khoa thi khong duoc sua du lieu anh huong luong.
- Lich lam viec khong duoc xung dot tai xe/phuong tien.
- Tang ca can nam trong han muc va duoc duyet moi tinh luong.
- Nghi phep dung quota; nghi khong luong phai tru vao luong.
- Vi pham duoc phep khieu nai; ket qua khieu nai anh huong khau tru.
- Moi thao tac quan trong phai duoc ghi nhat ky de kiem soat.

---

## 6.1) Danh sach Use Case hoan chinh (clean)

`Sign In`, `Sign Out`, `Refresh Session`, `View My Profile`, `Register User`, `Manage Roles`, `Assign Permissions`, `View User Sessions`, `Revoke Session`, `Lock Account`, `Manage Company`, `Manage Office`, `Manage Department`, `Manage Position`, `Manage User`, `Manage Driver Profile`, `Manage Vehicle`, `Assign Vehicle`, `Manage Trip`, `Update Trip Status`, `Manage Customer`, `Manage Invoice`, `Update Invoice Status`, `Create Work Schedule`, `Submit Work Schedule`, `Approve Work Schedule`, `Reject Work Schedule`, `Lock Work Schedule Period`, `Override Work Schedule Lock`, `Check In`, `Check Out`, `Adjust Attendance`, `Review Late Attendance`, `Submit Leave Request`, `Approve Leave Request`, `Reject Leave Request`, `Cancel Leave Request`, `Submit Overtime Request`, `Approve Overtime Request`, `Reject Overtime Request`, `Report Violation`, `Confirm Violation`, `Waive Violation`, `Submit Violation Dispute`, `Resolve Violation Dispute`, `Calculate Payroll`, `Approve Payroll`, `Lock Payroll`, `Export Payroll`, `View My Salary`, `View Dashboard Report`, `View Payroll Summary Report`, `View Audit Action Log`, `Monitor User Actions`, `Review Separation of Duties Breach`, `Request Business Advice`, `Record User Action`, `Check Separation of Duties`.

---

## 6.2) So do Use Case dang text (de import)

```text
[Authenticated User] --> (Sign In)
[Authenticated User] --> (Sign Out)
[Authenticated User] --> (Refresh Session)
[Authenticated User] --> (View My Profile)

[Administrator] --> (Register User)
[Administrator] --> (Manage Roles)
[Administrator] --> (Assign Permissions)
[Administrator] --> (View User Sessions)
[Administrator] --> (Revoke Session)
[Administrator] --> (Lock Account)

[HR Staff] --> (Manage Driver Profile)
[Operations Staff] --> (Manage Vehicle)
[Operations Staff] --> (Assign Vehicle)
[Operations Staff] --> (Manage Trip)
[Customer Service Staff] --> (Manage Customer)
[Customer Service Staff] --> (Manage Invoice)

[Operations Staff] --> (Create Work Schedule)
[Operations Staff] --> (Submit Work Schedule)
[Approver (Manager)] --> (Approve Work Schedule)
[Approver (Manager)] --> (Reject Work Schedule)
[Approver (Manager)] --> (Lock Work Schedule Period)
[Approver (Manager)] --> (Override Work Schedule Lock)

[Driver] --> (Check In)
[Driver] --> (Check Out)
[Driver] --> (Submit Leave Request)
[Driver] --> (Cancel Leave Request)
[Driver] --> (Submit Overtime Request)
[HR Staff] --> (Adjust Attendance)
[HR Staff] --> (Review Late Attendance)
[Approver (Manager)] --> (Approve Leave Request)
[Approver (Manager)] --> (Reject Leave Request)
[Approver (Manager)] --> (Approve Overtime Request)
[Approver (Manager)] --> (Reject Overtime Request)

[Operations Staff] --> (Report Violation)
[Operations Staff] --> (Confirm Violation)
[Operations Staff] --> (Waive Violation)
[Driver] --> (Submit Violation Dispute)
[Approver (Manager)] --> (Resolve Violation Dispute)

[Payroll Staff] --> (Calculate Payroll)
[Payroll Staff] --> (Approve Payroll)
[Payroll Staff] --> (Lock Payroll)
[Payroll Staff] --> (Export Payroll)
[Driver] --> (View My Salary)

[Administrator] --> (View Dashboard Report)
[Administrator] --> (View Payroll Summary Report)
[Auditor/Compliance] --> (Monitor User Actions)
[Auditor/Compliance] --> (Review Separation of Duties Breach)
[Auditor/Compliance] --> (View Audit Action Log)

[Staff] --> (Request Business Advice)

(Sign Out) --> <<include>> (Record User Action)
(Refresh Session) --> <<include>> (Record User Action)
(Export Payroll) --> <<include>> (Record User Action)
(Approve Work Schedule) --> <<include>> (Check Separation of Duties)
(Approve Leave Request) --> <<include>> (Check Separation of Duties)
(Approve Overtime Request) --> <<include>> (Check Separation of Duties)
(Confirm Violation) --> <<include>> (Check Separation of Duties)
(Approve Payroll) --> <<include>> (Check Separation of Duties)
(Lock Payroll) --> <<include>> (Check Separation of Duties)

(Calculate Payroll) --> <<include>> (Validate Attendance Data)
(Calculate Payroll) --> <<include>> (Apply Overtime Policy)
(Calculate Payroll) --> <<include>> (Apply Leave Rules)
(Calculate Payroll) --> <<include>> (Apply Violation Deduction)

(Lock Work Schedule Period) --> <<extend>> (Approve Work Schedule)
(Override Work Schedule Lock) --> <<extend>> (Lock Work Schedule Period)
(Reject Work Schedule) --> <<extend>> (Submit Work Schedule)
(Reject Leave Request) --> <<extend>> (Submit Leave Request)
(Reject Overtime Request) --> <<extend>> (Submit Overtime Request)
(Submit Violation Dispute) --> <<extend>> (Confirm Violation)
(Waive Violation) --> <<extend>> (Confirm Violation)
(Revoke Session) --> <<extend>> (View User Sessions)
(Lock Account) --> <<extend>> (View User Sessions)
```

---

## 6.3) Goi y chia diagram

Nen tach **6 Use Case Diagram**:

1. **Identity & Access**
   - Actor: Authenticated User, Administrator, Identity Provider
   - UC: dang nhap/dang xuat/refresh/phien/phan quyen.
2. **Master Data & Organization**
   - Actor: Administrator, HR Staff, Operations Staff
   - UC: cong ty/don vi/phong ban/chuc danh/tai xe/phuong tien.
3. **Operations & Billing**
   - Actor: Operations Staff, Customer Service Staff
   - UC: chuyen di, trang thai, khach hang, hoa don.
4. **Workforce Management**
   - Actor: Driver, HR Staff, Approver, Operations Staff
   - UC: lich lam/cham cong/nghi phep/tang ca.
5. **Compliance & Audit**
   - Actor: Operations Staff, Driver, Approver, Auditor
   - UC: vi pham/khieu nai/giam sat thao tac/SoD.
6. **Payroll & Reporting**
   - Actor: Payroll Staff, Driver, Administrator, Auditor
   - UC: tinh luong/duyet/khoa/xuat/report.

---

## 6.4) De xuat bo sung (Optional)

- Them use case: **Reset Password** (tach khoi Sign In domain).
- Them use case: **Receive Notification** cho Driver/Staff.
- Them use case: **Acknowledge Policy Change** khi thay doi quy dinh luong/ca.
- Neu can realtime chuan hon: them use case **Subscribe Live Monitoring** cho Auditor.
