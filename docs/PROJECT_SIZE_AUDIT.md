# Project Size Audit (2026-04-08)

## Snapshot

- Tổng dung lượng trước tối ưu: **~107MB**
- Tổng dung lượng sau tối ưu local cache/log: **~105MB**

## Top thư mục chiếm dung lượng

1. `vendor` ~96MB
2. `.git` ~5.6MB
3. `storage` trước ~2.7MB (sau cleanup còn ~360KB)

## File lớn đáng chú ý (ngoài dependencies)

- `storage/logs/laravel.log` ~2.3MB (đã truncate)

## Hành động đã áp dụng

1. Thêm `.dockerignore` để giảm build context Docker
   - Loại trừ: `.git`, `vendor`, `node_modules`, `storage/logs`, `tests`, `docs`, cache files...
2. Thêm script dọn dung lượng local:
   - `scripts/optimize_project_size.sh`
   - Thực hiện: `optimize:clear`, truncate logs, xóa cache artifacts.

## Lợi ích

- Build Docker nhanh hơn do context nhỏ hơn.
- Giảm rủi ro image phình to do copy file không cần thiết.
- Giảm nhiễu repository local khi logs/cache tăng mạnh.

## Khuyến nghị thêm (không phá kiến trúc)

- Dev local:
  - Chạy định kỳ `./scripts/optimize_project_size.sh`
- CI/Prod image:
  - Dùng `composer install --no-dev --optimize-autoloader`
  - Tránh mount toàn bộ source trong production runtime.
- Git hygiene:
  - Không commit artifacts sinh tự động (logs/cache/coverage).
