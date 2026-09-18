<?php

return [
    'field_sales_push' => [
        'endpoint' => env('FIELD_SALES_PUSH_ENDPOINT'),
        'secret' => env('FIELD_SALES_PUSH_SECRET'),
    ],

    'businessos' => [
        'base_url' => env('BUSINESSOS_BASE_URL'),
        'token' => env('BUSINESSOS_TOKEN'),
        'timeout' => (int) env('BUSINESSOS_TIMEOUT', 10),
    ],
];
