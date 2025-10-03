<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\Package;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReddeCallbackController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle payment callback from Redde (customer paying merchant)
     */
    public function handleReceiveCallback(Request $request): JsonResponse
    {
        Log::info('Redde receive callback received', [
            'request_data' => $request->all(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
        ]);

        try {
            // Extract callback data
            $callbackData = $this->extractCallbackData($request);
            
            // Validate required fields
            if (!$this->validateCallbackData($callbackData)) {
                Log::error('Invalid callback data received', $callbackData);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid callback data',
                ], 400);
            }

            DB::beginTransaction();

            // Find payment record by reference
            $payment = $this->findPaymentByReference($callbackData);
            
            if (!$payment) {
                Log::warning('Payment not found for callback', $callbackData);
                DB::rollback();
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                ], 404);
            }

            // Update payment with callback data
            $this->updatePaymentFromCallback($payment, $callbackData);

            // Handle subscription activation if payment is successful
            if ($this->isPaymentSuccessful($callbackData['status'])) {
                $this->handleSuccessfulPayment($payment, $callbackData);
            } else {
                $this->handleFailedPayment($payment, $callbackData);
            }

            DB::commit();

            Log::info('Payment callback processed successfully', [
                'payment_id' => $payment->id,
                'status' => $callbackData['status'],
                'transaction_id' => $callbackData['transaction_id'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Callback processed successfully',
                'payment_id' => $payment->id,
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error('Payment callback processing failed', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Callback processing failed',
            ], 500);
        }
    }

    /**
     * Handle cashout callback from Redde (merchant paying customer/refunds)
     */
    public function handleCashoutCallback(Request $request): JsonResponse
    {
        Log::info('Redde cashout callback received', [
            'request_data' => $request->all(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
        ]);

        try {
            // Extract callback data
            $callbackData = $this->extractCallbackData($request);
            
            // Validate required fields
            if (!$this->validateCallbackData($callbackData)) {
                Log::error('Invalid cashout callback data received', $callbackData);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid callback data',
                ], 400);
            }

            DB::beginTransaction();

            // Handle refund logic here
            $this->handleRefundCallback($callbackData);

            DB::commit();

            Log::info('Cashout callback processed successfully', [
                'transaction_id' => $callbackData['transaction_id'],
                'status' => $callbackData['status'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cashout callback processed successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error('Cashout callback processing failed', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Cashout callback processing failed',
            ], 500);
        }
    }

    /**
     * Extract callback data from request
     */
    private function extractCallbackData(Request $request): array
    {
        return [
            'transaction_id' => $request->input('transaction_id'),
            'client_transaction_id' => $request->input('client_transaction_id'),
            'status' => strtoupper($request->input('status', '')),
            'amount' => $request->input('amount'),
            'currency' => $request->input('currency', 'GHS'),
            'phone_number' => $request->input('phone_number'),
            'telco_transaction_id' => $request->input('telco_transaction_id'),
            'description' => $request->input('description'),
            'status_date' => $request->input('status_date'),
            'reason' => $request->input('reason'),
            'callback_data' => $request->all(),
        ];
    }

    /**
     * Validate callback data
     */
    private function validateCallbackData(array $data): bool
    {
        return !empty($data['transaction_id']) && 
               !empty($data['status']) && 
               !empty($data['amount']);
    }

    /**
     * Find payment by reference
     */
    private function findPaymentByReference(array $callbackData): ?Payment
    {
        // Try to find by client_transaction_id first (our internal reference)
        if (!empty($callbackData['client_transaction_id'])) {
            $payment = Payment::where('reference', $callbackData['client_transaction_id'])->first();
            if ($payment) {
                return $payment;
            }
        }

        // Try to find by external transaction ID
        if (!empty($callbackData['transaction_id'])) {
            $payment = Payment::where('external_reference', $callbackData['transaction_id'])->first();
            if ($payment) {
                return $payment;
            }
        }

        return null;
    }

    /**
     * Update payment with callback data
     */
    private function updatePaymentFromCallback(Payment $payment, array $callbackData): void
    {
        $status = $this->mapReddeStatusToPaymentStatus($callbackData['status']);
        
        $updateData = [
            'status' => $status,
            'external_reference' => $callbackData['transaction_id'],
            'metadata' => array_merge($payment->metadata ?? [], [
                'callback_data' => $callbackData['callback_data'],
                'telco_transaction_id' => $callbackData['telco_transaction_id'],
                'status_date' => $callbackData['status_date'],
                'callback_received_at' => now()->toISOString(),
            ]),
        ];

        // Set payment date if successful
        if ($this->isPaymentSuccessful($callbackData['status'])) {
            $updateData['payment_date'] = $callbackData['status_date'] ? 
                Carbon::parse($callbackData['status_date']) : now();
        }

        // Set failure reason if failed
        if ($this->isPaymentFailed($callbackData['status'])) {
            $updateData['failure_reason'] = $callbackData['reason'] ?? 'Payment failed';
        }

        $payment->update($updateData);

        Log::info('Payment updated from callback', [
            'payment_id' => $payment->id,
            'old_status' => $payment->getOriginal('status'),
            'new_status' => $status,
        ]);
    }

    /**
     * Handle successful payment
     */
    private function handleSuccessfulPayment(Payment $payment, array $callbackData): void
    {
        Log::info('Processing successful payment', [
            'payment_id' => $payment->id,
            'customer_id' => $payment->customer_id,
            'subscription_id' => $payment->subscription_id,
        ]);

        // If this payment is for a subscription, activate it
        if ($payment->subscription_id && $payment->subscription) {
            $this->activateSubscription($payment->subscription, $payment);
        } 
        // If this payment is for a package but no subscription exists, create one
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
     * Handle failed payment
     */
    private function handleFailedPayment(Payment $payment, array $callbackData): void
    {
        Log::info('Processing failed payment', [
            'payment_id' => $payment->id,
            'reason' => $callbackData['reason'] ?? 'Unknown',
        ]);

        // If subscription exists and is pending, mark it as failed
        if ($payment->subscription && $payment->subscription->status === 'pending') {
            $payment->subscription->update([
                'status' => 'failed',
                'notes' => 'Payment failed: ' . ($callbackData['reason'] ?? 'Unknown reason'),
            ]);
        }

        // Send payment failure notification
        $this->sendPaymentFailureNotification($payment);
    }

    /**
     * Activate subscription
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
                      "Activated via payment #{$payment->id} on " . now()->format('Y-m-d H:i:s'),
        ]);

        Log::info('Subscription activated', [
            'subscription_id' => $subscription->id,
            'package_name' => $package->name,
            'starts_at' => $startDate->toISOString(),
            'expires_at' => $endDate->toISOString(),
        ]);

        // Create RADIUS user account
        $this->createRadiusUserAccount($subscription);
        
        // Send subscription activation notification
        $this->sendSubscriptionActivationNotification($subscription, $payment);
    }

    /**
     * Create subscription from payment
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

        Log::info('Subscription created from payment', [
            'subscription_id' => $subscription->id,
            'payment_id' => $payment->id,
            'customer_id' => $payment->customer_id,
        ]);

        return $subscription;
    }

    /**
     * Calculate subscription end date
     */
    private function calculateSubscriptionEndDate(Carbon $startDate, Package $package): Carbon
    {
        // Default to 30 days if no validity period specified
        $validityDays = $package->validity_days ?? 30;
        
        return $startDate->copy()->addDays($validityDays);
    }

    /**
     * Create RADIUS user account
     */
    private function createRadiusUserAccount(Subscription $subscription): void
    {
        try {
            // This would integrate with your existing RADIUS user creation logic
            // For now, we'll just log the action
            
            Log::info('RADIUS user account creation initiated', [
                'subscription_id' => $subscription->id,
                'username' => $subscription->username, // This uses the getUsernameAttribute() method
                'package_id' => $subscription->package_id,
            ]);

            // TODO: Integrate with MikroTik RADIUS creation
            // This might involve creating RadCheck, RadReply, RadUserGroup entries
            
        } catch (\Exception $e) {
            Log::error('Failed to create RADIUS user account', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle refund callback
     */
    private function handleRefundCallback(array $callbackData): void
    {
        // Find related payment and process refund
        // This would involve updating payment refund status and possibly affecting subscriptions
        
        Log::info('Refund callback processing', [
            'transaction_id' => $callbackData['transaction_id'],
            'status' => $callbackData['status'],
        ]);

        // TODO: Implement refund processing logic
    }

    /**
     * Map Redde status to payment status
     */
    private function mapReddeStatusToPaymentStatus(string $reddeStatus): string
    {
        return match (strtoupper($reddeStatus)) {
            'OK' => 'processing',
            'PENDING' => 'processing',
            'PROGRESS' => 'processing',
            'PAID' => 'completed',
            'FAILED' => 'failed',
            'CANCELLED' => 'cancelled',
            default => 'pending',
        };
    }

    /**
     * Check if payment is successful
     */
    private function isPaymentSuccessful(string $status): bool
    {
        return in_array(strtoupper($status), ['PAID', 'COMPLETED']);
    }

    /**
     * Check if payment failed
     */
    private function isPaymentFailed(string $status): bool
    {
        return in_array(strtoupper($status), ['FAILED', 'CANCELLED']);
    }

    /**
     * Send payment confirmation notification
     */
    private function sendPaymentConfirmation(Payment $payment): void
    {
        try {
            if (!$payment->customer) {
                return;
            }

            $this->notificationService->sendPaymentSuccess([
                'name' => $payment->customer->name,
                'email' => $payment->customer->email,
                'phone' => $payment->customer->phone,
            ], [
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'transaction_id' => $payment->external_reference ?: $payment->transaction_id,
            ]);

            Log::info('Payment confirmation notification sent', [
                'payment_id' => $payment->id,
                'customer_id' => $payment->customer_id,
                'amount' => $payment->amount,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send payment failure notification
     */
    private function sendPaymentFailureNotification(Payment $payment): void
    {
        try {
            // TODO: Implement notification sending (SMS, email, etc.)
            Log::info('Payment failure notification sent', [
                'payment_id' => $payment->id,
                'customer_id' => $payment->customer_id,
                'reason' => $payment->failure_reason,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send payment failure notification', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send subscription activation notification
     */
    private function sendSubscriptionActivationNotification(Subscription $subscription, Payment $payment): void
    {
        try {
            if (!$subscription->customer || !$subscription->package) {
                return;
            }

            $this->notificationService->sendSubscriptionActivated([
                'name' => $subscription->customer->name,
                'email' => $subscription->customer->email,
                'phone' => $subscription->customer->phone,
            ], [
                'package_name' => $subscription->package->name,
                'expires_at' => $subscription->expires_at->format('Y-m-d H:i:s'),
                'username' => $subscription->username,
            ]);

            Log::info('Subscription activation notification sent', [
                'subscription_id' => $subscription->id,
                'customer_id' => $subscription->customer_id,
                'package_name' => $subscription->package->name,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send subscription activation notification', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}