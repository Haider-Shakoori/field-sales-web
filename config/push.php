<?php

return [
    'enabled' => (bool) env('PUSH_NOTIFICATIONS_ENABLED', false),
    'provider' => env('PUSH_NOTIFICATIONS_PROVIDER', 'fcm_v1'),
    'endpoint' => env('PUSH_NOTIFICATIONS_ENDPOINT'),
    'bearer_token' => env('PUSH_NOTIFICATIONS_BEARER_TOKEN'),
    'timeout_seconds' => (int) env('PUSH_NOTIFICATIONS_TIMEOUT', 10),

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'service_account_json' => env('FCM_SERVICE_ACCOUNT_JSON'),
        'service_account_path' => env('FCM_SERVICE_ACCOUNT_PATH'),
    ],
];
