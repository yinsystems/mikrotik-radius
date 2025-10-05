<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Payment;
use App\Services\OtpService;
use App\Services\ReddePaymentService;
use App\Helpers\SettingsHelper;
use App\Settings\GeneralSettings;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

use Carbon\Carbon;

class CaptivePortalController extends Controller
{
    protected $otpService;
    protected $paymentService;

    public function __construct(OtpService $otpService, ReddePaymentService $paymentService)
    {
        $this->otpService = $otpService;
        $this->paymentService = $paymentService;
    }

    /**
     * Show the main captive portal page
     */
    public function index()
    {
        // Check if user already has a session
        $phone = Session::get('customer_phone');

        if ($phone) {
            $customer = Customer::where('phone', $phone)->first();
            if ($customer) {
                // Check if customer has active subscription
                $activeSubscription = Subscription::where('customer_id', $customer->id)
                    ->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->first();

                if ($activeSubscription) {
                    return redirect()->route('portal.dashboard');
                }
            }
        }

        return view('portal.welcome');
    }

    /**
     * Show registration form
     */
    public function showRegistration()
    {
        return view('portal.register');
    }

    /**
     * Handle registration request and send OTP
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^\+?[0-9]{10,15}$/',
            'terms' => 'required|accepted',
            'email' => 'required|email',
            'name' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $phone = $request->phone;
        $email = $request->email;
        $name = $request->name;
        $password = $request->password;

        // Check if customer already exists
        $existingCustomer = Customer::where('phone', $phone)->first();
        if ($existingCustomer) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number already registered. Please login instead.'
            ], 422);
        }

        // Check OTP setting from general settings
        $settings = app(GeneralSettings::class);
        $otpEnabled = $settings->enable_otp_verification;

        if ($otpEnabled) {
            // Original OTP flow - store data in session and send OTP
            Session::put('registration_email', $email);
            Session::put('registration_name', $name);
            Session::put('registration_password', $password);

            $x = Session::get('registration_email');
            $y = Session::get('registration_name');
            $z = Session::get('registration_password');

            Log::info("OTP enabled - storing registration data: ".$x." ".$y." ".$z);

            // Generate and send OTP
            $otpResult = $this->otpService->generateAndSend($phone, 'registration');

            if ($otpResult['success']) {
                // Store phone in session for OTP verification
                Session::put('registration_phone', $phone);

                return response()->json([
                    'success' => true,
                    'message' => 'OTP sent successfully to your phone number',
                    'expires_at' => $otpResult['expires_at'],
                    'otp_enabled' => true
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $otpResult['message']
            ], 400);
        } else {
            // OTP disabled - create customer directly
            Log::info("OTP disabled - creating customer directly for phone: " . $phone);

            try {
                // Create customer immediately without OTP verification
                $customer = Customer::create([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'password' => $password,
                    'username' => $phone,
                    'status' => 'active',
                    'created_at' => now(),
                ]);

                // Set portal session
                $this->setPortalSession($customer->id, $phone);

                return response()->json([
                    'success' => true,
                    'message' => 'Registration successful! Please select a package.',
                    'redirect_url' => route('portal.dashboard'),
                    'otp_enabled' => false
                ]);

            } catch (Exception $e) {
                Log::error('Direct registration failed: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Registration failed. Please try again.'
                ], 500);
            }
        }
    }

    /**
     * Verify OTP and create customer
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $phone = Session::get('registration_phone');
        $email = Session::get('registration_email');
        $name = Session::get('registration_name');
        $password = Session::get('registration_password');

        if (!$phone) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please start registration again.'
            ], 400);
        }

        // Verify OTP
        $verifyResult = $this->otpService->verify($phone, $request->otp, 'registration');

        if ($verifyResult['success']) {
            // Create customer with phone as default name
            $customer = Customer::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password,
                'username' => $phone,
                'status' => 'active',
                'created_at' => now(),
            ]);

            // Store customer info in portal session
            Session::forget('registration_phone');
            Session::forget('registration_email');
            Session::forget('registration_name');
            Session::forget('registration_password');
            $this->setPortalSession($customer->id, $phone);

            return response()->json([
                'success' => true,
                'message' => 'Registration successful! Please select a package.',
                'redirect_url' => route('portal.dashboard')
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $verifyResult['message'],
            'attempts_remaining' => $verifyResult['attempts_remaining'] ?? null
        ], 400);
    }

    /**
     * Show package selection page
     */
    public function showPackages()
    {
        $phone = Session::get('customer_phone');
        if (!$phone) {
            return redirect()->route('portal.register');
        }

        $customer = Customer::where('phone', $phone)->first();
        if (!$customer) {
            return redirect()->route('portal.register');
        }

        $packages = Package::where('is_active', true)->where('is_trial', false)->get();
        $settings = SettingsHelper::general();

        // Check purchase eligibility and get active subscription details
        $activeSubscription = $customer->getActiveSubscription();
        $hasActive = $activeSubscription !== null;

        // Get package history - last 5 subscriptions
        $packageHistory = $customer->subscriptions()
            ->with(['package', 'payment'])
            ->where('status', '!=', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $purchaseCheck = [
            'eligible' => !$hasActive,
            'has_active' => $hasActive,
            'message' => null,
            'warning' => null,
            'active_subscription' => $activeSubscription
        ];

        if ($hasActive) {
            $purchaseCheck['message'] = 'You have an active subscription. New package will replace your current subscription.';
            $purchaseCheck['warning'] = 'Purchasing a new package will replace your current active subscription. Any remaining time on your current package will be lost.';
        }

        return view('portal.packages', compact('packages', 'settings', 'purchaseCheck', 'packageHistory'));
    }

    /**
     * Show customer dashboard
     */
    public function showDashboard()
    {
        $phone = Session::get('customer_phone');
        if (!$phone) {
            return redirect()->route('portal.index');
        }

        $customer = Customer::where('phone', $phone)->first();
        if (!$customer) {
            return redirect()->route('portal.index');
        }

        $activeSubscription = $customer->getActiveSubscription();
        $settings = SettingsHelper::general();

        // Get complete package history for dashboard
        $packageHistory = $customer->subscriptions()
            ->with(['package', 'payment'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('portal.dashboard', compact('customer', 'activeSubscription', 'settings', 'packageHistory'));
    }

    /**
     * Handle package selection and redirect to payment
     */
    public function selectPackage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:packages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $phone = Session::get('customer_phone');
        $customerId = Session::get('customer_id');

        if (!$phone || !$customerId) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please register again.'
            ], 400);
        }

        $package = Package::findOrFail($request->package_id);

        // Store selected package in session
        Session::put('selected_package_id', $package->id);

        return response()->json([
            'success' => true,
            'message' => 'Package selected successfully',
            'package' => $package,
            'redirect' => route('portal.payment')
        ]);
    }

    /**
     * Show payment page
     */
    public function showPayment()
    {
        $phone = Session::get('customer_phone');
        $customerId = Session::get('customer_id');
        $packageId = Session::get('selected_package_id');

        if (!$phone || !$customerId || !$packageId) {
            return redirect()->route('portal.index');
        }

        $customer = Customer::findOrFail($customerId);
        $package = Package::findOrFail($packageId);
        $settings = SettingsHelper::general();

        return view('portal.payment', compact('customer', 'package', 'settings'));
    }

    /**
     * Handle login for existing customers
     */
    public function showLogin()
    {
        return view('portal.login');
    }

    /**
     * Process login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'password' => 'nullable|string', // Make password optional for OTP-based login
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $customer = Customer::where('phone', $request->phone)->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number not found. Please register first.'
            ], 404);
        }

        // Check OTP setting from general settings
        $settings = app(GeneralSettings::class);
        $otpEnabled = $settings->enable_login_otp_verification;

        if ($otpEnabled) {
            // Original OTP-based login flow
            $otpResult = $this->otpService->generateAndSend($request->phone, 'login');

            if ($otpResult['success']) {
                Session::put('login_phone', $request->phone);

                return response()->json([
                    'success' => true,
                    'message' => 'OTP sent to your phone number',
                    'expires_at' => $otpResult['expires_at'],
                    'otp_enabled' => true
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $otpResult['message']
            ], 400);
        } else {
            // OTP disabled - use password-based login
            if (!$request->password) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is required when OTP is disabled.'
                ], 422);
            }

            // Verify password
            if ($customer->password !== $request->password) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid password. Please try again.'
                ], 401);
            }

            // Login successful - set session
            $this->setPortalSession($customer->id, $customer->phone);

            return response()->json([
                'success' => true,
                'message' => 'Login successful!',
                'redirect_url' => route('portal.dashboard'),
                'otp_enabled' => false
            ]);
        }
    }

    /**
     * Verify login OTP
     */
    public function verifyLoginOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $phone = Session::get('login_phone');
        if (!$phone) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please try login again.'
            ], 400);
        }

        $verifyResult = $this->otpService->verify($phone, $request->otp, 'login');

        if ($verifyResult['success']) {
            $customer = Customer::where('phone', $phone)->first();

            Session::forget('login_phone');
            $this->setPortalSession($customer->id, $phone);

            return response()->json([
                'success' => true,
                'message' => 'Login successful!',
                'redirect_url' => route('portal.dashboard')
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $verifyResult['message'],
            'attempts_remaining' => $verifyResult['attempts_remaining'] ?? null
        ], 400);
    }

    /**
     * Logout customer
     */
    public function logout()
    {
        $this->clearPortalSession();
        return redirect()->route('portal.index')->with('success', 'You have been logged out successfully.');
    }

    /**
     * Process payment through Redde
     */
    public function processPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:packages,id',
            'network' => 'required|in:mtn,AIRTELTIGO,VODAFONE',
            'momo_phone' => 'required|string|regex:/^0[0-9]{9}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $phone = Session::get('customer_phone');
        $customerId = Session::get('customer_id');

        if (!$phone || !$customerId) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please login again.'
            ], 400);
        }

        try {
            $customer = Customer::findOrFail($customerId);
            $package = Package::findOrFail($request->package_id);
            $reference = $this->generateTransactionId();
            // Create payment record first
            $payment = Payment::create([
                'customer_id' => $customerId,
                'package_id' => $package->id,
                'amount' => $package->price,
                'currency' => 'GHS',
                'payment_method' => 'mobile_money',
                'network' => strtoupper($request->network),
                'mobile_number' => $request->momo_phone,
                'status' => 'pending',
                'transaction_id' => $reference,
                'internal_reference' => $reference,
                'payment_date' => now(),
            ]);

            // Process payment with Redde
            $paymentData = [
                'amount' => $package->price,
                'phone_number' => $request->momo_phone,
                'payment_option' => strtoupper($request->network),
                'description' => "WiFi Package: {$package->name}",
                'reference' => "WIFI_PKG_{$package->id}",
                'client_transaction_id' => $payment->transaction_id,
            ];

            $result = $this->paymentService->createSubscriptionPayment($paymentData);

            if ($result['success']) {
                // Update payment with Redde transaction details
                $payment->update([
                    'transaction_id' => $result['transaction_id'],
                    'gateway_response' => json_encode($result),
                    'status' => 'processing',
                ]);

                // Store payment ID in session for status checking
                Session::put('payment_id', $payment->id);

                return response()->json([
                    'success' => true,
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'message' => 'Payment initiated successfully. Please check your phone for the mobile money prompt.',
                    'status' => 'processing'
                ]);
            } else {
                // Update payment status to failed
                $payment->update([
                    'status' => 'failed',
                    'gateway_response' => json_encode($result),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $result['error'] ?? 'Payment initiation failed. Please try again.'
                ], 400);
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment processing error. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check payment status
     */
    public function checkPaymentStatus(Request $request)
    {
        $paymentId = $request->payment_id ?? Session::get('payment_id');

        if (!$paymentId) {
            return response()->json([
                'success' => false,
                'message' => 'Payment ID not found'
            ], 400);
        }

        try {
            $payment = Payment::findOrFail($paymentId);

            // If payment is already completed, return success
            if ($payment->status === 'completed') {
                return response()->json([
                    'success' => true,
                    'status' => 'completed',
                    'message' => 'Payment completed successfully!',
                    'redirect' => 'http://192.168.77.1/login',
                    'router_redirect' => true,
                    'credentials' => [
                        'username' => $payment->customer->username,
                        'password' => $payment->customer->password
                    ]
                ]);
            }

            // If payment is failed, return failure
            if ($payment->status === 'failed') {
                return response()->json([
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'Payment failed. Please try again.'
                ]);
            }

            // Check status with Redde if payment is still processing
            if ($payment->transaction_id) {
                $statusResult = $this->paymentService->checkStatus($payment->transaction_id);

                if ($statusResult['success']) {
                    $newStatus = $this->mapReddeStatusToInternal($statusResult['status']);

                    // Update payment status
                    $payment->update([
                        'status' => $newStatus,
                        'gateway_response' => json_encode($statusResult),
                    ]);

                    // If payment is now completed, create subscription
                    if ($newStatus === 'completed') {
                        $customer = Customer::find($payment->customer_id);
                        $subscription = $customer->createSubscription(
                            $payment->package_id,
                            $payment->id
                        );
                        $subscription->activate();

                        return response()->json([
                            'success' => true,
                            'status' => 'completed',
                            'message' => 'Payment completed successfully! Your subscription is now active.',
                            'redirect' => 'http://192.168.77.1/login',
                            'router_redirect' => true,
                            'credentials' => [
                                'username' => $payment->customer->username,
                                'password' => $payment->customer->password
                            ]
                        ]);
                    } elseif ($newStatus === 'failed') {
                        return response()->json([
                            'success' => false,
                            'status' => 'failed',
                            'message' => 'Payment failed. Please try again.'
                        ]);
                    }
                }
            }

            // Payment still processing
            return response()->json([
                'success' => true,
                'status' => 'processing',
                'message' => 'Payment is being processed. Please wait...'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking payment status'
            ], 500);
        }
    }

    /**
     * Handle Redde payment callback
     */
    public function handlePaymentCallback(Request $request)
    {
        try {
            $callbackData = $request->all();
            $result = $this->paymentService->processCallback($callbackData);

            if (isset($result['client_transaction_id'])) {
                $payment = Payment::where('transaction_id', $result['client_transaction_id'])->first();

                if ($payment) {
                    $newStatus = $this->mapReddeStatusToInternal($result['status']);

                    $payment->update([
                        'status' => $newStatus,
                        'gateway_response' => json_encode($result),
                    ]);

                    // If payment completed, create subscription
                    if ($newStatus === 'completed') {
                        $customer = Customer::find($payment->customer_id);
                        $subscription = $customer->createSubscription(
                            $payment->package_id,
                            $payment->id
                        );
                    }
                }
            }

            return response()->json(['status' => 'success']);

        } catch (Exception $e) {
            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Map Redde status to internal status
     */
    protected function mapReddeStatusToInternal($reddeStatus)
    {
        $statusMap = [
            'PAID' => 'completed',
            'SUCCESS' => 'completed',
            'SUCCESSFUL' => 'completed',
            'FAILED' => 'failed',
            'FAIL' => 'failed',
            'CANCELLED' => 'failed',
            'PENDING' => 'processing',
            'PROCESSING' => 'processing',
            'PROGRESS' => 'processing',
        ];

        return $statusMap[strtoupper($reddeStatus)] ?? 'processing';
    }

    /**
     * Generate unique transaction ID
     */
    protected function generateTransactionId()
    {
        return 'WIFI_' . strtoupper(uniqid()) . '_' . time();
    }

    /**
     * Set portal session data for authenticated customer
     */
    protected function setPortalSession($customerId, $phone)
    {
        Session::put('portal_customer_id', $customerId);
        Session::put('portal_customer_phone', $phone);
        Session::put('portal_last_activity', Carbon::now()->toDateTimeString());
        Session::put('portal_session_created', Carbon::now()->toDateTimeString());

        // Keep backward compatibility for existing code
        Session::put('customer_id', $customerId);
        Session::put('customer_phone', $phone);
    }

    /**
     * Clear portal session data
     */
    protected function clearPortalSession()
    {
        Session::forget([
            'portal_customer_id',
            'portal_customer_phone',
            'portal_last_activity',
            'portal_session_created',
            'customer_id',
            'customer_phone',
            'selected_package_id',
            'payment_id',
            'otp_verified_phone',
            'otp_attempts',
            'otp_last_sent'
        ]);
    }

    /**
     * Check if customer is authenticated in portal
     */
    protected function isPortalAuthenticated()
    {
        return Session::has('portal_customer_id') && Session::has('portal_customer_phone');
    }

    /**
     * Change customer WiFi password
     */
    public function changePassword(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6',
                'confirm_password' => 'required|string|same:new_password'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get authenticated customer
            $customerId = Session::get('portal_customer_id') ?? Session::get('customer_id');

            // Debug session information
            Log::info('Password change attempt - Session check', [
                'portal_customer_id' => Session::get('portal_customer_id'),
                'customer_id' => Session::get('customer_id'),
                'customer_phone' => Session::get('customer_phone'),
                'portal_customer_phone' => Session::get('portal_customer_phone'),
                'session_id' => Session::getId(),
                'all_session_data' => Session::all()
            ]);

            if (!$customerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must be logged in to change password. Please refresh the page and try again.'
                ], 401);
            }

            $customer = Customer::find($customerId);
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            // Verify current password
            if ($customer->password !== $request->current_password) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            // Check if new password is different from current
            if ($customer->password === $request->new_password) {
                return response()->json([
                    'success' => false,
                    'message' => 'New password must be different from current password'
                ], 400);
            }

            // Validate new password strength
            $newPassword = $request->new_password;
            if (strlen($newPassword) < 6) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password must be at least 6 characters long'
                ], 400);
            }

            if (!preg_match('/(?=.*[a-zA-Z])(?=.*[0-9])/', $newPassword)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password must contain both letters and numbers'
                ], 400);
            }

            // Update password
            $customer->password = $newPassword;
            $customer->save();

            Log::info('Customer password changed', [
                'customer_id' => $customer->id,
                'phone' => $customer->phone,
                'timestamp' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'WiFi password updated successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Password change failed', [
                'customer_id' => Session::get('portal_customer_id'),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating password. Please try again.'
            ], 500);
        }
    }

    /**
     * Get OTP verification status from settings
     */
    public function getOtpStatus()
    {
        try {
            $settings = app(GeneralSettings::class);
            
            return response()->json([
                'success' => true,
                'registration_otp_enabled' => $settings->enable_otp_verification ?? true,
                'login_otp_enabled' => $settings->enable_login_otp_verification ?? true
            ]);
        } catch (Exception $e) {
            // Default to OTP enabled if there's an error
            return response()->json([
                'success' => true,
                'registration_otp_enabled' => true,
                'login_otp_enabled' => true
            ]);
        }
    }
}
