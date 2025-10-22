<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for customer notification system supporting SMS and Email
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Notification Channels
    |--------------------------------------------------------------------------
    |
    | Available channels: 'sms', 'email', 'both'
    | This controls which notification method(s) to use by default
    |
    */
    'default_channels' => env('NOTIFICATION_CHANNELS', 'both'),

    /*
    |--------------------------------------------------------------------------
    | Channel Specific Settings
    |--------------------------------------------------------------------------
    */
    'sms' => [
        'enabled' => env('NOTIFICATION_SMS_ENABLED', true),
        'service' => env('SMS_DRIVER', 'arkesel'),
        'fallback_to_email' => env('NOTIFICATION_SMS_FALLBACK_EMAIL', true),
    ],

    'email' => [
        'enabled' => env('NOTIFICATION_EMAIL_ENABLED', true),
        'from_address' => env('MAIL_FROM_ADDRESS', 'noreply@mikrotik.local'),
        'from_name' => env('MAIL_FROM_NAME', 'MikroTik RADIUS'),
        'fallback_to_sms' => env('NOTIFICATION_EMAIL_FALLBACK_SMS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Type Specific Settings
    |--------------------------------------------------------------------------
    |
    | Override default channels for specific notification types
    |
    */
    'types' => [
        'welcome' => [
            'channels' => env('NOTIFICATION_WELCOME_CHANNELS', null), // null = use default
            'sms_enabled' => env('NOTIFICATION_WELCOME_SMS', true),
            'email_enabled' => env('NOTIFICATION_WELCOME_EMAIL', true),
        ],
        'trial_assignment' => [
            'channels' => env('NOTIFICATION_TRIAL_CHANNELS', null),
            'sms_enabled' => env('NOTIFICATION_TRIAL_SMS', true),
            'email_enabled' => env('NOTIFICATION_TRIAL_EMAIL', true),
        ],
        'setup_instructions' => [
            'channels' => env('NOTIFICATION_SETUP_CHANNELS', null),
            'sms_enabled' => env('NOTIFICATION_SETUP_SMS', true),
            'email_enabled' => env('NOTIFICATION_SETUP_EMAIL', true),
        ],
        'payment_success' => [
            'channels' => env('NOTIFICATION_PAYMENT_SUCCESS_CHANNELS', null),
            'sms_enabled' => env('NOTIFICATION_PAYMENT_SUCCESS_SMS', true),
            'email_enabled' => env('NOTIFICATION_PAYMENT_SUCCESS_EMAIL', true),
        ],
        'subscription_activated' => [
            'channels' => env('NOTIFICATION_SUBSCRIPTION_ACTIVATED_CHANNELS', null),
            'sms_enabled' => env('NOTIFICATION_SUBSCRIPTION_ACTIVATED_SMS', true),
            'email_enabled' => env('NOTIFICATION_SUBSCRIPTION_ACTIVATED_EMAIL', true),
        ],
        'expiration_warning' => [
            'channels' => env('NOTIFICATION_EXPIRATION_WARNING_CHANNELS', null),
            'sms_enabled' => env('NOTIFICATION_EXPIRATION_WARNING_SMS', true),
            'email_enabled' => env('NOTIFICATION_EXPIRATION_WARNING_EMAIL', true),
            'warning_hours' => env('NOTIFICATION_EXPIRATION_WARNING_HOURS', 24), // Hours before expiry
        ],
        'subscription_expired' => [
            'channels' => env('NOTIFICATION_SUBSCRIPTION_EXPIRED_CHANNELS', null),
            'sms_enabled' => env('NOTIFICATION_SUBSCRIPTION_EXPIRED_SMS', true),
            'email_enabled' => env('NOTIFICATION_SUBSCRIPTION_EXPIRED_EMAIL', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry and Fallback Settings
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_attempts' => env('NOTIFICATION_MAX_RETRY_ATTEMPTS', 3),
        'delay_seconds' => env('NOTIFICATION_RETRY_DELAY', 5),
        'exponential_backoff' => env('NOTIFICATION_EXPONENTIAL_BACKOFF', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Settings
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('NOTIFICATION_LOG_ENABLED', true),
        'log_success' => env('NOTIFICATION_LOG_SUCCESS', true),
        'log_failures' => env('NOTIFICATION_LOG_FAILURES', true),
        'log_retries' => env('NOTIFICATION_LOG_RETRIES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        'enabled' => env('NOTIFICATION_RATE_LIMITING_ENABLED', true),
        'max_per_minute' => env('NOTIFICATION_MAX_PER_MINUTE', 60),
        'max_per_hour' => env('NOTIFICATION_MAX_PER_HOUR', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Templates
    |--------------------------------------------------------------------------
    */
    'templates' => [
        'welcome' => [
            'sms' => "Welcome to JayNet WI-FI! Portal Login:\n{username} \nPassword: {password}",
            'email_subject' => 'Welcome to JayNet WI-FI - Account Created',
            'email_body' => 'Welcome to JayNet WI-FI! Your portal account has been created successfully. Portal Login - Username: {username}, Password: {password}. After purchasing a subscription, you will receive a separate 6-digit WiFi token for internet access.',
        ],
        'trial_assignment' => [
            'sms' => 'Free trial package "{package_name}" activated! Valid until {expires_at_human}. Enjoy True Freedom - Unlimited browsing!',
            'email_subject' => 'Trial Package Activated - {package_name}',
            'email_body' => 'Great news! Your trial package "{package_name}" has been activated and is valid until {expires_at_human}. Enjoy True Freedom - Unlimited browsing!',
        ],
        'setup_instructions' => [
            'sms' => 'JayNet WiFi Ready! Connect using - Username: {username} | WiFi Token: {token} | Portal access at: http://jaynet.local.com',
            'email_subject' => 'Your JayNet WiFi Access Details',
            'email_body' => 'Welcome to JayNet WiFi!
                    To get connected, follow these simple steps:

                    1. Connect to the JayNet WiFi network
                    2. Enter your WiFi credentials:
                       • Username: {username}
                       • WiFi Token: {token}

                    3. For account management, visit: http://jaynet.local.com
                       • Portal Username: {username}
                       • Portal Password: {password}

                    Need help? Contact our support team.

                    Best regards,
                    The JayNet Team',
        ],
        'payment_success' => [
            'sms' => 'Payment successful! Amount: {amount} {currency}. Transaction ID: {transaction_id}. Thank you!',
            'email_subject' => 'Payment Confirmation - {amount} {currency}',
            'email_body' => 'Your payment has been processed successfully. Amount: {amount} {currency}, Transaction ID: {transaction_id}. {payment_time?Payment processed {payment_time}.} Thank you for your payment!',
        ],
        'subscription_activated' => [
            'sms' => 'Subscription activated! Package: {package_name}, Valid until: {expires_at_human}. WiFi Token: {token} | Username: {username} Tap: http://jaynet.local.com/logout to access!',
            'email_subject' => 'Subscription Activated - {package_name}',
            'email_body' => 'Your subscription has been activated successfully! Package: {package_name}, Valid until: {expires_at_human}. WiFi Connection Details - Username: {username}, WiFi Token: {token}. Enjoy your service!',
        ],
        'expiration_warning' => [
            'sms' => 'REMINDER: Your subscription expires in {time_remaining_display}. Renew now to avoid interruption. Dial *713*3607# to renew!',
            'email_subject' => 'Subscription Expiring Soon - {package_name}',
            'email_body' => 'REMINDER: Your subscription "{package_name}" will expire in {time_remaining_display} on {expires_at_human}. Please renew your subscription to avoid service interruption.',
        ],
        'subscription_expired' => [
            'sms' => 'Your subscription has expired. Package: {package_name}. Expired on {expired_at_human}. Dial *713*3607# to renew now.',
            'email_subject' => 'Subscription Expired - {package_name}',
            'email_body' => 'Your subscription "{package_name}" expired on {expired_at_human}. Please renew to restore your internet access. Username: {username}.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Emergency Fallback Settings
    |--------------------------------------------------------------------------
    */
    'emergency' => [
        'fallback_to_log' => env('NOTIFICATION_EMERGENCY_LOG_FALLBACK', true),
        'admin_notification' => env('NOTIFICATION_EMERGENCY_ADMIN_NOTIFY', true),
        'admin_email' => env('NOTIFICATION_EMERGENCY_ADMIN_EMAIL', null),
        'admin_phone' => env('NOTIFICATION_EMERGENCY_ADMIN_PHONE', null),
    ],
];
