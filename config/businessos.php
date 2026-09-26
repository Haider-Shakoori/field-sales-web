<?php

return [
    'enabled' => (bool) env('BUSINESSOS_INTEGRATION_ENABLED', false),
    'base_url' => env('BUSINESSOS_INTEGRATION_BASE_URL'),
    'token' => env('BUSINESSOS_INTEGRATION_TOKEN'),
    'timeout_seconds' => (int) env('BUSINESSOS_INTEGRATION_TIMEOUT', 20),

    'paths' => [
        'health' => env(
            'BUSINESSOS_INTEGRATION_HEALTH_PATH',
            '/api/fieldpulse/v1/health',
        ),
        'master_data' => env(
            'BUSINESSOS_INTEGRATION_MASTER_DATA_PATH',
            '/api/fieldpulse/v1/master-data',
        ),
        'events' => env(
            'BUSINESSOS_INTEGRATION_EVENTS_PATH',
            '/api/fieldpulse/v1/events',
        ),
    ],
];
