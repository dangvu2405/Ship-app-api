# Chuẩn hồ sơ User/Employee/Driver và Form báo cáo

## 1) Phân tích thiếu sót trước khi bổ sung

### 1.1 User (tài khoản đăng nhập)
- Đã có: `username`, `email`, `employee_id`, `status`, `last_login_at`.
- Thiếu trước đây:
  - Ảnh đại diện tài khoản.
  - Thông tin liên hệ khẩn cấp.
  - Địa chỉ cư trú riêng của tài khoản.

### 1.2 Employee (nhân viên)
- Đã có: thông tin cơ bản (`name`, `phone`, `dob`, `gender`, `address`), tổ chức (`office_id`, `department_id`, `position_id`), trạng thái làm việc.
- Thiếu trước đây:
  - Ảnh chân dung hồ sơ.
  - Số định danh và thông tin cấp giấy tờ.
  - Số BHXH/BHYT và ngày đăng ký bảo hiểm.
  - Thông tin ngân hàng nhận lương.

### 1.3 Driver (hồ sơ tài xế)
- Đã có: `license_no`, `license_class`, `expired_date`, `available_status`.
- Thiếu trước đây:
  - Ảnh bằng lái, ảnh giấy tờ định danh.
  - Thông tin bảo hiểm nghề nghiệp tài xế.
  - Số/chứng chỉ sức khỏe và hạn.

---

## 2) Bộ trường đã bổ sung (code đã cập nhật)

### 2.1 Bảng `users`
- `avatar_url`
- `emergency_contact_name`
- `emergency_contact_phone`
- `residential_address`

### 2.2 Bảng `employees`
- `avatar_url`
- `national_id_no`
- `national_id_issue_date`
- `national_id_issue_place`
- `social_insurance_no`
- `health_insurance_no`
- `insurance_registered_at`
- `bank_name`
- `bank_account_no`
- `bank_account_name`

### 2.3 Bảng `drivers`
- `license_image_url`
- `identity_image_url`
- `driver_insurance_no`
- `driver_insurance_expired_date`
- `health_certificate_no`
- `health_certificate_expired_date`

---

## 3) Form chuẩn cho báo cáo hồ sơ (khuyến nghị dùng thống nhất)

## 3.1 JSON schema xuất báo cáo hồ sơ nhân sự

```json
{
  "report_type": "employee_profile",
  "generated_at": "2026-04-09T15:30:00Z",
  "generated_by": {
    "id": 1,
    "username": "admin"
  },
  "filters": {
    "office_id": 1,
    "department_id": null,
    "type": "driver",
    "status": "active"
  },
  "items": [
    {
      "employee": {
        "id": 1,
        "code": "EMP001",
        "name": "Nguyen Van A",
        "avatar_url": "https://cdn.example.com/avatars/emp001.jpg",
        "dob": "1990-01-01",
        "gender": "male",
        "phone": "0912345678",
        "email": "a@example.com",
        "address": "Ha Noi",
        "national_id_no": "012345678901",
        "national_id_issue_date": "2018-01-01",
        "national_id_issue_place": "Ha Noi",
        "social_insurance_no": "BHXH123456",
        "health_insurance_no": "BHYT123456",
        "insurance_registered_at": "2022-01-01",
        "bank_name": "Vietcombank",
        "bank_account_no": "001122334455",
        "bank_account_name": "NGUYEN VAN A"
      },
      "user": {
        "id": 10,
        "username": "user001",
        "email": "user001@example.com",
        "avatar_url": "https://cdn.example.com/users/u001.jpg",
        "status": "active",
        "emergency_contact_name": "Tran Thi B",
        "emergency_contact_phone": "0987654321",
        "residential_address": "Ha Noi"
      },
      "driver": {
        "license_no": "B2-123456",
        "license_class": "B2",
        "expired_date": "2028-12-31",
        "license_image_url": "https://cdn.example.com/license/b2-123456.jpg",
        "identity_image_url": "https://cdn.example.com/id/012345678901.jpg",
        "driver_insurance_no": "DRV-INS-0001",
        "driver_insurance_expired_date": "2027-12-31",
        "health_certificate_no": "HC-0001",
        "health_certificate_expired_date": "2026-10-31",
        "available_status": "available"
      }
    }
  ]
}
```

## 3.2 Cột chuẩn khi xuất Excel/CSV báo cáo hồ sơ

- `employee_code`
- `employee_name`
- `employee_type`
- `office_name`
- `department_name`
- `position_name`
- `avatar_url`
- `national_id_no`
- `social_insurance_no`
- `health_insurance_no`
- `insurance_registered_at`
- `license_no`
- `license_expired_date`
- `driver_insurance_no`
- `driver_insurance_expired_date`
- `health_certificate_no`
- `health_certificate_expired_date`
- `user_username`
- `user_email`
- `emergency_contact_name`
- `emergency_contact_phone`
- `status`

---

## 4) Danh sách trường có thể mở rộng thêm (chưa thêm vào DB)

- `citizen_id_front_image_url`, `citizen_id_back_image_url`.
- `tax_identification_no` (MST cá nhân).
- `insurance_contribution_rate` (tỷ lệ đóng BH theo hợp đồng).
- `probation_end_date`, `contract_type`, `contract_end_date`.
- `avatar_storage_disk`, `avatar_uploaded_at` nếu quản lý upload nội bộ.

> Lưu ý: chỉ thêm khi có use-case rõ ràng để tránh phình schema và tăng chi phí bảo trì.
