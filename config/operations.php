<?php

return [
    'backup' => [
        'directory' => env('FIELD_SALES_BACKUP_DIR', storage_path('app/backups')),
        'retention_days' => (int) env('FIELD_SALES_BACKUP_RETENTION_DAYS', 14),
    ],

    'monitoring' => [
        'max_failed_jobs' => (int) env('FIELD_SALES_MONITOR_MAX_FAILED_JOBS', 10),
        'max_queued_jobs' => (int) env('FIELD_SALES_MONITOR_MAX_QUEUED_JOBS', 1000),
        'max_oldest_job_seconds' => (int) env('FIELD_SALES_MONITOR_MAX_OLDEST_JOB_SECONDS', 600),
    ],
];
