# OTP Service Usage Examples

## Basic Configuration

The OTP service is now fully configured and ready to use. Here are some usage examples:

## Configuration

The service uses the configuration from `config/otp.php` which supports different OTP types:

- `login` - For login verification (5 minutes expiry)
- `registration` - For user registration (15 minutes expiry)  
- `password_reset` - For password reset (10 minutes expiry)
- `transaction` - For transaction confirmation (5 minutes expiry)

## Usage Examples

### 1. Basic OTP Generation

```php
// In your controller or service
use App\Services\OtpService;

$otpService = app(OtpService::class);

// Generate OTP for login
$result = $otpService->generate('user@example.com', 'login');

if ($result['success']) {
    // OTP: $result['otp']
    // Expires: $result['expires_at']
    // Type: $result['type']
}
```

### 2. Generate and Send OTP

```php
// Generate and automatically send OTP via SMS/Email
$result = $otpService->generateAndSend('+233123456789', 'login');

if ($result['success']) {
    if ($result['sent']) {
        return response()->json(['message' => 'OTP sent successfully']);
    } else {
        return response()->json(['message' => 'OTP generated but failed to send: ' . $result['send_message']]);
    }
}
```

### 3. Verify OTP

```php
$result = $otpService->verify('user@example.com', '123456', 'login');

if ($result['success']) {
    // OTP verified successfully
    // Proceed with login
} else {
    // Handle verification failure
    // $result['message'] contains error details
    // $result['attempts_remaining'] shows remaining attempts
}
```

### 4. Check OTP Status

```php
$status = $otpService->exists('user@example.com', 'login');

if ($status['exists']) {
    // OTP exists and is valid
    // $status['expires_in_seconds'] - time remaining
    // $status['attempts_remaining'] - attempts left
}
```

### 5. Clear/Revoke OTPs

```php
// Clear specific OTP type
$otpService->clear('user@example.com', 'login');

// Revoke all OTP types for user
$revokedCount = $otpService->revokeAll('user@example.com');
```

## Controller Integration Example

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OtpController extends Controller
{
    public function __construct(
        private OtpService $otpService
    ) {}

    public function sendLoginOtp(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string', // email or phone
        ]);

        $result = $this->otpService->generateAndSend(
            $request->identifier,
            'login'
        );

        if ($result['success']) {
            return response()->json([
                'message' => 'OTP sent successfully',
                'expires_at' => $result['expires_at'],
                'sent_via' => filter_var($request->identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'sms'
            ]);
        }

        return response()->json([
            'message' => $result['message']
        ], 400);
    }

    public function verifyLoginOtp(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        $result = $this->otpService->verify(
            $request->identifier,
            $request->otp,
            'login'
        );

        if ($result['success']) {
            // Generate auth token or session
            return response()->json([
                'message' => 'Login successful',
                'verified_at' => $result['verified_at']
            ]);
        }

        return response()->json([
            'message' => $result['message'],
            'attempts_remaining' => $result['attempts_remaining'] ?? null
        ], 400);
    }
}
```

## Environment Configuration

Add these to your `.env` file for customization:

```env
# OTP Configuration
OTP_LENGTH=6
OTP_EXPIRY_MINUTES=10
OTP_MAX_ATTEMPTS=3
OTP_NUMERIC_ONLY=true

# Rate Limiting
OTP_RATE_LIMIT_ENABLED=true
OTP_RATE_LIMIT_MAX_REQUESTS=3
OTP_RATE_LIMIT_WINDOW_MINUTES=60

# Notification Integration
OTP_AUTO_SEND=false
OTP_SMS_TEMPLATE="Your OTP code is: {otp}. Valid for {minutes} minutes."
OTP_EMAIL_TEMPLATE="Your verification code is {otp}. This code will expire in {minutes} minutes."
OTP_EMAIL_SUBJECT="Your Verification Code"

# Security
OTP_HASH_IDENTIFIER=true
OTP_LOG_ATTEMPTS=true
OTP_CLEAR_ON_MAX_ATTEMPTS=true
```

## Integration with Notification System

The OTP service integrates with the NotificationService for automatic SMS/Email delivery:

```php
// The service will automatically detect if identifier is email or phone
$result = $otpService->generateAndSend('user@example.com', 'registration');
// Sends via email

$result = $otpService->generateAndSend('+233123456789', 'registration');
// Sends via SMS
```

## Security Features

1. **Rate Limiting**: Prevents spam requests (configurable)
2. **Attempt Limiting**: Maximum verification attempts per OTP
3. **Automatic Expiry**: OTPs expire after configured time
4. **Secure Generation**: Cryptographically secure random generation
5. **Identifier Hashing**: Identifiers are hashed in cache for privacy
6. **Audit Logging**: All attempts are logged for security monitoring

## Cache Storage

OTPs are stored in Laravel's cache system with the following structure:

```
Cache Key: otp:type:hash(identifier)
Data: {
    code: "123456",
    identifier: "user@example.com",
    type: "login",
    created_at: "2024-01-01T10:00:00.000000Z",
    expires_at: "2024-01-01T10:05:00.000000Z",
    attempts: 0,
    max_attempts: 3,
    used: false
}
```

This ensures OTPs are automatically cleaned up when they expire and provides fast access for verification.