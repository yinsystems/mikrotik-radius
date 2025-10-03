<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auto-assign Trial Package
    |--------------------------------------------------------------------------
    |
    | When enabled, new customers will automatically be assigned a trial package
    | if one is available and they are eligible.
    |
    */
    'auto_assign_trial' => env('HOTSPOT_AUTO_ASSIGN_TRIAL', true),

    /*
    |--------------------------------------------------------------------------
    | Default Trial Package Duration
    |--------------------------------------------------------------------------
    |
    | Default settings for trial packages if not specified.
    |
    */
    'trial_duration_hours' => env('HOTSPOT_TRIAL_DURATION_HOURS', 2),

    /*
    |--------------------------------------------------------------------------
    | RADIUS Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for RADIUS integration.
    |
    */
    'radius' => [
        'auto_sync' => env('RADIUS_AUTO_SYNC', true),
        'session_timeout_on_suspend' => env('RADIUS_TERMINATE_ON_SUSPEND', true),
        'default_idle_timeout' => env('RADIUS_DEFAULT_IDLE_TIMEOUT', 900), // 15 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Username Generation
    |--------------------------------------------------------------------------
    |
    | Settings for automatic username generation.
    |
    */
    'username' => [
        'use_phone_as_default' => env('USERNAME_USE_PHONE', true),
        'allow_custom_username' => env('USERNAME_ALLOW_CUSTOM', true),
        'min_length' => env('USERNAME_MIN_LENGTH', 4),
        'max_length' => env('USERNAME_MAX_LENGTH', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Generation
    |--------------------------------------------------------------------------
    |
    | Settings for automatic password generation.
    |
    */
    'password' => [
        'default_length' => env('PASSWORD_DEFAULT_LENGTH', 8),
        'min_length' => env('PASSWORD_MIN_LENGTH', 6),
        'max_length' => env('PASSWORD_MAX_LENGTH', 20),
        'include_special_chars' => env('PASSWORD_INCLUDE_SPECIAL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Usage Sync
    |--------------------------------------------------------------------------
    |
    | Settings for syncing data usage from RADIUS accounting.
    |
    */
    'data_usage' => [
        'sync_interval_minutes' => env('DATA_USAGE_SYNC_INTERVAL', 15),
        'auto_suspend_on_limit' => env('DATA_USAGE_AUTO_SUSPEND', true),
    ],
];