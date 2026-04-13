# Backend — Sửa đúng chỗ: migration & schema

Khi gặp **lỗi 500** kiểu “Unknown column”, “Base table or view not found”, “SQLSTATE”, integrity constraint… trên môi trường đã deploy, **ưu tiên đối chiếu schema với code** (migrations), **không** dùng frontend để che hoặc bỏ qua lỗi DB.

## Trong thư mục `ship-app-api`

1. **Chạy migration** trên đúng connection môi trường đó:
   ```bash
   php artisan migrate
   ```
   Trên CI/CD: bước deploy tương đương (`php artisan migrate --force` khi production).

2. **Nếu migration lỗi** (conflict version, thứ tự migration, khác engine SQLite vs MySQL):
   - Đọc **full log** `php artisan migrate` (hoặc output pipeline).
   - Xử lý **ở backend**: sửa migration / dữ liệu / thứ tự / tách migration an toàn — **không** sửa frontend chỉ để API không báo lỗi khi DB vẫn sai.

3. **Sau khi schema khớp code**, các lỗi 500 do thiếu cột/bảng thường **hết**. Nếu vẫn còn 500, tiếp tục rà:
   - migration nào **chưa** chạy trên env đó;
   - `.env` `DB_*` có trỏ đúng database không;
   - có nhánh code mới dùng cột/chưa merge migration tương ứng.

## SQLite vs MySQL

- **Local / CI test** thường dùng SQLite (`phpunit.xml`).
- **Docker / production** trong repo này dùng **MySQL** (`docker-compose.yml`).

Đừng giả định migration chạy giống hệt trên mọi engine; nếu migration có nhánh theo driver, kiểm tra log trên **đúng** engine của env đang lỗi.

## Liên quan

- [FRONTEND_SOC2_BACKEND_CHANGES.md](./FRONTEND_SOC2_BACKEND_CHANGES.md) — thay đổi API/tenant cho frontend (không thay thế bước migrate).
- [SOC2_DATABASE_STRATEGY.md](./SOC2_DATABASE_STRATEGY.md) — chiến lược DB / tenant.
