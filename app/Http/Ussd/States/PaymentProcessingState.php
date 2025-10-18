<?php

namespace App\Http\Ussd\States;

use App\Http\Ussd\Actions\CheckPaymentStatusAction;
use App\Http\Ussd\Actions\WifiWelcomeAction;
use Sparors\Ussd\State;

class PaymentProcessingState extends State
{
    protected function beforeRendering(): void
    {
        $packageName = $this->record->get('package_name');
        $paymentStatus = $this->record->get('payment_status', 'Processing...');
        
        $this->menu
            ->line('Payment Status')
            ->lineBreak()
            ->line('Package: ' . $packageName)
            ->line('Status: ' . $paymentStatus)
            ->lineBreak()
            ->line('Please approve the mobile')
            ->line('money request on your phone')
            ->line('if you haven\'t already.')
            ->lineBreak()
            ->line('1) Check Payment Status')
            ->line('2) Back to Main Menu')
            ->text('0) Exit');
    }

    protected function afterRendering(string $argument): void
    {
        $this->decision->equal('1', CheckPaymentStatusAction::class);
        $this->decision->equal('2', WifiWelcomeAction::class);
        // Option 0 or any other input ends the session
    }
}
