<?php

namespace App\Services;

use App\Services\SmsService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class NotificationService
{
    protected SmsService $smsService;
    protected array $config;
    protected bool $smsEnabled;
    protected bool $emailEnabled;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
        $this->config = config('notification');
        $this->smsEnabled = $this->config['sms']['enabled'] ?? true;
        $this->emailEnabled = $this->config['email']['enabled'] ?? true;
    }

    /**
     * Send notification using configured channels
     */
    public function send(
        string $type,
        array $recipient,
        array $data = [],
        ?array $customChannels = null
    ): array {
        try {
            // Check rate limiting
            if ($this->isRateLimited($recipient)) {
                return $this->buildErrorResponse('Rate limit exceeded for recipient');
            }

            // Determine which channels to use
            $channels = $this->determineChannels($type, $customChannels);

            if (empty($channels)) {
                return $this->buildErrorResponse('No notification channels enabled');
            }

            // Validate recipient data
            $validationResult = $this->validateRecipient($recipient, $channels);
            if (!$validationResult['valid']) {
                return $this->buildErrorResponse($validationResult['message']);
            }

            // Prepare message content
            $messages = $this->prepareMessages($type, $data);

            // Send via each channel
            $results = [];
            $anySuccess = false;

            foreach ($channels as $channel) {
                $result = $this->sendViaChannel($channel, $recipient, $messages, $type);
                $results[$channel] = $result;

                if ($result['success']) {
                    $anySuccess = true;
                    // If one channel succeeds, we can consider it successful
                    // unless both are required
                }
            }

            // Handle fallbacks if primary channels failed
            if (!$anySuccess) {
                $fallbackResult = $this->handleFallbacks($type, $recipient, $messages, $results);
                if ($fallbackResult['attempted']) {
                    $results['fallback'] = $fallbackResult;
                    $anySuccess = $fallbackResult['success'];
                }
            }

            // Log the overall result
            $this->logNotification($type, $recipient, $results, $anySuccess);

            return [
                'success' => $anySuccess,
                'type' => $type,
                'channels_attempted' => array_keys($results),
                'results' => $results,
                'message' => $anySuccess ? 'Notification sent successfully' : 'All notification channels failed'
            ];

        } catch (\Exception $e) {
            Log::error('NotificationService::send failed', [
                'type' => $type,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->buildErrorResponse('Notification system error: ' . $e->getMessage());
        }
    }

    /**
     * Send welcome notification with account details
     */
    public function sendWelcome(array $customer, array $credentials): array
    {
        return $this->send('welcome', [
            'name' => $customer['name'],
            'email' => $customer['email'] ?? null,
            'phone' => $customer['phone'] ?? null,
        ], [
            'customer_name' => $customer['name'],
            'username' => $credentials['username'],
            'password' => $credentials['password'],
        ]);
    }

    /**
     * Send trial package assignment notification
     */
    public function sendTrialAssignment(array $customer, array $package): array
    {
        // Humanize the expiration date/time
        $expiresAtHuman = $this->humanizeDateTime($package['expires_at']);
        
        return $this->send('trial_assignment', [
            'name' => $customer['name'],
            'email' => $customer['email'] ?? null,
            'phone' => $customer['phone'] ?? null,
        ], [
            'customer_name' => $customer['name'],
            'package_name' => $package['name'],
            'expires_at' => $package['expires_at'],
            'expires_at_human' => $expiresAtHuman,
        ]);
    }

    /**
     * Send setup instructions
     */
    public function sendSetupInstructions(array $customer, array $credentials): array
    {
        return $this->send('setup_instructions', [
            'name' => $customer['name'],
            'email' => $customer['email'] ?? null,
            'phone' => $customer['phone'] ?? null,
        ], [
            'customer_name' => $customer['name'],
            'token' => $customer['internet_token'],
            'username' => $credentials['username'],
            'password' => $credentials['password'],
        ]);
    }

    /**
     * Send payment success confirmation
     */
    public function sendPaymentSuccess(array $customer, array $payment): array
    {
        $paymentTime = isset($payment['created_at']) ? $this->humanizeDateTime($payment['created_at']) : null;
        
        $variables = [
            'customer_name' => $customer['name'],
            'amount' => $payment['amount'],
            'currency' => $payment['currency'],
            'transaction_id' => $payment['transaction_id'],
        ];

        if ($paymentTime) {
            $variables['payment_time'] = $paymentTime;
        }

        return $this->send('payment_success', [
            'name' => $customer['name'],
            'email' => $customer['email'] ?? null,
            'phone' => $customer['phone'] ?? null,
        ], $variables);
    }

    /**
     * Send subscription activated notification
     */
    public function sendSubscriptionActivated(array $customer, array $subscription): array
    {
        // Humanize the expiration date/time
        $expiresAtHuman = $this->humanizeDateTime($subscription['expires_at']);
        
        return $this->send('subscription_activated', [
            'name' => $customer['name'],
            'email' => $customer['email'] ?? null,
            'phone' => $customer['phone'] ?? null,
        ], [
            // Prefer token provided in the subscription payload (e.g., controller),
            // fall back to customer array if present
            'token' => $subscription['token'] ?? ($customer['internet_token'] ?? null),
            'customer_name' => $customer['name'],
            'package_name' => $subscription['package_name'],
            'expires_at' => $subscription['expires_at'],
            'expires_at_human' => $expiresAtHuman,
            'username' => $subscription['username'],
        ]);
    }

    /**
     * Send expiration warning
     */
    public function sendExpirationWarning(array $customer, array $subscription): array
    {
        $hoursRemaining = $subscription['hours_remaining'] ?? 24;
        $minutesRemaining = $subscription['minutes_remaining'] ?? ($hoursRemaining * 60);
        $durationType = $subscription['duration_type'] ?? 'hourly';

        // Determine the appropriate time display based on duration type and remaining time
        $timeDisplay = $this->formatTimeRemaining($durationType, $hoursRemaining, $minutesRemaining);
        
        // Humanize the expiration date/time
        $expiresAtHuman = $this->humanizeDateTime($subscription['expires_at']);

        return $this->send('expiration_warning', [
            'name' => $customer['name'],
            'email' => $customer['email'] ?? null,
            'phone' => $customer['phone'] ?? null,
        ], [
            'customer_name' => $customer['name'],
            'package_name' => $subscription['package_name'],
            'expires_at' => $subscription['expires_at'],
            'expires_at_human' => $expiresAtHuman,
            'hours_remaining' => $hoursRemaining,
            'minutes_remaining' => $minutesRemaining,
            'time_remaining_display' => $timeDisplay,
            'duration_type' => $durationType,
        ]);
    }

    /**
     * Format time remaining display based on duration type with improved humanization
     */
    protected function formatTimeRemaining(string $durationType, int $hoursRemaining, int $minutesRemaining): string
    {
        // Handle expired or immediate expiration
        if ($minutesRemaining <= 0) {
            return 'expired';
        }

        // For very short durations (less than 2 minutes), be precise
        if ($minutesRemaining < 2) {
            return $minutesRemaining == 1 ? '1 minute' : 'less than 1 minute';
        }

        // For minute-based packages or short durations (less than 2 hours)
        if ($durationType === 'minutely' || $minutesRemaining < 120) {
            if ($minutesRemaining < 60) {
                return $minutesRemaining . ' minutes';
            } else {
                // Show hours and minutes for clarity
                $hours = intval($minutesRemaining / 60);
                $mins = $minutesRemaining % 60;
                
                if ($mins == 0) {
                    return $hours == 1 ? '1 hour' : $hours . ' hours';
                } else {
                    return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' and ' . $mins . ' minute' . ($mins > 1 ? 's' : '');
                }
            }
        }

        // For longer durations, show in days/hours format
        if ($hoursRemaining >= 24) {
            $days = intval($hoursRemaining / 24);
            $remainingHours = $hoursRemaining % 24;
            
            if ($remainingHours == 0) {
                return $days == 1 ? '1 day' : $days . ' days';
            } else {
                return $days . ' day' . ($days > 1 ? 's' : '') . ' and ' . $remainingHours . ' hour' . ($remainingHours > 1 ? 's' : '');
            }
        }

        // For hour-based durations
        if ($hoursRemaining <= 0) {
            return 'less than 1 hour';
        } elseif ($hoursRemaining == 1) {
            return '1 hour';
        } else {
            return $hoursRemaining . ' hours';
        }
    }

    /**
     * Humanize date/time strings for better readability
     */
    protected function humanizeDateTime($dateTime): string
    {
        if (empty($dateTime)) {
            return 'Unknown';
        }

        try {
            $date = \Carbon\Carbon::parse($dateTime);
            $now = \Carbon\Carbon::now();
            
            // If it's today, show time with "today"
            if ($date->isToday()) {
                return 'today at ' . $date->format('g:i A');
            }
            
            // If it's tomorrow, show "tomorrow"
            if ($date->isTomorrow()) {
                return 'tomorrow at ' . $date->format('g:i A');
            }
            
            // If it's yesterday, show "yesterday"
            if ($date->isYesterday()) {
                return 'yesterday at ' . $date->format('g:i A');
            }
            
            // If it's within this week, show day name
            if ($date->isCurrentWeek()) {
                return $date->format('l \a\t g:i A'); // e.g., "Monday at 3:30 PM"
            }
            
            // If it's within this year, show month and day
            if ($date->isCurrentYear()) {
                return $date->format('M j \a\t g:i A'); // e.g., "Oct 25 at 3:30 PM"
            }
            
            // For dates in other years, show full date
            return $date->format('M j, Y \a\t g:i A'); // e.g., "Oct 25, 2024 at 3:30 PM"
            
        } catch (\Exception $e) {
            // Fallback to original string if parsing fails
            return $dateTime;
        }
    }

    /**
     * Send SMS directly (public interface for OTP service)
     */
    public function sendSmsNotification(string $phone, string $message): array
    {
        try {
            // Validate inputs
            if (empty($phone)) {
                return ['success' => false, 'message' => 'Phone number is required'];
            }

            if (empty($message)) {
                return ['success' => false, 'message' => 'Message is required'];
            }

            // Prepare recipient array for the protected method
            $recipient = ['phone' => $phone];

            // Send SMS using the existing protected method
            return $this->sendSms($recipient, $message);

        } catch (\Exception $e) {
            Log::error('SMS sending failed', [
                'phone' => $phone,
                'message' => $message,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'message' => 'Failed to send SMS: ' . $e->getMessage()];
        }
    }

    /**
     * Determine which channels to use for notification
     */
    protected function determineChannels(string $type, ?array $customChannels = null): array
    {
        // Use custom channels if provided
        if ($customChannels) {
            return array_intersect($customChannels, ['sms', 'email']);
        }

        // Check type-specific settings
        $typeConfig = $this->config['types'][$type] ?? [];

        $channels = [];

        // Check if SMS is enabled for this type
        if (($typeConfig['sms_enabled'] ?? true) && $this->smsEnabled) {
            $channels[] = 'sms';
        }

        // Check if email is enabled for this type
        if (($typeConfig['email_enabled'] ?? true) && $this->emailEnabled) {
            $channels[] = 'email';
        }

        // Fallback to default channels if type-specific not configured
        if (empty($channels)) {
            $defaultChannels = $this->config['default_channels'];
            switch ($defaultChannels) {
                case 'sms':
                    $channels = $this->smsEnabled ? ['sms'] : [];
                    break;
                case 'email':
                    $channels = $this->emailEnabled ? ['email'] : [];
                    break;
                case 'both':
                default:
                    $channels = array_filter([
                        $this->smsEnabled ? 'sms' : null,
                        $this->emailEnabled ? 'email' : null
                    ]);
                    break;
            }
        }

        return $channels;
    }

    /**
     * Validate recipient data
     */
    protected function validateRecipient(array $recipient, array $channels): array
    {
        $errors = [];

        if (in_array('sms', $channels) && empty($recipient['phone'])) {
            $errors[] = 'Phone number required for SMS notifications';
        }

        if (in_array('email', $channels) && empty($recipient['email'])) {
            $errors[] = 'Email address required for email notifications';
        }

        return [
            'valid' => empty($errors),
            'message' => implode(', ', $errors)
        ];
    }

    /**
     * Prepare message content for all channels
     */
    protected function prepareMessages(string $type, array $data): array
    {
        $templates = $this->config['templates'][$type] ?? [];

        $messages = [];

        // Prepare SMS message
        if (isset($templates['sms'])) {
            $messages['sms'] = $this->replacePlaceholders($templates['sms'], $data);
        }

        // Prepare email message
        if (isset($templates['email_subject']) || isset($templates['email_body'])) {
            $messages['email'] = [
                'subject' => $this->replacePlaceholders($templates['email_subject'] ?? '', $data),
                'body' => $this->replacePlaceholders($templates['email_body'] ?? '', $data)
            ];
        }

        return $messages;
    }

    /**
     * Replace placeholders in message templates
     */
    protected function replacePlaceholders(string $template, array $data): string
    {
        // Handle conditional variables first (e.g., {payment_time?Payment processed {payment_time}.})
        $template = preg_replace_callback('/\{(\w+)\?([^}]*)\}/', function($matches) use ($data) {
            $variable = $matches[1];
            $conditionalText = $matches[2];
            
            if (isset($data[$variable]) && !empty($data[$variable])) {
                // Replace the variable within the conditional text
                return str_replace('{' . $variable . '}', $data[$variable], $conditionalText);
            }
            
            return ''; // Remove the entire conditional block if variable is empty
        }, $template);
        
        // Handle regular variables
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        
        return $template;
    }

    /**
     * Send notification via specific channel
     */
    protected function sendViaChannel(string $channel, array $recipient, array $messages, string $type): array
    {
        $maxAttempts = $this->config['retry']['max_attempts'] ?? 3;
        $delay = $this->config['retry']['delay_seconds'] ?? 5;
        $exponentialBackoff = $this->config['retry']['exponential_backoff'] ?? true;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $result = match ($channel) {
                    'sms' => $this->sendSms($recipient, $messages['sms'] ?? ''),
                    'email' => $this->sendEmail($recipient, $messages['email'] ?? []),
                    default => ['success' => false, 'message' => 'Unknown channel: ' . $channel]
                };

                if ($result['success']) {
                    $this->logSuccess($type, $channel, $recipient, $attempt);
                    return $result;
                }

                // Log retry attempt
                if ($attempt < $maxAttempts) {
                    $this->logRetry($type, $channel, $recipient, $attempt, $result['message'] ?? 'Unknown error');

                    // Wait before retry
                    $retryDelay = $exponentialBackoff ? $delay * pow(2, $attempt - 1) : $delay;
                    sleep($retryDelay);
                }

            } catch (\Exception $e) {
                if ($attempt < $maxAttempts) {
                    $this->logRetry($type, $channel, $recipient, $attempt, $e->getMessage());

                    $retryDelay = $exponentialBackoff ? $delay * pow(2, $attempt - 1) : $delay;
                    sleep($retryDelay);
                } else {
                    return [
                        'success' => false,
                        'message' => 'Failed after ' . $maxAttempts . ' attempts: ' . $e->getMessage(),
                        'attempts' => $attempt
                    ];
                }
            }
        }

        return [
            'success' => false,
            'message' => 'Failed after ' . $maxAttempts . ' attempts',
            'attempts' => $maxAttempts
        ];
    }

    /**
     * Send SMS notification
     */
    protected function sendSms(array $recipient, string $message): array
    {
        if (empty($message)) {
            return ['success' => false, 'message' => 'SMS message is empty'];
        }

        return $this->smsService->send($recipient['phone'], $message);
    }

    /**
     * Send email notification
     */
    protected function sendEmail(array $recipient, array $emailData): array
    {
        if (empty($emailData['subject']) && empty($emailData['body'])) {
            return ['success' => false, 'message' => 'Email content is empty'];
        }

        try {
            Mail::raw($emailData['body'] ?? '', function ($mail) use ($recipient, $emailData) {
                $mail->to($recipient['email'], $recipient['name'] ?? '')
                     ->subject($emailData['subject'] ?? 'MikroTik RADIUS Notification')
                     ->from(
                         $this->config['email']['from_address'],
                         $this->config['email']['from_name']
                     );
            });

            return ['success' => true, 'message' => 'Email sent successfully'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Email failed: ' . $e->getMessage()];
        }
    }

    /**
     * Handle fallback notifications
     */
    protected function handleFallbacks(string $type, array $recipient, array $messages, array $results): array
    {
        $attempted = false;
        $success = false;

        // Try email fallback if SMS failed
        if (isset($results['sms']) && !$results['sms']['success'] && $this->config['sms']['fallback_to_email']) {
            if (!empty($recipient['email']) && $this->emailEnabled) {
                $result = $this->sendEmail($recipient, $messages['email'] ?? []);
                if ($result['success']) {
                    $success = true;
                    $attempted = true;
                }
            }
        }

        // Try SMS fallback if email failed
        if (isset($results['email']) && !$results['email']['success'] && $this->config['email']['fallback_to_sms']) {
            if (!empty($recipient['phone']) && $this->smsEnabled) {
                $result = $this->sendSms($recipient, $messages['sms'] ?? '');
                if ($result['success']) {
                    $success = true;
                    $attempted = true;
                }
            }
        }

        // Emergency fallback to log
        if (!$success && $this->config['emergency']['fallback_to_log']) {
            Log::warning('Notification fallback to log', [
                'type' => $type,
                'recipient' => $recipient,
                'messages' => $messages,
                'failed_results' => $results
            ]);
            $attempted = true;
        }

        return [
            'attempted' => $attempted,
            'success' => $success,
            'message' => $success ? 'Fallback succeeded' : 'All fallbacks failed'
        ];
    }

    /**
     * Check if recipient is rate limited
     */
    protected function isRateLimited(array $recipient): bool
    {
        if (!$this->config['rate_limiting']['enabled']) {
            return false;
        }

        $key = 'notification_rate_limit:' . ($recipient['phone'] ?? $recipient['email'] ?? 'unknown');
        $maxPerMinute = $this->config['rate_limiting']['max_per_minute'];
        $maxPerHour = $this->config['rate_limiting']['max_per_hour'];

        // Check per-minute limit
        if (RateLimiter::tooManyAttempts($key . ':minute', $maxPerMinute)) {
            return true;
        }

        // Check per-hour limit
        if (RateLimiter::tooManyAttempts($key . ':hour', $maxPerHour)) {
            return true;
        }

        // Increment counters
        RateLimiter::hit($key . ':minute', 60); // 1 minute
        RateLimiter::hit($key . ':hour', 3600); // 1 hour

        return false;
    }

    /**
     * Log notification attempt
     */
    protected function logNotification(string $type, array $recipient, array $results, bool $success): void
    {
        if (!$this->config['logging']['enabled']) {
            return;
        }

        if ($success && $this->config['logging']['log_success']) {
            Log::info('Notification sent successfully', [
                'type' => $type,
                'recipient_phone' => $recipient['phone'] ?? null,
                'recipient_email' => $recipient['email'] ?? null,
                'channels' => array_keys($results),
                'results' => $results
            ]);
        } elseif (!$success && $this->config['logging']['log_failures']) {
            Log::error('Notification failed', [
                'type' => $type,
                'recipient_phone' => $recipient['phone'] ?? null,
                'recipient_email' => $recipient['email'] ?? null,
                'channels' => array_keys($results),
                'results' => $results
            ]);
        }
    }

    /**
     * Log successful notification
     */
    protected function logSuccess(string $type, string $channel, array $recipient, int $attempt): void
    {
        if ($this->config['logging']['log_success']) {
            Log::info("Notification sent via {$channel}", [
                'type' => $type,
                'channel' => $channel,
                'attempt' => $attempt,
                'recipient_phone' => $recipient['phone'] ?? null,
                'recipient_email' => $recipient['email'] ?? null,
            ]);
        }
    }

    /**
     * Log retry attempt
     */
    protected function logRetry(string $type, string $channel, array $recipient, int $attempt, string $error): void
    {
        if ($this->config['logging']['log_retries']) {
            Log::warning("Notification retry attempt {$attempt} for {$channel}", [
                'type' => $type,
                'channel' => $channel,
                'attempt' => $attempt,
                'error' => $error,
                'recipient_phone' => $recipient['phone'] ?? null,
                'recipient_email' => $recipient['email'] ?? null,
            ]);
        }
    }

    /**
     * Build error response
     */
    protected function buildErrorResponse(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'channels_attempted' => [],
            'results' => []
        ];
    }

    /**
     * Test notification system
     */
    public function test(string $phone, string $email): array
    {
        return $this->send('welcome', [
            'name' => 'Test User',
            'phone' => $phone,
            'email' => $email,
        ], [
            'customer_name' => 'Test User',
            'username' => 'testuser123',
            'password' => 'testpass123',
        ]);
    }
}
