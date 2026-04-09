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
    | BulkDataSeeder — số bản ghi mỗi “nhóm” bảng (companies, trips, cache, …)
    |--------------------------------------------------------------------------
    |
    | Chạy: php artisan db:seed --class=BulkDataSeeder
    | Ví dụ 20 dòng: BULK_SEED_COUNT=20 php artisan db:seed --class=BulkDataSeeder
    |
    */
    'bulk_seed_count' => max(1, min(5000, (int) env('BULK_SEED_COUNT', 100))),

];
