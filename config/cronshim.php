<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Administrator bootstrap account
    |--------------------------------------------------------------------------
    |
    | Used by the AdminUserSeeder to create the first account. Override in
    | the environment and change the password immediately after first login.
    |
    */
    'admin' => [
        'email' => env('CRONSHIM_ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('CRONSHIM_ADMIN_PASSWORD', 'password'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTPS
    |--------------------------------------------------------------------------
    |
    | When true, all generated URLs use the https scheme (set this when TLS is
    | terminated by an upstream proxy and the app cannot detect it directly).
    |
    */
    'force_https' => (bool) env('CRONSHIM_FORCE_HTTPS', false),

    /*
    |--------------------------------------------------------------------------
    | Ingest API
    |--------------------------------------------------------------------------
    |
    | Limits applied when a site's reporting client posts a run report.
    |
    */
    'ingest' => [
        'max_log_entries' => (int) env('CRONSHIM_INGEST_MAX_LOG_ENTRIES', 200),
        'max_log_message_length' => (int) env('CRONSHIM_INGEST_MAX_MESSAGE_LENGTH', 5000),
        'max_body_kb' => (int) env('CRONSHIM_INGEST_MAX_BODY_KB', 512),
        'throttle_per_minute' => (int) env('CRONSHIM_INGEST_THROTTLE', 120),
        'require_signature' => (bool) env('CRONSHIM_INGEST_REQUIRE_SIGNATURE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health evaluation
    |--------------------------------------------------------------------------
    */
    'health' => [
        'failure_threshold' => (int) env('CRONSHIM_FAILURE_THRESHOLD', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'min_interval_minutes' => (int) env('CRONSHIM_NOTIFY_MIN_INTERVAL', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data retention (days)
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'runs_days' => (int) env('CRONSHIM_RETENTION_RUNS_DAYS', 90),
        'logs_days' => (int) env('CRONSHIM_RETENTION_LOGS_DAYS', 30),
        'notifications_days' => (int) env('CRONSHIM_RETENTION_NOTIFICATIONS_DAYS', 90),
    ],

];
