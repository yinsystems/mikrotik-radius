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
        return $this->send('trial_assignment', [
            'name' => $customer['name'],
            'email' => $customer['email'] ?? null,
            'phone' => $customer['phone'] ?? null,
        ], [
            'customer_name' => $customer['name'],
            'package_name' => $package['name'],
            'expires_at' => $package['expires_at'],
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
            'username' => $credentials['username'],
            'password' => $credentials['password'],
        ]);
    }

    /**
     * Send payment success confirmation
     */
    public function sendPaymentSuccess(array $customer, array $payment): array
    {
        return $this->send('payment_success', [
            'name' => $customer['name'],
            'email' => $customer['email'] ?? null,
            'phone' => $customer['phone'] ?? null,
        ], [
            'customer_name' => $customer['name'],
            'amount' => $payment['amount'],
            'currency' => $payment['currency'],
            'transaction_id' => $payment['transaction_id'],
        ]);
    }

    /**
     * Send subscription activated notification
     */
    public function sendSubscriptionActivated(array $customer, array $subscription): array
    {
        return $this->send('subscription_activated', [
            'name' => $customer['name'],
            'email' => $customer['email'] ?? null,
            'phone' => $customer['phone'] ?? null,
        ], [
            'customer_name' => $customer['name'],
            'package_name' => $subscription['package_name'],
            'expires_at' => $subscription['expires_at'],
            'username' => $subscription['username'],
        ]);
    }

    /**
     * Send expiration warning
     */
    public function sendExpirationWarning(array $customer, array $subscription): array
    {
        $hoursRemaining = $subscription['hours_remaining'] ?? 24;
        
        return $this->send('expiration_warning', [
            'name' => $customer['name'],
            'email' => $customer['email'] ?? null,
            'phone' => $customer['phone'] ?? null,
        ], [
            'customer_name' => $customer['name'],
            'package_name' => $subscription['package_name'],
            'expires_at' => $subscription['expires_at'],
            'hours_remaining' => $hoursRemaining,
        ]);
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