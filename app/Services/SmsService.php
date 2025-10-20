<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected string $driver;
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
        $this->driver = config('sms.driver', 'arkesel');

        // Set driver-specific configurations
        if ($this->driver === 'hubtel') {
            $this->senderId = config('sms.hubtel.sender_id');
        } else {
            $this->senderId = config('sms.arkesel.sender_id');
        }

        // Keep Arkesel configs for backward compatibility
        $this->apiKey = config('sms.arkesel.api_key');
        $this->apiUrl = config('sms.arkesel.api_url');
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

        // Check driver-specific API key requirements
        if ($this->driver === 'arkesel' && empty($this->apiKey)) {
            Log::error('SMS API key not configured for Arkesel');
            return [
                'success' => false,
                'message' => 'SMS API key not configured for Arkesel',
                'code' => 'API_KEY_MISSING'
            ];
        }

        if ($this->driver === 'hubtel' && (empty(config('sms.hubtel.api_client_id')) || empty(config('sms.hubtel.api_client_secret')))) {
            Log::error('SMS credentials not configured for Hubtel');
            return [
                'success' => false,
                'message' => 'SMS credentials not configured for Hubtel',
                'code' => 'API_CREDENTIALS_MISSING'
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
                Log::info('Sending SMS via ' . $this->driver, [
                    'driver' => $this->driver,
                    'to' => $formattedNumber,
                    'sender' => $sender,
                    'message_length' => strlen($message),
                ]);
            }

            // Send via selected driver
            if ($this->driver === 'hubtel') {
                return $this->sendViaHubtel($formattedNumber, $message, $sender);
            } else {
                return $this->sendViaArkesel($formattedNumber, $message, $sender);
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
     * Send SMS via Arkesel
     */
    private function sendViaArkesel(string $formattedNumber, string $message, string $sender): array
    {
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
            Log::info('SMS sent successfully via Arkesel', [
                'to' => $formattedNumber,
                'response' => $responseData,
            ]);

            return [
                'success' => true,
                'message' => 'SMS sent successfully via Arkesel',
                'response' => $responseData,
                'message_id' => $responseData['data']['id'] ?? null,
            ];
        } else {
            Log::error('Arkesel SMS sending failed', [
                'to' => $formattedNumber,
                'status' => $response->status(),
                'response' => $responseData,
            ]);

            return [
                'success' => false,
                'message' => 'Arkesel SMS failed: ' . ($responseData['message'] ?? 'Unknown error'),
                'code' => $responseData['code'] ?? 'API_ERROR',
                'response' => $responseData,
            ];
        }
    }

    /**
     * Send SMS via Hubtel
     */
    private function sendViaHubtel(string $formattedNumber, string $message, string $sender): array
    {
        $clientId = config('sms.hubtel.api_client_id');
        $clientSecret = config('sms.hubtel.api_client_secret');
        $apiUrl = config('sms.hubtel.api_url');
        $timeout = config('sms.hubtel.timeout', 30);

        $response = Http::timeout($timeout)
            ->withBasicAuth($clientId, $clientSecret)
            ->post($apiUrl, [
                'From' => $sender,
                'To' => $formattedNumber,
                'Content' => $message,
                'type' => 0, // Text message
            ]);

        $responseData = $response->json();

        if ($response->successful()) {
            Log::info('SMS sent successfully via Hubtel', [
                'to' => $formattedNumber,
                'response' => $responseData,
            ]);

            return [
                'success' => true,
                'message' => 'SMS sent successfully via Hubtel',
                'response' => $responseData,
                'message_id' => $responseData['messageId'] ?? null,
            ];
        } else {
            Log::error('Hubtel SMS sending failed', [
                'to' => $formattedNumber,
                'status' => $response->status(),
                'response' => $responseData,
            ]);

            return [
                'success' => false,
                'message' => 'Hubtel SMS failed: ' . ($responseData['message'] ?? 'Unknown error'),
                'code' => $responseData['status'] ?? 'API_ERROR',
                'response' => $responseData,
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
        // If it doesn't start with + and doesn't already have the country code
        elseif (!str_starts_with($phone, '+')) {
            // Extract just the numeric part of country code (e.g., "233" from "+233")
            $countryCodeNumeric = ltrim($this->defaultCountryCode, '+');

            // Only add country code if phone doesn't already start with it
            if (!str_starts_with($phone, $countryCodeNumeric)) {
                $phone = $this->defaultCountryCode . $phone;
            } else {
                // Already has country code, just add the + if needed
                $phone = '+' . $phone;
            }
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
        $status = [
            'enabled' => $this->enabled,
            'driver' => $this->driver,
            'sender_id' => $this->senderId,
            'default_country_code' => $this->defaultCountryCode,
        ];

        if ($this->driver === 'arkesel') {
            $status['api_configured'] = !empty($this->apiKey);
        } else {
            $status['api_configured'] = !empty(config('sms.hubtel.api_client_id')) && !empty(config('sms.hubtel.api_client_secret'));
        }

        return $status;
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

        if ($this->driver === 'hubtel') {
            return $this->testHubtelConnection();
        } else {
            return $this->testArkeselConnection();
        }
    }

    /**
     * Test Arkesel connection
     */
    private function testArkeselConnection(): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'Arkesel API key not configured',
            ];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->get($this->apiUrl, [
                    'action' => 'check-balance',
                    'api_key' => $this->apiKey,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Arkesel connection successful',
                    'driver' => 'arkesel',
                    'balance' => $response->json()['balance'] ?? 'Unknown',
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Arkesel connection failed',
                    'driver' => 'arkesel',
                    'status' => $response->status(),
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Arkesel connection test failed: ' . $e->getMessage(),
                'driver' => 'arkesel',
            ];
        }
    }

    /**
     * Test Hubtel connection
     */
    private function testHubtelConnection(): array
    {
        $clientId = config('sms.hubtel.api_client_id');
        $clientSecret = config('sms.hubtel.api_client_secret');

        if (empty($clientId) || empty($clientSecret)) {
            return [
                'success' => false,
                'message' => 'Hubtel credentials not configured',
                'driver' => 'hubtel',
            ];
        }

        try {
            $apiUrl = config('sms.hubtel.api_url');
            $timeout = config('sms.hubtel.timeout', 30);

            // Test with account balance endpoint (adjust URL as needed)
            $balanceUrl = str_replace('/messages/send', '/account/balance', $apiUrl);

            $response = Http::timeout($timeout)
                ->withBasicAuth($clientId, $clientSecret)
                ->get($balanceUrl);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Hubtel connection successful',
                    'driver' => 'hubtel',
                    'balance' => $response->json()['balance'] ?? 'Unknown',
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Hubtel connection failed',
                    'driver' => 'hubtel',
                    'status' => $response->status(),
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Hubtel connection test failed: ' . $e->getMessage(),
                'driver' => 'hubtel',
            ];
        }
    }
}
