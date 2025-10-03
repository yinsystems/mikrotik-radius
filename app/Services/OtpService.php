<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * OTP Service for generating and verifying one-time passwords
 * 
 * This service is optimized for database cache driver.
 * It handles OTP generation, verification, rate limiting, and cleanup.
 */
class OtpService
{
    protected int $otpLength;
    protected int $expiryMinutes;
    protected int $maxAttempts;
    protected bool $numericOnly;
    protected string $cachePrefix;

    public function __construct()
    {
        $this->otpLength = config('otp.length', 6);
        $this->expiryMinutes = config('otp.expiry_minutes', 10);
        $this->maxAttempts = config('otp.max_attempts', 3);
        $this->numericOnly = config('otp.numeric_only', true);
        $this->cachePrefix = config('otp.cache_prefix', 'otp');
    }

    /**
     * Generate OTP for a given identifier (phone/email)
     */
    public function generate(string $identifier, string $type = 'default', ?int $customLength = null): array
    {
        try {
            // Clean the identifier
            $identifier = $this->cleanIdentifier($identifier);

            // Get type-specific configuration
            $typeConfig = $this->getTypeConfig($type);

            // Check if there's a rate limit
            if ($this->isRateLimited($identifier)) {
                return [
                    'success' => false,
                    'message' => 'Too many OTP requests. Please wait before requesting again.',
                    'rate_limited' => true,
                    'retry_after' => $this->getRateLimitRetryAfter($identifier)
                ];
            }

            // Generate the OTP
            $otpLength = $customLength ?? $typeConfig['length'];
            $otp = $this->generateOtpCode($otpLength);

            // Store OTP with metadata
            $expiryMinutes = $typeConfig['expiry_minutes'];
            $maxAttempts = $typeConfig['max_attempts'];

            $otpData = [
                'code' => $otp,
                'identifier' => $identifier,
                'type' => $type,
                'created_at' => now()->toISOString(),
                'expires_at' => now()->addMinutes($expiryMinutes)->toISOString(),
                'attempts' => 0,
                'max_attempts' => $maxAttempts,
                'used' => false,
            ];

            $cacheKey = $this->getOtpCacheKey($identifier, $type);
            Cache::put($cacheKey, $otpData, now()->addMinutes($expiryMinutes));

            // Update rate limiting
            $this->updateRateLimit($identifier);

            Log::info('OTP generated', [
                'identifier' => $this->maskIdentifier($identifier),
                'otp_length' => $otpLength,
                'expires_at' => $otpData['expires_at']
            ]);

            return [
                'success' => true,
                'otp' => $otp,
                'type' => $type,
                'expires_at' => $otpData['expires_at'],
                'expires_in_minutes' => $expiryMinutes,
                'message' => "OTP generated successfully. Valid for {$expiryMinutes} minutes."
            ];

        } catch (\Exception $e) {
            Log::error('OTP generation failed', [
                'identifier' => $this->maskIdentifier($identifier),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to generate OTP. Please try again.',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify OTP for a given identifier
     */
    public function verify(string $identifier, string $otp, string $type = 'default'): array
    {
        try {
            $identifier = $this->cleanIdentifier($identifier);
            $cacheKey = $this->getOtpCacheKey($identifier, $type);

            $otpData = Cache::get($cacheKey);

            if (!$otpData) {
                return [
                    'success' => false,
                    'message' => 'OTP not found or expired.',
                    'error_code' => 'OTP_NOT_FOUND'
                ];
            }

            // Check if OTP has been used
            if ($otpData['used']) {
                return [
                    'success' => false,
                    'message' => 'OTP has already been used.',
                    'error_code' => 'OTP_ALREADY_USED'
                ];
            }

            // Check if OTP has expired
            if (Carbon::parse($otpData['expires_at'])->isPast()) {
                Cache::forget($cacheKey);
                return [
                    'success' => false,
                    'message' => 'OTP has expired.',
                    'error_code' => 'OTP_EXPIRED'
                ];
            }

            // Increment attempt count
            $otpData['attempts']++;

            // Check if max attempts exceeded
            if ($otpData['attempts'] > $otpData['max_attempts']) {
                Cache::forget($cacheKey);

                Log::warning('OTP max attempts exceeded', [
                    'identifier' => $this->maskIdentifier($identifier),
                    'attempts' => $otpData['attempts']
                ]);

                return [
                    'success' => false,
                    'message' => 'Maximum verification attempts exceeded. Please request a new OTP.',
                    'error_code' => 'MAX_ATTEMPTS_EXCEEDED'
                ];
            }

            // Verify the OTP code
            if ($otp !== $otpData['code']) {
                // Update attempts in cache
                Cache::put($cacheKey, $otpData, Carbon::parse($otpData['expires_at']));

                $remainingAttempts = $otpData['max_attempts'] - $otpData['attempts'];

                return [
                    'success' => false,
                    'message' => "Invalid OTP. {$remainingAttempts} attempts remaining.",
                    'error_code' => 'INVALID_OTP',
                    'attempts_remaining' => $remainingAttempts
                ];
            }

            // OTP is valid - mark as used
            $otpData['used'] = true;
            $otpData['verified_at'] = now()->toISOString();
            Cache::put($cacheKey, $otpData, Carbon::parse($otpData['expires_at']));

            Log::info('OTP verified successfully', [
                'identifier' => $this->maskIdentifier($identifier),
                'attempts_used' => $otpData['attempts']
            ]);

            return [
                'success' => true,
                'message' => 'OTP verified successfully.',
                'verified_at' => $otpData['verified_at']
            ];

        } catch (\Exception $e) {
            Log::error('OTP verification failed', [
                'identifier' => $this->maskIdentifier($identifier),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to verify OTP. Please try again.',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate and send OTP in one call
     */
    public function generateAndSend(string $identifier, string $type = 'default'): array
    {
        $result = $this->generate($identifier, $type);

        if ($result['success']) {
            $sendResult = $this->sendOtp($identifier, $result['otp'], $result['expires_in_minutes']);

            return array_merge($result, [
                'sent' => $sendResult['success'] ?? false,
                'send_message' => $sendResult['message'] ?? 'Unknown error',
                'send_details' => $sendResult
            ]);
        }

        return $result;
    }

    /**
     * Resend OTP (generate new one)
     */
    public function resend(string $identifier, string $type = 'default'): array
    {
        // Clear existing OTP
        $this->clear($identifier, $type);

        // Generate new OTP
        return $this->generate($identifier, $type);
    }

    /**
     * Clear OTP for a given identifier
     */
    public function clear(string $identifier, string $type = 'default'): bool
    {
        try {
            $identifier = $this->cleanIdentifier($identifier);
            $cacheKey = $this->getOtpCacheKey($identifier, $type);

            Cache::forget($cacheKey);

            Log::info('OTP cleared', [
                'identifier' => $this->maskIdentifier($identifier)
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear OTP', [
                'identifier' => $this->maskIdentifier($identifier),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Check if OTP exists and is valid for identifier
     */
    public function exists(string $identifier, string $type = 'default'): array
    {
        try {
            $identifier = $this->cleanIdentifier($identifier);
            $cacheKey = $this->getOtpCacheKey($identifier, $type);

            $otpData = Cache::get($cacheKey);

            if (!$otpData) {
                return [
                    'exists' => false,
                    'message' => 'No OTP found for this identifier.'
                ];
            }

            $expiresAt = Carbon::parse($otpData['expires_at']);
            $isExpired = $expiresAt->isPast();

            return [
                'exists' => !$isExpired,
                'used' => $otpData['used'],
                'expires_at' => $otpData['expires_at'],
                'expires_in_seconds' => $isExpired ? 0 : $expiresAt->diffInSeconds(now()),
                'attempts_used' => $otpData['attempts'],
                'attempts_remaining' => $otpData['max_attempts'] - $otpData['attempts'],
                'is_expired' => $isExpired
            ];
        } catch (\Exception $e) {
            return [
                'exists' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get configuration for a specific OTP type
     */
    protected function getTypeConfig(string $type): array
    {
        $types = config('otp.types', []);

        if (isset($types[$type])) {
            return $types[$type];
        }

        // Return default configuration
        return [
            'length' => $this->otpLength,
            'expiry_minutes' => $this->expiryMinutes,
            'max_attempts' => $this->maxAttempts,
        ];
    }

    /**
     * Send OTP via notification service (SMS/Email)
     */
    public function sendOtp(string $identifier, string $otp, int $expiryMinutes): array
    {
        try {
            $message = $this->formatOtpMessage($otp, $expiryMinutes);

            // Determine if it's email or SMS based on identifier format
            if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                // Send email
                $subject = config('otp.notifications.email_subject', 'Your Verification Code');
                $result = app(NotificationService::class)->sendEmail($identifier, $subject, $message);
            } else {
                // Send SMS using the public method
                $result = app(NotificationService::class)->sendSmsNotification($identifier, $message);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to send OTP', [
                'identifier' => $this->maskIdentifier($identifier),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send OTP',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Format OTP message using template
     */
    protected function formatOtpMessage(string $otp, int $expiryMinutes): string
    {
        $template = filter_var(request()->input('identifier', ''), FILTER_VALIDATE_EMAIL)
            ? config('otp.notifications.email_template', 'Your verification code is {otp}. This code will expire in {minutes} minutes.')
            : config('otp.notifications.sms_template', 'Your one-time password: {otp}. Expires in {minutes} min. Keep this code confidential and do not share with anyone, including our staff.');

        return str_replace(
            ['{otp}', '{minutes}'],
            [$otp, $expiryMinutes],
            $template
        );
    }

    /**
     * Revoke all OTPs for a given identifier
     */
    public function revokeAll(string $identifier): int
    {
        $identifier = $this->cleanIdentifier($identifier);
        $types = array_keys(config('otp.types', []));
        $types[] = 'default'; // Include default type

        $revoked = 0;
        foreach ($types as $type) {
            if ($this->clear($identifier, $type)) {
                $revoked++;
            }
        }

        Log::info('OTPs revoked for identifier', [
            'identifier' => $this->maskIdentifier($identifier),
            'types_revoked' => $revoked
        ]);

        return $revoked;
    }

    /**
     * Generate OTP code
     */
    protected function generateOtpCode(int $length): string
    {
        if ($this->numericOnly) {
            $characters = '0123456789';
        } else {
            $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }

        $otp = '';
        for ($i = 0; $i < $length; $i++) {
            $otp .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $otp;
    }

    /**
     * Clean identifier (remove spaces, special chars, normalize)
     */
    protected function cleanIdentifier(string $identifier): string
    {
        // Remove spaces and convert to lowercase
        $identifier = strtolower(trim($identifier));

        // For phone numbers, keep only digits and +
        if (str_starts_with($identifier, '+') || is_numeric(str_replace(['+', '-', ' '], '', $identifier))) {
            $identifier = preg_replace('/[^+0-9]/', '', $identifier);
        }

        return $identifier;
    }

    /**
     * Get cache key for OTP
     */
    protected function getOtpCacheKey(string $identifier, string $type = 'default'): string
    {
        return $this->cachePrefix . ':' . $type . ':' . hash('sha256', $identifier);
    }

    /**
     * Get cache key for rate limiting
     */
    protected function getRateLimitCacheKey(string $identifier): string
    {
        return $this->cachePrefix . ':rate_limit:' . hash('sha256', $identifier);
    }

    /**
     * Check if identifier is rate limited
     */
    protected function isRateLimited(string $identifier): bool
    {
        $rateLimitKey = $this->getRateLimitCacheKey($identifier);
        $rateLimitData = Cache::get($rateLimitKey, null);

        $maxRequests = config('otp.rate_limit.max_requests', 3);
        $windowMinutes = config('otp.rate_limit.window_minutes', 60);

        if (!$rateLimitData) {
            return false;
        }

        // For database cache, we store both count and timestamp
        if (is_array($rateLimitData)) {
            $attempts = $rateLimitData['attempts'] ?? 0;
            $firstAttempt = $rateLimitData['first_attempt'] ?? now();
            
            // Check if the window has expired
            if (Carbon::parse($firstAttempt)->addMinutes($windowMinutes)->isPast()) {
                // Window expired, clear the cache
                Cache::forget($rateLimitKey);
                return false;
            }
            
            return $attempts >= $maxRequests;
        }

        // Legacy format (just a number)
        return $rateLimitData >= $maxRequests;
    }

    /**
     * Update rate limit counter
     */
    protected function updateRateLimit(string $identifier): void
    {
        $rateLimitKey = $this->getRateLimitCacheKey($identifier);
        $windowMinutes = config('otp.rate_limit.window_minutes', 60);

        $rateLimitData = Cache::get($rateLimitKey, null);
        
        if (!$rateLimitData) {
            // First attempt
            $newData = [
                'attempts' => 1,
                'first_attempt' => now()->toISOString(),
                'last_attempt' => now()->toISOString()
            ];
        } else {
            // Handle both new array format and legacy number format
            if (is_array($rateLimitData)) {
                $newData = [
                    'attempts' => $rateLimitData['attempts'] + 1,
                    'first_attempt' => $rateLimitData['first_attempt'],
                    'last_attempt' => now()->toISOString()
                ];
            } else {
                // Legacy format, convert to new format
                $newData = [
                    'attempts' => $rateLimitData + 1,
                    'first_attempt' => now()->toISOString(),
                    'last_attempt' => now()->toISOString()
                ];
            }
        }

        Cache::put($rateLimitKey, $newData, now()->addMinutes($windowMinutes));
    }

    /**
     * Get rate limit retry after time in seconds
     */
    protected function getRateLimitRetryAfter(string $identifier): int
    {
        $rateLimitKey = $this->getRateLimitCacheKey($identifier);
        $windowMinutes = config('otp.rate_limit.window_minutes', 60);

        $rateLimitData = Cache::get($rateLimitKey);
        if (!$rateLimitData) {
            return 0;
        }

        // For database cache with new format
        if (is_array($rateLimitData) && isset($rateLimitData['first_attempt'])) {
            $firstAttempt = Carbon::parse($rateLimitData['first_attempt']);
            $windowEnd = $firstAttempt->addMinutes($windowMinutes);
            
            if ($windowEnd->isPast()) {
                return 0; // Window has expired
            }
            
            return $windowEnd->diffInSeconds(now());
        }

        // Fallback for legacy format or unknown structure
        return $windowMinutes * 60; // Convert minutes to seconds as estimation
    }

    /**
     * Mask identifier for logging (privacy)
     */
    protected function maskIdentifier(string $identifier): string
    {
        $length = strlen($identifier);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        if (str_contains($identifier, '@')) {
            // Email masking
            [$local, $domain] = explode('@', $identifier);
            $maskedLocal = substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 2));
            return $maskedLocal . '@' . $domain;
        }

        // Phone number or general masking
        return substr($identifier, 0, 2) . str_repeat('*', $length - 4) . substr($identifier, -2);
    }

    /**
     * Validate OTP format
     */
    public function validateOtpFormat(string $otp): array
    {
        $trimmedOtp = trim($otp);

        if (empty($trimmedOtp)) {
            return [
                'valid' => false,
                'message' => 'OTP cannot be empty.'
            ];
        }

        if (strlen($trimmedOtp) !== $this->otpLength) {
            return [
                'valid' => false,
                'message' => "OTP must be exactly {$this->otpLength} characters long."
            ];
        }

        if ($this->numericOnly && !is_numeric($trimmedOtp)) {
            return [
                'valid' => false,
                'message' => 'OTP must contain only numbers.'
            ];
        }

        if (!$this->numericOnly && !ctype_alnum($trimmedOtp)) {
            return [
                'valid' => false,
                'message' => 'OTP must contain only letters and numbers.'
            ];
        }

        return [
            'valid' => true,
            'message' => 'OTP format is valid.'
        ];
    }

    /**
     * Get OTP statistics for monitoring
     */
    public function getStats(): array
    {
        // This would require additional tracking, but here's a basic structure
        return [
            'total_generated' => 0, // Would need persistent storage
            'total_verified' => 0,  // Would need persistent storage
            'success_rate' => 0,    // Would need calculation
            'current_active' => 0,  // Could scan cache keys
        ];
    }

    /**
     * Cleanup expired OTPs (maintenance function)
     */
    public function cleanupExpired(): int
    {
        // For database cache, we can't easily scan keys like Redis
        // Instead, we rely on Laravel's cache system to handle expiration
        // This method will return 0 since database cache handles cleanup automatically
        
        Log::info('OTP cleanup requested - database cache handles expiration automatically');
        
        return 0; // Database cache automatically handles expired entries
    }
}
