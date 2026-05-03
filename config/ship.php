<?php

return [

    /*
    |--------------------------------------------------------------------------
    | URI API (đồng bộ với ship-app: export const API_PREFIX = '/api')
    |--------------------------------------------------------------------------
    |
    | Laravel đăng ký routes trong routes/api.php với prefix mặc định "api".
    | Đổi API_URI_PREFIX chỉ khi sửa bootstrap/app.php và frontend cùng lúc.
    |
    */
    'api_uri_prefix' => trim((string) env('API_URI_PREFIX', 'api'), '/'),

    'api_base_path' => '/'.trim((string) env('API_URI_PREFIX', 'api'), '/'),

    'attendance' => [
        'late_after' => (string) env('ATTENDANCE_LATE_AFTER', '08:15'),
    ],

    /*
    |--------------------------------------------------------------------------
    | GET /auth/test-accounts (chỉ bật khi cần — xem FRONTEND_PAYLOAD_BY_SCREEN)
    |--------------------------------------------------------------------------
    |
    | Mặc định chỉ đăng ký route khi APP_ENV là local hoặc testing.
    | Bật thêm (vd. staging demo): SHOW_TEST_ACCOUNTS=true
    |
    */
    'expose_test_accounts' => (bool) env('SHOW_TEST_ACCOUNTS', false),

    /*
    |--------------------------------------------------------------------------
    | Default role assigned when admin creates a new user via /auth/register
    |--------------------------------------------------------------------------
    */
    'default_register_role' => env('DEFAULT_REGISTER_ROLE', 'admin'),

    /*
    |--------------------------------------------------------------------------
    | BulkDataSeeder — số bản ghi mỗi “nhóm” bảng (companies, trips, cache, …)
    |--------------------------------------------------------------------------
    |
    | Chạy: php artisan db:seed --class=BulkDataSeeder
    | Ví dụ 20 dòng: BULK_SEED_COUNT=20 php artisan db:seed --class=BulkDataSeeder
    |
    */
    'bulk_seed_count' => max(1, min(5000, (int) env('BULK_SEED_COUNT', 100))),

    /*
    |--------------------------------------------------------------------------
    | SOC2 — audit READ (GET) on sensitive API prefixes
    |--------------------------------------------------------------------------
    |
    | Patterns are passed to preg_match() against the request path (no leading slash).
    |
    */
    'audit_read_path_patterns' => [
        '#^api/drivers(\b|/)#',
        '#^api/users(\b|/)#',
        '#^api/reconciliations(\b|/)#',
    ],

    /*
    |--------------------------------------------------------------------------
    | SOC2 — optional application-layer encryption for PII / salary fields
    |--------------------------------------------------------------------------
    |
    | See docs/SOC2_ENCRYPTION_AND_KMS.md. Keep false until a KMS-backed rollout.
    |
    */
    'encrypt_pii_fields' => (bool) env('SHIP_ENCRYPT_PII', false),

    /*
    |--------------------------------------------------------------------------
    | Áp lịch hàng loạt theo văn phòng (work_schedule_templates → driver_work_schedules)
    |--------------------------------------------------------------------------
    |
    | - max_date_range_days: giới hạn độ dài khoảng [start_date, end_date] (API apply).
    | - sync_max_rows: nếu drivers × ngày > ngưỡng này → chạy ApplyOfficeScheduleJob (queue), tránh timeout HTTP.
    | - insert_chunk_size: kích thước chunk INSERT (một câu lệnh nhiều dòng).
    |
    | Gợi ý: sync_max_rows ≈ 5k–15k tùy DB; production nên QUEUE_CONNECTION=redis/database.
    |
    */
    'office_schedule_apply' => [
        'max_date_range_days' => max(1, min(366, (int) env('OFFICE_SCHEDULE_MAX_RANGE_DAYS', 120))),
        'sync_max_rows' => max(500, min(100000, (int) env('OFFICE_SCHEDULE_SYNC_MAX_ROWS', 8000))),
        'insert_chunk_size' => max(100, min(2000, (int) env('OFFICE_SCHEDULE_INSERT_CHUNK', 400))),
    ],

];
