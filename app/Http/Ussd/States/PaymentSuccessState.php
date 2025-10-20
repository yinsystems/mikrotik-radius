<?php

namespace App\Http\Ussd\States;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Sparors\Ussd\State;

class PaymentSuccessState extends State
{
    // Set action to checkout to trigger AddToCart response
    protected $action = 'checkout';

    protected function beforeRendering(): void
    {
        // Get cart data prepared by ProcessWifiPaymentAction
        $cart = $this->record->get('cart');
        $order = $this->record->get('order');
        $packageName = $this->record->get('package_name');

        // Format price values to 1 decimal place for Hubtel
        if (isset($cart['price'])) {
            $cart['price'] = number_format((float)$cart['price'], 1, '.', '');
        }
        if (isset($cart['total'])) {
            $cart['total'] = number_format((float)$cart['total'], 1, '.', '');
        }

        // Update the cart in the record
        $this->record->set('cart', $cart);

        Log::info('WiFi Cart data in PaymentSuccessState (formatted):', $cart ?? []);

        // Get the sessionId from the request for Hubtel AddToCart
        $sessionId = request('SessionId');

        // Prepare cart data for AddToCart response
        $cartData = [
            'item_name' => $cart['item_name'] ?? "WiFi Package: {$packageName}",
            'item_quantity' => $cart['quantity'] ?? 1,
            'item_price' => round((float)($cart['total'] ?? 0), 2)
        ];

        // Store in cache with sessionId as key for Hubtel to retrieve
        Cache::put('cart_' . $sessionId, $cartData, now()->addMinutes(30));

        // Also store in session for backward compatibility
        session($cartData);

        // Set success message for the user

        $this->menu
            ->line('Checkout')
            ->lineBreak()
            ->line('Package: ' . $packageName)
            ->line('Please approve the prompt')
            ->line("WIFI Token would be sent via SMS after payment")
            ->line("Or Redial Code Goto Option (3)");

        Log::info('WiFi USSD Payment Success', [
            'session_id' => $sessionId,
            'order_id' => $order->id ?? null,
            'package' => $packageName,
            'cart_data' => $cartData
        ]);
    }

    protected function afterRendering(string $argument): void
    {
        // Session ends here with checkout action - no further processing needed
        Log::info('WiFi USSD session completed successfully');
    }
}
