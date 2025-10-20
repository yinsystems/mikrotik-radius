<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\PaymentSuccessState;
use App\Http\Ussd\States\PaymentFailedState;
use App\Models\Package;
use App\Models\Payment;
use Sparors\Ussd\Action;

class ProcessPaymentAction extends Action
{

    public function run(): string
    {
        $customer = $this->record->get('customer');
        $packageId = $this->record->get('selected_package_id');
        $package = Package::find($packageId);
        $phoneNumber = $customer->phone;

        try {
            // Generate unique transaction reference
            $reference = $this->generateTransactionId();

            // Create subscription for customer
            $subscription = $customer->createSubscription($packageId);

            // Create payment record with all required fields
            $payment = Payment::create([
                'customer_id' => $customer->id,
                'subscription_id' => $subscription->id,
                'package_id' => $package->id,
                'amount' => $package->price,
                'currency' => 'GHS',
                'payment_method' => 'mobile_money',
                'mobile_money_provider' => strtolower($this->detectPaymentOption($phoneNumber)),
                'mobile_number' => $phoneNumber,
                'status' => 'pending',
                'transaction_id' => $reference,
                'internal_reference' => Payment::generateInternalReference(),
                'payment_date' => now(),
            ]);

            // Prepare payment data for Redde
            $paymentData = [
                'amount' => $package->price,
                'phone_number' => $phoneNumber,
                'payment_option' => $this->detectPaymentOption($phoneNumber),
                'description' => "WiFi Package: {$package->name}",
                'reference' => "WIFI_USSD_{$package->id}",
                'client_transaction_id' => $payment->transaction_id,
            ];

            // Keep payment as pending - service fulfillment will complete it

            // Prepare cart data for AddToCart response
            $cart = [
                'item_name' => "WiFi Package: {$package->name}",
                'quantity' => 1,
                'price' => number_format((float)$package->price, 1, '.', ''),
                'total' => number_format((float)$package->price, 1, '.', '')
            ];

            // Prepare order data
            $order = (object)[
                'id' => $payment->id,
                'reference' => $payment->transaction_id,
                'amount' => $package->price,
                'package' => $package->name
            ];

            // Store data in record for success state
            $this->record->setMultiple([
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'subscription_id' => $subscription->id,
                'package_name' => $package->name,
                'payment_status' => 'pending',
                'cart' => $cart,
                'order' => $order
            ]);

            \Log::info("WiFi USSD Payment Created (Pending - Service Fulfillment)", [
                'customer_id' => $customer->id,
                'payment_id' => $payment->id,
                'subscription_id' => $subscription->id,
                'transaction_id' => $payment->transaction_id,
                'phone' => $phoneNumber,
                'amount' => $package->price,
                'package' => $package->name
            ]);

            return PaymentSuccessState::class;

        } catch (\Exception $e) {
            \Log::error('USSD Payment Error: ' . $e->getMessage());

            // Update payment record if it was created
            if (isset($payment)) {
                $payment->update([
                    'status' => 'failed',
                    'failure_reason' => $e->getMessage()
                ]);
            }

            $this->record->set('error', 'Payment processing failed: ' . $e->getMessage());
            return PaymentFailedState::class;
        }
    }

    /**
     * Detect payment option based on phone number prefix
     */
    private function detectPaymentOption(string $phoneNumber): string
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
     * Generate unique transaction ID
     */
    private function generateTransactionId(): string
    {
        return 'USSD_' . strtoupper(uniqid()) . '_' . time();
    }
}
