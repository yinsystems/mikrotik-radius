<?php

namespace App\Http\Controllers;

use App\Http\Ussd\Actions\WifiWelcomeAction;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Sparors\Ussd\Facades\Ussd;
use Exception;

class WifiUssdController extends Controller
{
    /**
     * Handle the incoming request from Hubtel Programmable Services API.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
    {
        // Log the incoming request for debugging
        Log::info('WiFi USSD Request', $request->all());
        Log::info('WiFi USSD Request URL: ' . $request->url());

        // Extract data from Hubtel request
        $sessionId = $request->input('SessionId');
        $type = $request->input('Type');
        $message = $request->input('Message');
        $mobile = $request->input('Mobile');
        $clientState = $request->input('ClientState');
        $operator = $request->input('Operator');
        $sequence = $request->input('Sequence');
        $platform = $request->input('Platform', 'USSD');

        // Handle timeout requests
        if ($type === 'Timeout') {
            Log::info('WiFi USSD session timeout', ['sessionId' => $sessionId]);
            return response()->json([
                'SessionId' => $sessionId,
                'Type' => 'release',
                'Message' => 'Session timed out. Please try again.',
                'Label' => 'Session Timeout',
                'DataType' => 'display',
                'FieldType' => 'text'
            ]);
        }

        // Store the session ID in cache for this user
        Cache::put('session_' . $mobile, $sessionId, now()->addMinutes(30));
        Log::info('WiFi USSD session stored in cache', ['mobile' => $mobile, 'sessionId' => $sessionId]);

        // Create or retrieve subscriber
        Customer::firstOrCreate(
            ['phone' => $mobile],
            [
                'username'=> $mobile, // Customer's chosen RADIUS username (defaults to phone)
                'password'=> Str::random(12), // Customer's chosen RADIUS password
                'name' => 'USSD-' . substr($mobile, -4),
                'phone' => $mobile,
                'status' => 'active',
                'registration_date' => now()
            ]
        );

        // Extract user input from USSD string if it's an initiation
        $userInput = $message;
        if ($type === 'Initiation' && strpos($message, '*') !== false) {
            $userInput = substr($message, strrpos($message, '*') + 1);
            // If it's just the shortcode with no input, set empty input
            if (empty($userInput)) {
                $userInput = '';
            }
        }

        try {
            // Configure USSD machine
            $ussd = Ussd::machine()
                ->setSessionId($sessionId)
                ->setFromRequest([
                    'phone_number' => $mobile,
                    'network' => $operator,
                    'platform' => $platform,
                    'sequence' => $sequence,
                    'client_state' => $clientState
                ])
                ->setInput($userInput)
                ->setInitialState(WifiWelcomeAction::class)
                ->setResponse(function (string $message, string $action) use ($sessionId, $clientState) {
                    // Determine response type based on action
                    $responseType = 'response';
                    if ($action === 'end') {
                        $responseType = 'release';
                    } elseif ($action === 'checkout') {
                        $responseType = 'AddToCart';
                    }

                    // Get the label from the first line of the message
                    $label = $this->extractLabel($message);

                    // Build response according to Hubtel API format
                    $response = [
                        'SessionId' => $sessionId,
                        'Type' => $responseType,
                        'Message' => $message,
                        'ClientState' => $clientState ?? '',
                        'DataType' => $action === 'input' ? 'input' : 'display',
                        'FieldType' => 'text'
                    ];

                    // Add Item object for AddToCart responses (WiFi subscription packages)
                    if ($responseType === 'AddToCart') {
                        // Get session-specific cart data using the sessionId as key
                        $sessionKey = 'cart_' . $sessionId;
                        $cartData = Cache::get($sessionKey, []);

                        // Log the session data for debugging
                        Log::info('WiFi AddToCart session data', [
                            'sessionId' => $sessionId,
                            'cartData' => $cartData
                        ]);

                        // Get package details for WiFi subscription
                        $packageId = $cartData['package_id'] ?? session('selected_package_id');
                        $package = $packageId ? Package::find($packageId) : null;

                        // Calculate the actual price with Hubtel fee deduction
                        $originalPrice = $package ? floatval($package->price) : floatval($cartData['item_price'] ?? 0);
                        $hubtelFee = $this->calculateHubtelFee($originalPrice);
                        $finalPrice = round($originalPrice - $hubtelFee, 2);

                        $response['Item'] = [
                            'ItemName' => $package ? "WiFi Package: {$package->name}" : ($cartData['item_name'] ?? 'WiFi Subscription'),
                            'Qty' => 1, // Always 1 for subscriptions
                            'Price' => $finalPrice
                        ];

                        // Add sessionId to the Item for tracking
                        $response['Item']['SessionId'] = $sessionId;

                        // Store package info for service fulfillment
                        if ($package) {
                            Cache::put('package_' . $sessionId, [
                                'package_id' => $package->id,
                                'package_name' => $package->name,
                                'package_price' => $package->price
                            ], now()->addHours(2));
                        }

                        //remove ClientState from response
                        unset($response['ClientState']);
                        // For AddToCart, the Label should match the Message
                        $response['Label'] = "WiFi Package Purchase";
                    }

                    return $response;
                });

            // Run the USSD machine and return the response
            $response = $ussd->run();

            // Log the response for debugging
            Log::info('WiFi USSD Response', $response);

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('WiFi USSD Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            // Return a friendly error message
            return response()->json([
                'SessionId' => $sessionId,
                'Type' => 'release',
                'Message' => 'Sorry, an error occurred. Please try again later.',
                'Label' => 'Error',
                'DataType' => 'display',
                'FieldType' => 'text'
            ]);
        }
    }

    /**
     * Handle service fulfillment from Hubtel after payment
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function serviceFulfillment(Request $request)
    {
        // Log the incoming fulfillment request
        Log::info('WiFi Service Fulfillment Request', $request->all());

        try {
            // Extract data from request
            $sessionId = $request->input('SessionId');
            $orderId = $request->input('OrderId');
            $orderInfo = $request->input('OrderInfo');

            if (!$sessionId || !$orderId || !$orderInfo) {
                throw new \Exception('Missing required parameters');
            }

            // Process the WiFi subscription order
            $subscription = $this->processWifiSubscription($sessionId, $orderId, $orderInfo);

            // Send callback to Hubtel that service was fulfilled
            $this->sendFulfillmentCallback($sessionId, $orderId, 'success');

            return response()->json(['status' => 'success', 'message' => 'WiFi subscription processed']);

        } catch (\Exception $e) {
            Log::error('WiFi Service Fulfillment Error', ['error' => $e->getMessage()]);

            // If there was an error processing the order, send failed callback
            if (isset($sessionId) && isset($orderId)) {
                $this->sendFulfillmentCallback($sessionId, $orderId, 'failed');
            }

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Process the WiFi subscription after payment
     *
     * @param string $sessionId
     * @param string $orderId
     * @param array $orderInfo
     * @return Subscription
     */
    private function processWifiSubscription(string $sessionId, string $orderId, array $orderInfo)
    {
        // Get package info from cache
        $packageData = Cache::get('package_' . $sessionId);
        if (!$packageData) {
            throw new \Exception('Package information not found for session');
        }

        $package = Package::find($packageData['package_id']);
        if (!$package) {
            throw new \Exception('Package not found');
        }

        // Get customer phone from order info
        $customerMobile = $orderInfo['CustomerMobileNumber'] ?? null;
        if (!$customerMobile) {
            throw new \Exception('Customer mobile number not found');
        }

        // Find or create customer
        $customer = Customer::firstOrCreate(
            ['phone' => $customerMobile],
            [
                'username' => $customerMobile,
                'password' => Str::random(12),
                'name' => $orderInfo['CustomerName'] ?? 'USSD-' . substr($customerMobile, -4),
                'phone' => $customerMobile,
                'status' => 'active',
                'registration_date' => now()
            ]
        );

        // Create subscription (PENDING status - not activated yet)
        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'pending', // Pending until manual approval
            'starts_at' => now(),
            'expires_at' => $this->calculateExpiration($package),
            'data_used' => 0,
            'sessions_used' => 0,
            'is_trial' => false,
            'auto_renew' => false,
            'notes' => 'Created via USSD payment'
        ]);

        // Create payment record (COMPLETED status since Hubtel handled payment)
        $payment = Payment::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'package_id' => $package->id,
            'amount' => $orderInfo['Payment']['AmountPaid'] ?? $package->price,
            'currency' => 'GHS',
            'payment_method' => 'mobile_money',
            'mobile_money_provider' => $this->detectPaymentProvider($customerMobile),
            'mobile_number' => $customerMobile,
            'status' => 'pending', // Payment already processed by Hubtel
            'transaction_id' => $orderId,
            'external_reference' => $orderId,
            'internal_reference' => Payment::generateInternalReference(),
            'payment_date' => now(),
            'webhook_data' => $orderInfo
        ]);

        Log::info('WiFi subscription created via USSD', [
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'payment_id' => $payment->id,
            'package_name' => $package->name,
            'amount_paid' => $payment->amount,
            'status' => 'pending_activation'
        ]);

        // Clean up cache
        Cache::forget('package_' . $sessionId);
        Cache::forget('cart_' . $sessionId);

        return $subscription;
    }

    /**
     * Calculate subscription expiration based on package
     */
    private function calculateExpiration(Package $package)
    {
        $start = now();

        return match($package->duration_type) {
            'minutely' => $start->addMinutes($package->duration_value),
            'hourly' => $start->addHours($package->duration_value),
            'daily' => $start->addDays($package->duration_value),
            'weekly' => $start->addWeeks($package->duration_value),
            'monthly' => $start->addMonths($package->duration_value),
            'trial' => $start->addHours($package->trial_duration_hours ?? 24),
            default => $start->addDays(1)
        };
    }

    /**
     * Detect payment provider based on phone number
     */
    private function detectPaymentProvider(string $phoneNumber): string
    {
        // Remove country code and normalize
        $number = preg_replace('/^233/', '', $phoneNumber);

        // MTN prefixes: 24, 25, 53, 54, 55, 59
        if (preg_match('/^(24|25|53|54|55|59)/', $number)) {
            return 'MTN';
        }

        // Vodafone prefixes: 20, 50, 23, 28
        if (preg_match('/^(20|50|23|28)/', $number)) {
            return 'VODAFONE';
        }

        // AirtelTigo prefixes: 26, 27, 56, 57
        if (preg_match('/^(26|27|56|57)/', $number)) {
            return 'AIRTELTIGO';
        }

        // Default to MTN if unknown
        return 'MTN';
    }

    /**
     * Send fulfillment callback to Hubtel
     *
     * @param string $sessionId
     * @param string $orderId
     * @param string $status
     * @return void
     */
    private function sendFulfillmentCallback(string $sessionId, string $orderId, string $status)
    {
        try {
            $callbackUrl = 'https://gs-callback.hubtel.com:9055/callback';

            $response = Http::post($callbackUrl, [
                'SessionId' => $sessionId,
                'OrderId' => $orderId,
                'ServiceStatus' => $status,
                'MetaData' => null
            ]);

            Log::info('WiFi fulfillment callback sent', [
                'status' => $status,
                'response' => $response->body(),
                'status_code' => $response->status()
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('WiFi fulfillment callback error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Calculate Hubtel transaction processing fee based on amount
     *
     * @param float $amount
     * @return float
     */
    private function calculateHubtelFee(float $amount): float
    {
        if ($amount >= 0.01 && $amount <= 1.00) {
            return 0.01;
        } elseif ($amount >= 1.01 && $amount <= 10.00) {
            return 0.10;
        } elseif ($amount >= 10.01 && $amount <= 50.00) {
            return 0.50;
        } elseif ($amount >= 50.01 && $amount <= 500.00) {
            return round($amount * 0.01, 2); // 1% of amount
        }
        
        // For amounts above 500, assume 1% (you may want to clarify this with Hubtel)
        return round($amount * 0.01, 2);
    }

    /**
     * Extract a label from the message for better UI on web/app platforms
     *
     * @param string $message
     * @return string
     */
    private function extractLabel(string $message)
    {
        // Extract first line as label or use default
        $lines = explode('\n', $message);
        return trim($lines[0]) ?: 'WiFi Service Menu';
    }
}
