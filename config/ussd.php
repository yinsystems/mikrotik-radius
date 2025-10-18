<?php

return [

    /*
    |--------------------------------------------------------------------------
    | USSD Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for USSD WiFi portal functionality
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Package Pagination
    |--------------------------------------------------------------------------
    |
    | How many packages to display per page in USSD menu
    | Recommended: 3-4 packages per page for better readability
    |
    */
    'packages_per_page' => env('USSD_PACKAGES_PER_PAGE', 3),

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    */
    'session_timeout' => env('USSD_SESSION_TIMEOUT', 600), // 10 minutes

    /*
    |--------------------------------------------------------------------------
    | USSD Display Limits
    |--------------------------------------------------------------------------
    |
    | USSD has character limits per message (typically 160-182 characters)
    | These settings help optimize display for different networks
    |
    */
    'max_message_length' => env('USSD_MAX_MESSAGE_LENGTH', 160),
    'max_menu_items' => env('USSD_MAX_MENU_ITEMS', 5),

    /*
    |--------------------------------------------------------------------------
    | WiFi Portal Settings
    |--------------------------------------------------------------------------
    */
    'wifi_portal_url' => env('WIFI_PORTAL_URL', 'wifi.portal.com'),
    'wifi_ssid' => env('WIFI_SSID', 'WiFi-Portal'),

];