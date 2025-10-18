<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\PaymentSuccessState;
use App\Http\Ussd\States\PaymentFailedState;
use App\Http\Ussd\States\PaymentProcessingState;
use App\Models\Payment;
use App\Services\ReddePaymentService;
use Sparors\Ussd\Action;

class CheckPaymentStatusAction extends Action
{
    public function __construct(protected ReddePaymentService $reddeService) {}

    public function run(): string
    {
        $paymentId = $this->record->get('payment_id');
        $transactionId = $this->record->get('transaction_id');
        
        try {
            // Get payment record
            $payment = Payment::find($paymentId);
            
            if (!$payment) {
                $this->record->set('error', 'Payment record not found');
                return PaymentFailedState::class;
            }
            
            // Check status with Redde
            $statusResult = $this->reddeService->checkStatus($transactionId);
            
            if ($statusResult['success']) {
                $reddeStatus = $statusResult['status'];
                $mappedStatus = $this->reddeService->mapStatus($reddeStatus);
                
                // Update payment status
                $payment->update([
                    'status' => $mappedStatus,
                    'payment_gateway_response' => array_merge(
                        $payment->payment_gateway_response ?? [],
                        ['status_check' => $statusResult]
                    )
                ]);
                
                if ($mappedStatus === 'completed') {
                    // Payment successful - activate subscription and generate token
                    $subscription = $payment->subscription;
                    $customer = $payment->customer;
                    
                    // Activate subscription
                    $subscription->activate();
                    
                    // Generate internet token
                    $token = $customer->generateInternetToken();
                    
                    $this->record->setMultiple([
                        'wifi_token' => $token,
                        'expires_at' => $subscription->expires_at->format('d/m/Y H:i')
                    ]);
                    
                    \Log::info("WiFi Token Generated via USSD Payment", [
                        'customer_id' => $customer->id,
                        'payment_id' => $payment->id,
                        'transaction_id' => $transactionId,
                        'token' => $token,
                        'package' => $subscription->package->name
                    ]);
                    
                    return PaymentSuccessState::class;
                    
                } elseif ($mappedStatus === 'failed') {
                    // Payment failed
                    $this->record->set('error', $statusResult['reason'] ?? 'Payment was declined');
                    return PaymentFailedState::class;
                    
                } else {
                    // Still processing
                    $this->record->set('payment_status', 'Still processing...');
                    return PaymentProcessingState::class;
                }
                
            } else {
                // Status check failed - assume still processing
                $this->record->set('payment_status', 'Status check failed, please try again');
                return PaymentProcessingState::class;
            }
            
        } catch (\Exception $e) {
            \Log::error('USSD Payment Status Check Error: ' . $e->getMessage());
            $this->record->set('error', 'Failed to check payment status');
            return PaymentProcessingState::class;
        }
    }
}
