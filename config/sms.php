<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMS Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for SMS messaging service with support for multiple drivers
    |
    */

    'driver' => env('SMS_DRIVER', 'arkesel'),

    /*
    |--------------------------------------------------------------------------
    | Arkesel SMS Configuration
    |--------------------------------------------------------------------------
    */
    'arkesel' => [
        'api_key' => env('ARKESEL_API_KEY', ''),
        'api_url' => env('ARKESEL_API_URL', 'https://sms.arkesel.com/sms/api'),
        'sender_id' => env('ARKESEL_SENDER_ID', 'MikroTik'),
        'timeout' => env('ARKESEL_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hubtel SMS Configuration
    |--------------------------------------------------------------------------
    */
    'hubtel' => [
        'api_client_id' => env('HUBTEL_SMS_CLIENT_ID', ''),
        'api_client_secret' => env('HUBTEL_SMS_CLIENT_SECRET', ''),
        'api_url' => env('HUBTEL_SMS_API_URL', 'https://sms.vasgh.com/v1/messages/send'),
        'sender_id' => env('HUBTEL_SMS_SENDER_ID', 'MikroTik'),
        'timeout' => env('HUBTEL_SMS_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Settings
    |--------------------------------------------------------------------------
    */
    'enabled' => env('SMS_ENABLED', true),
    'log_messages' => env('SMS_LOG_MESSAGES', true),
    'max_length' => env('SMS_MAX_LENGTH', 160),

    /*
    |--------------------------------------------------------------------------
    | Default Country Code
    |--------------------------------------------------------------------------
    */
    'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE', '+233'), // Ghana
];
