# SOC 2 — Mã hóa PII / lương & quản lý khóa (KMS)

## Mục tiêu

- Giảm phơi nhiễm PII trong DB-at-rest (SĐT, CCCD, STK, số BHXH/YT…).
- Tách **master key** khỏi repo; dùng **KMS** (AWS KMS / GCP KMS / Vault Transit) để bọc **DEK** (data encryption key) theo kiểu **envelope encryption**.

## Trạng thái triển khai trong code

- `config/ship.php` → `encrypt_pii_fields` (env `SHIP_ENCRYPT_PII`, mặc định `false`).
- `App\Models\Driver::casts()` bật cast `encrypted` cho một số cột PII **chỉ khi** `SHIP_ENCRYPT_PII=true`.
- **Không** bật trên môi trường đang có dữ liệu plaintext cho đến khi chạy job re-encrypt (xem dưới).

## Envelope (mô tả vận hành)

1. Sinh **DEK** ngẫu nhiên (AES-256) trong app hoặc KMS `GenerateDataKey`.
2. Mã hóa payload bằng DEK.
3. Gọi KMS `Encrypt` để bọc DEK → **wrapped_dek** lưu kèm ciphertext (hoặc lưu DEK đã bọc trong meta JSON).
4. Master key **không** nằm trong `.env` production; chỉ có quyền IAM/role tới KMS.

Laravel cast `encrypted` dùng `APP_KEY` làm khóa ứng dụng — phù hợp **dev/staging**; production SOC2 nên thay bằng custom cast gọi KMS (mở rộng sau).

## Luân chuyển / rotate khóa (quy trình)

1. Tạo KMS key/alias mới (ví dụ `alias/ship-pii-v2`).
2. Triển khai biến môi trường trỏ alias mới (song song giữ alias cũ để đọc).
3. Chạy job batch (ví dụ `php artisan ship:encrypt-rotate` — **stub tài liệu**, implement sau):
   - Đọc từng row, decrypt bằng DEK cũ (unwrap qua KMS alias cũ).
   - Re-encrypt bằng DEK mới (unwrap/wrap alias mới).
   - Ghi `key_version` / prefix version trên ciphertext.
4. Verify mẫu + monitor lỗi decrypt.
5. Thu hồi quyền unwrap trên alias cũ sau TTL.

## Blind index (tìm kiếm equality)

Mã hóa full-field làm mất index equality. Nếu nghiệp vụ bắt buộc tra cứu theo SĐT/CCCD:

- Lưu thêm cột `phone_lookup_hmac` = HMAC-SHA256(phone, server_pepper) với **pepper** lấy từ KMS/secret manager.
- Tra cứu theo HMAC, không lưu plaintext index.

Trade-off: phải quản lý pepper rotation đồng bộ với blind index rebuild.

## Liên quan

- [`SOC2_DATABASE_STRATEGY.md`](./SOC2_DATABASE_STRATEGY.md)
- [`SOC2_CONTROL_MAPPING.md`](./SOC2_CONTROL_MAPPING.md)
