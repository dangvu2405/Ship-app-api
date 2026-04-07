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

];
