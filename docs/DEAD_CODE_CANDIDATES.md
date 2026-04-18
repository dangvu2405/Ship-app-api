# Dead code candidates (Phase 7 — review before delete)

Các file **không** thấy được đăng ký trong `routes/api.php` hoặc tham chiếu rõ ràng từ route/console. Xóa chỉ sau khi xác nhận không dùng cho OpenAPI/demo nội bộ.

| File | Ghi chú |
|------|---------|
| [app/Http/Controllers/Api/ExampleController.php](../app/Http/Controllers/Api/ExampleController.php) | Không có route trỏ tới; có thể là mẫu Swagger/OpenAPI — xóa nếu không còn dùng. |

Sau khi merge refactor, chạy lại: `rg ExampleController` trong toàn repo trước khi xóa.
