<?php

return [
    'tracking' => [
        'max_batch_points' => (int) env('FIELD_SALES_MAX_BATCH_POINTS', 100),
        'map_track_bucket_seconds' => (int) env('FIELD_SALES_MAP_TRACK_BUCKET_SECONDS', 300),
    ],

    'mobile' => [
        'minimum_version' => env('FIELD_SALES_MINIMUM_APP_VERSION', '1.0'),
        'upgrade_url' => env('FIELD_SALES_UPGRADE_URL'),
    ],

    'defaults' => [
        'work_session_start_mode' => 'manual',
        'workday_start_time' => '08:00',
        'workday_end_time' => '17:00',
        'auto_end_session' => false,
        'gps_tracking_enabled' => true,
        'gps_moving_interval_seconds' => 15,
        'gps_stationary_interval_seconds' => 60,
        'gps_stale_after_minutes' => 15,
        'privacy_policy_version' => '1',
    ],
];
