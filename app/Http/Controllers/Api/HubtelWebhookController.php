<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Customer;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class HubtelWebhookController extends Controller
{

    /**
     * Handle the incoming request from Hubtel.
     * This handles both regular Hubtel payment callbacks and Hubtel service fulfillment requests
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
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
                    'payment_gateway_response' => $request->all()
                ]);

                // Activate subscription if exists
                if ($payment->subscription) {
                    $this->activateWifiSubscription($payment->subscription, $payment);
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
                        'payment_gateway_response' => $request->all()
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

            // Find the pending payment for this customer and session
            $payment = Payment::where('customer_id', $customer->id)
                ->where('status', 'pending')
                ->where('amount', $amountPaid)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$payment) {
                throw new Exception('No matching pending payment found');
            }

            // Complete the payment using existing ReddeCallback logic
            $payment->update([
                'status' => 'completed',
                'external_reference' => $orderId,
                'payment_date' => now(),
                'payment_gateway_response' => array_merge(
                    $payment->payment_gateway_response ?? [],
                    ['hubtel_service_fulfillment' => $orderInfo]
                )
            ]);

            // Activate subscription using existing subscription methods if it exists
            if ($payment->subscription) {
                $subscription = $payment->subscription;
                
                // Use existing subscription activation logic
                $subscription->update(['status' => 'active']);
                $subscription->createRadiusUser(); // Use existing method
                $subscription->syncRadiusStatus(); // Use existing method
            }

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
     * Send fulfillment callback to Hubtel
     */
    private function sendFulfillmentCallback(string $sessionId, string $orderId, string $status): void
    {
        try {
            $callbackUrl = 'https://gs-callback.hubtel.com:9055/callback';

            $response = Http::post($callbackUrl, [
                'SessionId' => $sessionId,
                'OrderId' => $orderId,
                'ServiceStatus' => $status,
                'MetaData' => null
            ]);

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
