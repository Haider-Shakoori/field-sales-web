<?php

return [
    'whatsapp' => [
        'enabled' => (bool) env('WHATSAPP_MESSAGES_ENABLED', false),
        'endpoint' => env('WHATSAPP_MESSAGES_ENDPOINT'),
        'bearer_token' => env('WHATSAPP_MESSAGES_BEARER_TOKEN'),
        'provider' => env('WHATSAPP_MESSAGES_PROVIDER', 'generic_http'),
        'timeout_seconds' => (int) env('WHATSAPP_MESSAGES_TIMEOUT', 10),
    ],
    'sms' => [
        'enabled' => (bool) env('SMS_MESSAGES_ENABLED', false),
        'endpoint' => env('SMS_MESSAGES_ENDPOINT'),
        'bearer_token' => env('SMS_MESSAGES_BEARER_TOKEN'),
        'provider' => env('SMS_MESSAGES_PROVIDER', 'generic_http'),
        'timeout_seconds' => (int) env('SMS_MESSAGES_TIMEOUT', 10),
    ],
];
