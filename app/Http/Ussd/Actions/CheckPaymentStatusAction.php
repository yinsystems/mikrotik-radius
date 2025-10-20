<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\PaymentSuccessState;
use App\Http\Ussd\States\PaymentFailedState;
use App\Http\Ussd\States\PaymentProcessingState;
use App\Models\Payment;
use App\Models\Subscription;
use Sparors\Ussd\Action;

class CheckPaymentStatusAction extends Action
{
    public function run(): string
    {
        $paymentId = $this->record->get('payment_id');
        $subscriptionId = $this->record->get('subscription_id');
        
        try {
            // Get payment record
            $payment = Payment::find($paymentId);
            
            if (!$payment) {
                $this->record->set('error', 'Payment record not found');
                return PaymentFailedState::class;
            }
            
            // For WiFi USSD payments (bypassing Redde), check payment status directly
            if ($payment->status === 'completed') {
                // Payment successful - ensure subscription is active
                $subscription = Subscription::find($subscriptionId);
                $customer = $payment->customer;
                
                if ($subscription && $subscription->status === 'active') {
                    // Prepare cart data for success state
                    $package = $subscription->package;
                    
                    $cart = [
                        'item_name' => "WiFi Package: {$package->name}",
                        'quantity' => 1,
                        'price' => number_format((float)$package->price, 1, '.', ''),
                        'total' => number_format((float)$package->price, 1, '.', ''),
                        'package_name' => $package->name,
                        'duration' => $this->formatPackageDuration($package),
                        'data_limit' => $package->data_limit ? $this->formatDataLimit($package->data_limit) : 'Unlimited'
                    ];
                    
                    $this->record->setMultiple([
                        'cart' => $cart,
                        'package_name' => $package->name,
                        'order' => (object)[
                            'id' => $payment->id,
                            'reference' => $payment->transaction_id,
                            'amount' => $package->price,
                            'package' => $package->name
                        ]
                    ]);
                    
                    \Log::info("WiFi USSD Payment Status Check - Completed", [
                        'customer_id' => $customer->id,
                        'payment_id' => $payment->id,
                        'subscription_id' => $subscription->id,
                        'package' => $package->name
                    ]);
                    
                    return PaymentSuccessState::class;
                }
                
            } elseif ($payment->status === 'failed') {
                // Payment failed
                $this->record->set('error', $payment->failure_reason ?? 'Payment was declined');
                return PaymentFailedState::class;
                
            } else {
                // Still processing (though this shouldn't happen for WiFi USSD bypassing Redde)
                $this->record->set('payment_status', 'Processing payment...');
                return PaymentProcessingState::class;
            }
            
        } catch (\Exception $e) {
            \Log::error('WiFi USSD Payment Status Check Error: ' . $e->getMessage());
            $this->record->set('error', 'Failed to check payment status');
            return PaymentProcessingState::class;
        }
    }
    
    /**
     * Format package duration for display
     */
    private function formatPackageDuration($package): string
    {
        $value = $package->duration_value;
        $type = $package->duration_type;
        
        return match($type) {
            'minutely' => $value . ' minute(s)',
            'hourly' => $value . ' hour(s)',
            'daily' => $value . ' day(s)',
            'weekly' => $value . ' week(s)',
            'monthly' => $value . ' month(s)',
            'trial' => ($package->trial_duration_hours ?? 24) . ' hour(s) trial',
            default => '1 day'
        };
    }
    
    /**
     * Format data limit for display
     */
    private function formatDataLimit($bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1) . 'GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . 'MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . 'KB';
        }
        return $bytes . 'B';
    }
}
