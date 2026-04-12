<?php

declare(strict_types=1);

return [
    'app_id' => env('LARK_APP_ID'),
    'app_secret' => env('LARK_APP_SECRET'),
    'verification_token' => env('LARK_VERIFICATION_TOKEN'),
    'encrypt_key' => env('LARK_ENCRYPT_KEY'),
    'signing_secret' => env('LARK_SIGNING_SECRET'),

    'base' => [
        'app_token' => env('LARK_BASE_APP_TOKEN'),
        'employees_table_id' => env('LARK_BASE_EMPLOYEES_TABLE_ID'),
        'trips_table_id' => env('LARK_BASE_TRIPS_TABLE_ID'),
        'enable_reverse_sync' => (bool) env('LARK_BASE_ENABLE_REVERSE_SYNC', false),
    ],

    'chat_ids' => [
        'ops' => env('LARK_CHAT_ID_OPS'),
        'hr' => env('LARK_CHAT_ID_HR'),
        'fleet' => env('LARK_CHAT_ID_FLEET'),
        'payroll' => env('LARK_CHAT_ID_PAYROLL'),
    ],

    'security' => [
        'max_request_age_seconds' => (int) env('LARK_MAX_REQUEST_AGE_SECONDS', 300),
    ],
];
