<?php

return [
    'enabled' => (bool) env('PUSH_NOTIFICATIONS_ENABLED', false),
    'endpoint' => env('PUSH_NOTIFICATIONS_ENDPOINT'),
    'bearer_token' => env('PUSH_NOTIFICATIONS_BEARER_TOKEN'),
    'timeout_seconds' => (int) env('PUSH_NOTIFICATIONS_TIMEOUT', 10),
];
