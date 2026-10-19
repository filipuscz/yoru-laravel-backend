<?php

return [

    'queue_monitor' => [
        'connection' => env('QUEUE_MONITOR_CONNECTION', env('QUEUE_CONNECTION', 'database')),
        'queues' => env('QUEUE_MONITOR_QUEUES', 'default,notifications'),
        'max_jobs' => (int) env('QUEUE_MONITOR_MAX_JOBS', 100),
    ],

    'database_backup' => [
        'path' => env('DB_BACKUP_PATH', storage_path('app/backups/latest.dump')),
        'max_age_hours' => (int) env('DB_BACKUP_MAX_AGE_HOURS', 24),
    ],

];
