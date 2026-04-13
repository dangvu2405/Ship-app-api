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
        '#^api/(v1/)?drivers(\b|/)#',
        '#^api/(v1/)?payrolls(\b|/)#',
        '#^api/(v1/)?positions(\b|/)#',
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

];
