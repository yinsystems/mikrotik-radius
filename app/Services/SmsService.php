<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected string $apiKey;
    protected string $apiUrl;
    protected string $senderId;
    protected int $timeout;
    protected bool $enabled;
    protected bool $logMessages;
    protected int $maxLength;
    protected string $defaultCountryCode;

    public function __construct()
    {
        $this->apiKey = config('sms.arkesel.api_key');
        $this->apiUrl = config('sms.arkesel.api_url');
        $this->senderId = config('sms.arkesel.sender_id');
        $this->timeout = config('sms.arkesel.timeout', 3000);
        $this->enabled = config('sms.enabled', true);
        $this->logMessages = config('sms.log_messages', true);
        $this->maxLength = config('sms.max_length', 160);
        $this->defaultCountryCode = config('sms.default_country_code', '+233');
    }

    /**
     * Send SMS message
     */
    public function send(string $phoneNumber, string $message, ?string $senderId = null): array
    {
        if (!$this->enabled) {
            Log::info('SMS sending is disabled');
            return [
                'success' => false,
                'message' => 'SMS sending is disabled',
                'code' => 'SMS_DISABLED'
            ];
        }
        Log::info("$this->apiKey API KEY, $this->apiUrl API URL, $this->senderId SENDER ID, $this->timeout TIMEOUT, $this->enabled ENABLED");

        if (empty($this->apiKey)) {
            Log::error('SMS API key not configured');
            return [
                'success' => false,
                'message' => 'SMS API key not configured',
                'code' => 'API_KEY_MISSING'
            ];
        }

        try {
            // Format phone number
            $formattedNumber = $this->formatPhoneNumber($phoneNumber);

            // Validate message length
            if (strlen($message) > $this->maxLength) {
                $message = substr($message, 0, $this->maxLength - 3) . '...';
            }

            // Use provided sender ID or default
            $sender = $senderId ?? $this->senderId;

            // Log the SMS attempt
            if ($this->logMessages) {
                Log::info('Sending SMS', [
                    'to' => $formattedNumber,
                    'sender' => $sender,
                    'message_length' => strlen($message),
                ]);
            }

            // Send SMS via Arkesel API
            $response = Http::timeout($this->timeout)
                ->get($this->apiUrl, [
                    'action' => 'send-sms',
                    'api_key' => $this->apiKey,
                    'to' => $formattedNumber,
                    'from' => $sender,
                    'sms' => $message,
                ]);

            $responseData = $response->json();

            if ($response->successful()) {
                Log::info('SMS sent successfully', [
                    'to' => $formattedNumber,
                    'response' => $responseData,
                ]);

                return [
                    'success' => true,
                    'message' => 'SMS sent successfully',
                    'response' => $responseData,
                    'message_id' => $responseData['data']['id'] ?? null,
                ];
            } else {
                Log::error('SMS sending failed', [
                    'to' => $formattedNumber,
                    'status' => $response->status(),
                    'response' => $responseData,
                ]);

                return [
                    'success' => false,
                    'message' => 'SMS sending failed: ' . ($responseData['message'] ?? 'Unknown error'),
                    'code' => $responseData['code'] ?? 'API_ERROR',
                    'response' => $responseData,
                ];
            }

        } catch (\Exception $e) {
            Log::error('SMS sending exception', [
                'to' => $phoneNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'SMS sending failed: ' . $e->getMessage(),
                'code' => 'EXCEPTION',
            ];
        }
    }

    /**
     * Send bulk SMS to multiple recipients
     */
    public function sendBulk(array $phoneNumbers, string $message, ?string $senderId = null): array
    {
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($phoneNumbers as $phoneNumber) {
            $result = $this->send($phoneNumber, $message, $senderId);
            $results[] = [
                'phone' => $phoneNumber,
                'result' => $result,
            ];

            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }

        Log::info('Bulk SMS completed', [
            'total' => count($phoneNumbers),
            'success' => $successCount,
            'failures' => $failureCount,
        ]);

        return [
            'success' => $successCount > 0,
            'total' => count($phoneNumbers),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'results' => $results,
        ];
    }

    /**
     * Format phone number to international format
     */
    protected function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove any non-digit characters except +
        $phone = preg_replace('/[^\d+]/', '', $phoneNumber);

        // If it starts with 0, replace with country code
        if (str_starts_with($phone, '0')) {
            $phone = $this->defaultCountryCode . substr($phone, 1);
        }

        // If it doesn't start with +, add default country code
        if (!str_starts_with($phone, '+')) {
            $phone = $this->defaultCountryCode . $phone;
        }

        return $phone;
    }

    /**
     * Validate phone number format
     */
    public function isValidPhoneNumber(string $phoneNumber): bool
    {
        $formatted = $this->formatPhoneNumber($phoneNumber);
        // Basic validation for Ghana numbers (+233xxxxxxxxx)
        return preg_match('/^\+233\d{9}$/', $formatted);
    }

    /**
     * Get SMS service status
     */
    public function getStatus(): array
    {
        return [
            'enabled' => $this->enabled,
            'api_configured' => !empty($this->apiKey),
            'sender_id' => $this->senderId,
            'default_country_code' => $this->defaultCountryCode,
        ];
    }

    /**
     * Test SMS connection
     */
    public function testConnection(): array
    {
        if (!$this->enabled) {
            return [
                'success' => false,
                'message' => 'SMS service is disabled',
            ];
        }

        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'API key not configured',
            ];
        }

        try {
            // Test with a simple API call
            $response = Http::timeout($this->timeout)
                ->get($this->apiUrl, [
                    'action' => 'check-balance',
                    'api_key' => $this->apiKey,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'SMS service connection successful',
                    'balance' => $response->json()['balance'] ?? 'Unknown',
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'SMS service connection failed',
                    'status' => $response->status(),
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ];
        }
    }
}
