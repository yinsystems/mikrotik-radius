<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MikroTik Configuration
    |--------------------------------------------------------------------------
    |
    | Configure MikroTik router settings for different environments.
    | You can have separate configurations for development and production.
    |
    */

    'default' => env('MIKROTIK_ENVIRONMENT', 'local'),

    'connections' => [
        'local' => [
            'host' => env('MIKROTIK_LOCAL_HOST', '192.168.1.1'),
            'user' => env('MIKROTIK_LOCAL_USER', 'admin'),
            'password' => env('MIKROTIK_LOCAL_PASSWORD', ''),
            'api_port' => (int) env('MIKROTIK_LOCAL_API_PORT', 8728),
            'timeout' => (int) env('MIKROTIK_LOCAL_TIMEOUT', 10),
            'ssl' => env('MIKROTIK_LOCAL_SSL', false),
            'hotspot_server' => env('MIKROTIK_LOCAL_HOTSPOT_SERVER', 'hotspot1'),
            'interface' => env('MIKROTIK_LOCAL_INTERFACE', 'wlan1'),
            'connection_type' => 'ethernet',
            'description' => 'Local network access via Ethernet',
        ],

    ],
];
