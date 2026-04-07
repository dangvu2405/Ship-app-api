<?php

/*
|--------------------------------------------------------------------------
| CORS — đồng bộ với ship-app (VITE_API_ORIGIN / dev :3000, :5173, …)
|--------------------------------------------------------------------------
| FRONTEND_URL: một hoặc nhiều origin, cách nhau bởi dấu phẩy (không path /api).
| CORS_ALLOWED_ORIGINS: thêm origin tạm thời (CSV).
| API_URI_PREFIX: khớp config/ship.php (mặc định api → paths api/*).
*/

$apiPrefix = trim((string) env('API_URI_PREFIX', 'api'), '/');

$defaultOrigins = [
    'http://localhost:3000',
    'http://localhost:5173',
    'http://localhost:4173',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:4173',
    'http://localhost',
    'http://127.0.0.1',
];

$fromFrontendUrl = [];
if (filled(env('FRONTEND_URL'))) {
    $fromFrontendUrl = array_map(
        static fn (string $u): string => rtrim(trim($u), '/'),
        array_filter(array_map('trim', explode(',', (string) env('FRONTEND_URL'))))
    );
}

$fromCsv = [];
if (filled(env('CORS_ALLOWED_ORIGINS'))) {
    $fromCsv = array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS'))));
}

$allowedOrigins = array_values(array_unique(array_filter(array_merge(
    $defaultOrigins,
    $fromFrontendUrl,
    $fromCsv
))));

$patterns = [];
if (filled(env('CORS_ALLOWED_ORIGIN_PATTERNS'))) {
    $patterns = array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS'))
    )));
}

return [

    'paths' => [
        $apiPrefix.'/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => $patterns,

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => (int) env('CORS_MAX_AGE', 0),

    'supports_credentials' => filter_var(
        env('CORS_SUPPORTS_CREDENTIALS', true),
        FILTER_VALIDATE_BOOL
    ),

];
