<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\Package;
use App\Services\NotificationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class HubtelWebhookController extends Controller
{

    /**
     * Handle the incoming request from Hubtel.
     * This handles both regular Hubtel payment callbacks and Hubtel service fulfillment requests
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Log the incoming request for debugging
        Log::info('Hubtel Webhook Request', $request->all());

        // Check if this is a service fulfillment request from Hubtel Programmable Services
        if ($request->has('SessionId') && $request->has('OrderId') && $request->has('OrderInfo')) {
            return $this->handleServiceFulfillment($request);
        }

        // Otherwise, handle as a regular payment callback
        return $this->handlePaymentCallback($request);
    }

    /**
     * Handle regular payment callback from Hubtel
     *
     * @param Request $request
     * @return JsonResponse
     */
    private function handlePaymentCallback(Request $request): JsonResponse
    {
        Log::info('Hubtel payment callback received', [
            'request_data' => $request->all(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
        ]);

        try {
            $responseCode = $request->input('ResponseCode');

            if ($responseCode == "0000") {
                // Payment successful
                $clientReference = $request->input('Data.ClientReference');
                $amount = $request->input('Data.Amount');

                // Find payment by transaction ID
                $payment = Payment::where('transaction_id', $clientReference)
                    ->where('status', 'pending')
                    ->first();

                if (!$payment) {
                    Log::warning('Payment not found for Hubtel callback', [
                        'client_reference' => $clientReference,
                        'amount' => $amount
                    ]);
                    return response()->json(['status' => false, 'message' => 'Payment not found'], 404);
                }

                // Validate amount
                if (floatval($amount) < $payment->amount) {
                    Log::warning('Payment amount mismatch', [
                        'expected' => $payment->amount,
                        'received' => $amount,
                        'payment_id' => $payment->id
                    ]);
                    return response()->json(['status' => false, 'message' => 'Invalid amount'], 400);
                }

                DB::beginTransaction();

                // Update payment status
                $payment->update([
                    'status' => 'completed',
                    'external_reference' => $request->input('Data.TransactionId'),
                    'payment_date' => now(),
                    'metadata' => $request->all()
                ]);

                // Activate subscription if exists using same logic as ReddeCallback
                if ($payment->subscription) {
                    $this->handleSuccessfulPayment($payment);
                }

                DB::commit();

                Log::info('Hubtel payment callback processed successfully', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $clientReference,
                    'amount' => $amount
                ]);

                return response()->json(['status' => true, 'message' => 'Payment processed successfully']);

            } else {
                // Payment failed
                $clientReference = $request->input('Data.ClientReference');

                $payment = Payment::where('transaction_id', $clientReference)
                    ->where('status', 'pending')
                    ->first();

                if ($payment) {
                    $payment->update([
                        'status' => 'failed',
                        'failure_reason' => 'Payment failed with response code: ' . $responseCode,
                        'metadata' => $request->all()
                    ]);

                    // Mark subscription as failed if exists
                    if ($payment->subscription) {
                        $payment->subscription->update(['status' => 'failed']);
                    }

                    Log::info('Hubtel payment marked as failed', [
                        'payment_id' => $payment->id,
                        'response_code' => $responseCode
                    ]);
                }

                return response()->json(['status' => false, 'message' => 'Payment failed']);
            }

        } catch (Exception $e) {
            DB::rollback();
            Log::error('Hubtel payment callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json(['status' => false, 'message' => 'Callback processing failed'], 500);
        }
    }

    /**
     * Handle service fulfillment from Hubtel Programmable Services after payment
     *
     * @param Request $request
     * @return JsonResponse
     */
    private function handleServiceFulfillment(Request $request): JsonResponse
    {
        Log::info('Hubtel Service Fulfillment Request', $request->all());

        try {
            // Extract data from request
            $sessionId = $request->input('SessionId');
            $orderId = $request->input('OrderId');
            $orderInfo = $request->input('OrderInfo');

            // Basic validation
            if (!$sessionId || !$orderId || !$orderInfo) {
                throw new Exception('Missing required parameters');
            }

            // Check payment was successful
            $paymentInfo = $orderInfo['Payment'] ?? null;
            if (!$paymentInfo || !($paymentInfo['IsSuccessful'] ?? false)) {
                throw new Exception('Payment was not successful');
            }

            $customerMobile = $orderInfo['CustomerMobileNumber'] ?? null;
            $amountPaid = floatval($paymentInfo['AmountPaid'] ?? 0);

            if (!$customerMobile || $amountPaid <= 0) {
                throw new Exception('Invalid customer mobile or amount');
            }

            DB::beginTransaction();

            // Find customer and pending payment
            $customer = Customer::where('phone', $customerMobile)->first();
            if (!$customer) {
                throw new Exception('Customer not found');
            }

            // Find the pending payment for this session
            $payment = Payment::where('transaction_id', $sessionId)
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$payment) {
                throw new Exception('No matching pending payment found');
            }

            // Update payment status to completed
            $payment->update([
                'status' => 'completed',
                'payment_date' => now(),
                'external_reference' => $orderId,
            ]);

            // Handle successful payment using our local method
            $this->handleSuccessfulPayment($payment);

            DB::commit();

            // Send callback to Hubtel
            $this->sendFulfillmentCallback($sessionId, $orderId, 'success');

            Log::info('Hubtel service fulfillment completed', [
                'payment_id' => $payment->id,
                'customer_id' => $customer->id,
                'amount' => $amountPaid
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Service fulfilled successfully',
                'payment_id' => $payment->id
            ]);

        } catch (Exception $e) {
            DB::rollback();

            Log::error('Hubtel Service Fulfillment Error', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            // Send failed callback to Hubtel
            if (isset($sessionId) && isset($orderId)) {
                $this->sendFulfillmentCallback($sessionId, $orderId, 'failed');
            }

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Handle successful payment - copied from ReddeCallbackController logic
     */
    private function handleSuccessfulPayment(Payment $payment): void
    {
        Log::info('Processing successful Hubtel payment', [
            'payment_id' => $payment->id,
            'customer_id' => $payment->customer_id,
            'subscription_id' => $payment->subscription_id,
        ]);

        // If this payment is for a subscription, activate it
        if ($payment->subscription_id && $payment->subscription) {
            $this->activateSubscription($payment->subscription, $payment);
        } // If this payment is for a package but no subscription exists, create one
        elseif ($payment->package_id && $payment->customer_id && !$payment->subscription_id) {
            $subscription = $this->createSubscriptionFromPayment($payment);
            if ($subscription) {
                $payment->update(['subscription_id' => $subscription->id]);
                $this->activateSubscription($subscription, $payment);
            }
        }

        // Send payment confirmation notification
        $this->sendPaymentConfirmation($payment);
    }

    /**
     * Create subscription from payment - copied from ReddeCallbackController logic
     */
    private function createSubscriptionFromPayment(Payment $payment): ?Subscription
    {
        if (!$payment->customer || !$payment->package) {
            Log::warning('Cannot create subscription: missing customer or package', [
                'payment_id' => $payment->id,
                'customer_id' => $payment->customer_id,
                'package_id' => $payment->package_id,
            ]);
            return null;
        }

        $subscription = Subscription::create([
            'customer_id' => $payment->customer_id,
            'package_id' => $payment->package_id,
            'status' => 'pending',
        ]);

        Log::info('Subscription created from Hubtel payment', [
            'subscription_id' => $subscription->id,
            'payment_id' => $payment->id,
            'customer_id' => $payment->customer_id,
        ]);

        return $subscription;
    }

    /**
     * Activate subscription - copied from ReddeCallbackController logic
     */
    private function activateSubscription(Subscription $subscription, Payment $payment): void
    {
        // Calculate subscription dates based on package
        $package = $subscription->package ?? $payment->package;

        if (!$package) {
            Log::warning('No package found for subscription activation', [
                'subscription_id' => $subscription->id,
                'payment_id' => $payment->id,
            ]);
            return;
        }

        $startDate = now();
        $endDate = $this->calculateSubscriptionEndDate($startDate, $package);

        $subscription->update([
            'status' => 'active',
            'starts_at' => $startDate,
            'expires_at' => $endDate,
            'data_used' => 0,
            'notes' => ($subscription->notes ? $subscription->notes . "\n" : '') .
                "Activated via Hubtel payment #{$payment->id} on " . now()->format('Y-m-d H:i:s'),
        ]);

        Log::info('Subscription activated via Hubtel', [
            'subscription_id' => $subscription->id,
            'package_name' => $package->name,
            'starts_at' => $startDate->toISOString(),
            'expires_at' => $endDate->toISOString(),
        ]);

        // Note: RADIUS user account creation and syncing is handled automatically by SubscriptionEventListener
        // when the subscription is updated above

        // Send subscription activation notification
        $this->sendSubscriptionActivationNotification($subscription, $payment);
    }

    /**
     * Calculate subscription end date - copied from ReddeCallbackController logic
     */
    private function calculateSubscriptionEndDate(Carbon $startDate, Package $package): Carbon
    {
        return match ($package->duration_type) {
            'minutely' => $startDate->copy()->addMinutes($package->duration_value),
            'hourly' => $startDate->copy()->addHours($package->duration_value),
            'daily' => $startDate->copy()->addDays($package->duration_value),
            'weekly' => $startDate->copy()->addWeeks($package->duration_value),
            'monthly' => $startDate->copy()->addMonths($package->duration_value),
            'trial' => $startDate->copy()->addHours($package->trial_duration_hours ?? 1),
            default => $startDate->copy()->addDays(1)
        };
    }


    /**
     * Send payment confirmation - copied from ReddeCallbackController logic
     */
    private function sendPaymentConfirmation(Payment $payment): void
    {
        try {
            if (!$payment->customer) {
                return;
            }

            $notificationService = app(NotificationService::class);
            $notificationService->sendPaymentSuccess([
                'name' => $payment->customer->name,
                'email' => $payment->customer->email,
                'phone' => $payment->customer->phone,
            ], [
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'transaction_id' => $payment->external_reference ?: $payment->transaction_id,
            ]);

            Log::info('Hubtel payment confirmation notification sent', [
                'payment_id' => $payment->id,
                'customer_id' => $payment->customer_id,
                'amount' => $payment->amount,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send Hubtel payment confirmation', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send subscription activation notification - copied from ReddeCallbackController logic
     */
    private function sendSubscriptionActivationNotification(Subscription $subscription, Payment $payment): void
    {
        try {
            if (!$subscription->customer || !$subscription->package) {
                return;
            }

            $notificationService = app(NotificationService::class);
            $notificationService->sendSubscriptionActivated([
                'name' => $subscription->customer->name,
                'email' => $subscription->customer->email,
                'phone' => $subscription->customer->phone,
            ], [
                'package_name' => $subscription->package->name,
                'expires_at' => $subscription->expires_at->format('Y-m-d H:i:s'),
                'username' => $subscription->username,
                'token' => $subscription->customer->internet_token,
            ]);

            Log::info('Hubtel subscription activation notification sent', [
                'subscription_id' => $subscription->id,
                'customer_id' => $subscription->customer_id,
                'package_name' => $subscription->package->name,
                'token' => $subscription->customer->internet_token,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send Hubtel subscription activation notification', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send fulfillment callback to Hubtel
     */
    private function sendFulfillmentCallback(string $sessionId, string $orderId, string $status): void
    {
        try {
            $callbackUrl = 'https://gs-callback.hubtel.com/callback';

            $response = Http::post($callbackUrl, [
                'SessionId' => $sessionId,
                'OrderId' => $orderId,
                'ServiceStatus' => $status,
                'MetaData' => null
            ], ["port" => 9055]);

            Log::info('Hubtel fulfillment callback sent', [
                'session_id' => $sessionId,
                'order_id' => $orderId,
                'status' => $status,
                'response_status' => $response->status(),
                'response_body' => $response->body()
            ]);

        } catch (Exception $e) {
            Log::error('Hubtel fulfillment callback error', [
                'session_id' => $sessionId,
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
        }
    }

}
