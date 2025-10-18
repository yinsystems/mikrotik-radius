<?php

namespace App\Http\Ussd\States;

use Sparors\Ussd\State;

class PaymentSuccessState extends State
{
    protected function beforeRendering(): void
    {
        $token = $this->record->get('wifi_token');
        $packageName = $this->record->get('package_name');
        $expiresAt = $this->record->get('expires_at');
        
        $this->menu
            ->line('Payment Successful!')
            ->lineBreak()
            ->line('Your WiFi Token: ' . $token)
            ->line('Package: ' . $packageName)
            ->line('Valid until: ' . $expiresAt)
            ->lineBreak()
            ->line('SMS sent with login details.')
            ->line('Connect to: WiFi-Portal')
            ->line('Login at: wifi.portal.com')
            ->lineBreak()
            ->text('Thank you for using WiFi Portal!');
    }

    protected function afterRendering(string $argument): void
    {
        // This is a terminal state - session ends here
    }
}
