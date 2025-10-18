# WiFi Portal USSD Flow & Services Integration

## 📱 COMPLETE USSD FLOW (Without Emojis)

### STEP 1: Initial Dial (*123#)
```
User dials: *123#
Display: "WiFi Portal
         Welcome John
         1) Buy Internet Package
         2) Get Free Trial (30min)
         3) My Active Token
         4) Help & Support"
```

### STEP 2A: Buy Package Flow
```
User selects: 1
Display: "Select Internet Package:
         1) Basic - GH¢2.00
            500MB, 1 day
         2) Standard - GH¢5.00
            1GB, 3 days
         3) Premium - GH¢10.00
            2GB, 7 days
         4) Unlimited - GH¢20.00
            Unlimited, 30 days
         0) Back to Main Menu"

User selects: 2
Display: "Confirm Purchase:
         Standard
         Price: GH¢5.00
         1GB data, Valid 3 days
         
         1) Confirm & Pay
         0) Back to Packages"

User selects: 1
Display: "Processing payment...
         Please approve mobile money
         request on your phone
         
         Waiting for confirmation..."

Payment Success:
Display: "Payment Successful!
         
         Your WiFi Token: 456789
         Package: Standard
         Valid until: 20/10/2025
         
         SMS sent with login details.
         Connect at: wifi.portal.com
         
         Thank you!"
```

### STEP 2B: Trial Token Flow
```
User selects: 2
Display: "Checking eligibility..."

If Eligible:
Display: "Free Trial Token!
         
         Your 30min Token: 123456
         Valid for: 30 minutes
         
         SMS sent with details.
         Connect at: wifi.portal.com
         
         Enjoy your free internet!"

If Not Eligible:
Display: "Trial Not Available
         
         You have already used your
         free trial or have an active
         subscription.
         
         Select option 1 to buy a
         package instead.
         
         Thank you!"
```

### STEP 2C: Check Active Token
```
User selects: 3

If Has Active Token:
Display: "Your Active Token
         
         Token: 456789
         Package: Standard
         Expires: 20/10/2025 14:30
         
         Data Used: 234MB / 1GB
         Time Left: 2 days 5 hours
         
         Thank you!"

If No Active Token:
Display: "No Active Token
         
         You don't have any active
         internet subscription.
         
         Select option 1 to buy a
         package or option 2 for
         free trial.
         
         Thank you!"
```

### STEP 2D: Help & Support
```
User selects: 4
Display: "Help & Support
         
         WiFi Connection Steps:
         1) Connect to 'WiFi-Portal'
         2) Open browser
         3) Enter your 6-digit token
         4) Start browsing
         
         Support: 0123456789
         
         Thank you!"
```

---

## 🔧 SERVICES & INTEGRATIONS

### 1. **EXISTING LARAVEL SERVICES** (Already Built)

#### **Customer Model Services**
- `Customer::firstOrCreate()` - Auto-register USSD users
- `$customer->generateInternetToken()` - Create 6-digit WiFi tokens
- `$customer->isEligibleForTrial()` - Check trial eligibility  
- `$customer->assignTrialPackage()` - Give free trial
- `$customer->createSubscription()` - Create paid subscriptions
- `$customer->hasActiveSubscription()` - Check active status
- `$customer->getActiveSubscription()` - Get current package

#### **Package Model Services**
- `Package::where('is_active', true)` - Get available packages
- `Package::where('is_trial', true)` - Get trial packages
- Package pricing, duration, data limits

#### **Subscription Model Services** 
- `$subscription->activate()` - Enable internet access
- `$subscription->isExpired()` - Check validity
- Session management and data usage tracking

### 2. **PAYMENT SERVICES** (Integration Required)

#### **Mobile Money Integration**
```php
// In ProcessPaymentAction
class MobileMoneyService 
{
    public function processPayment($phone, $amount, $provider = 'mtn')
    {
        // Integration options:
        
        // Option A: Hubtel Payment API
        $response = Http::post('https://api.hubtel.com/v1/merchantaccount/merchants/{merchantId}/receive/mobilemoney', [
            'CustomerMsisdn' => $phone,
            'Amount' => $amount,
            'PrimaryCallbackUrl' => url('/webhook/payment'),
            'Description' => 'WiFi Internet Package'
        ]);
        
        // Option B: Paystack Mobile Money
        $response = Http::withToken($paystackKey)->post('https://api.paystack.co/charge', [
            'amount' => $amount * 100, // Convert to pesewas
            'email' => $phone . '@ussd.portal.com',
            'mobile_money' => [
                'phone' => $phone,
                'provider' => $provider // mtn, vodafone, tigo
            ]
        ]);
        
        return [
            'success' => $response->successful(),
            'transaction_id' => $response->json('data.reference'),
            'status' => $response->json('data.status')
        ];
    }
}
```

### 3. **SMS NOTIFICATION SERVICES** (Integration Required)

#### **Token Delivery SMS**
```php
class SmsService
{
    public function sendTokenSms($phone, $token, $package, $expiresAt)
    {
        $message = "WiFi Token: {$token}\n";
        $message .= "Package: {$package}\n"; 
        $message .= "Valid until: {$expiresAt}\n";
        $message .= "Connect to 'WiFi-Portal' network\n";
        $message .= "Enter token in browser: wifi.portal.com";
        
        // Option A: Hubtel SMS API
        Http::post('https://devapi.hubtel.com/v1/messages/send', [
            'From' => 'WiFi Portal',
            'To' => $phone,
            'Content' => $message
        ]);
        
        // Option B: Custom SMS Gateway
        Http::post(env('SMS_GATEWAY_URL'), [
            'to' => $phone,
            'message' => $message,
            'sender_id' => 'WIFI'
        ]);
    }
}
```

### 4. **RADIUS/MIKROTIK SERVICES** (Already Built)

#### **WiFi Authentication**
- `RadCheck` - Token authentication entries
- `RadReply` - Bandwidth and time limits  
- `RadAcct` - Session tracking and data usage
- `$customer->createRadiusEntries()` - Setup WiFi access
- MikroTik hotspot integration for captive portal

### 5. **CACHE SERVICES** (Redis/Laravel Cache)

#### **USSD Session Management**
```php
// Store USSD session data
Cache::put($msisdn, $session_data, 300); // 5 minutes TTL

// Store payment status for polling
Cache::put("payment_{$transaction_id}", [
    'status' => 'pending',
    'customer_id' => $customer->id,
    'amount' => $amount
], 600); // 10 minutes
```

---

## 🚀 TECHNICAL ARCHITECTURE

### **Request Flow**
```
USSD Gateway → Laravel Controller → USSD Actions → Customer Services → Database
     ↓
SMS Gateway ← Token Generated ← Subscription Created ← Payment Processed
     ↓
MikroTik Hotspot ← RADIUS Auth ← WiFi Token ← Customer Login
```

### **Database Integration**
- **customers** table - User management
- **packages** table - Internet plans  
- **subscriptions** table - Active services
- **payments** table - Transaction history
- **rad_check/rad_reply** tables - RADIUS authentication

### **External APIs Required**
1. **Mobile Money API** (Hubtel/Paystack/MTN/Vodafone)
2. **SMS Gateway** (Hubtel/Custom provider)
3. **USSD Gateway** (Telecom provider integration)

### **Background Jobs** (Optional Enhancement)
- Payment status polling
- Token expiry notifications
- Usage limit alerts
- Subscription renewals

---

## 💡 KEY ADVANTAGES

✅ **Leverages Existing Code** - Uses 90% of your current Customer/Package system
✅ **Simple Integration** - Just add USSD layer on top
✅ **Automated Workflow** - From payment to WiFi access in seconds
✅ **Mobile-First** - Perfect for African market accessibility  
✅ **Scalable Architecture** - Can handle thousands of concurrent users
✅ **Real-time Updates** - Instant token generation and SMS delivery

The beauty is that your existing WiFi portal, customer management, and RADIUS systems remain unchanged - USSD just becomes another interface to the same services! 🎯