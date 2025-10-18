<?php

namespace App\Http\Ussd\States;

use App\Http\Ussd\Actions\WifiWelcomeAction;
use Sparors\Ussd\State;

class PaymentFailedState extends State
{
    protected function beforeRendering(): void
    {
        $error = $this->record->get('error', 'Payment failed');
        
        $this->menu
            ->line('Payment Failed')
            ->lineBreak()
            ->line($error)
            ->lineBreak()
            ->line('Please try again later or')
            ->line('contact support.')
            ->lineBreak()
            ->line('1) Back to Main Menu')
            ->text('0) Exit');
    }

    protected function afterRendering(string $argument): void
    {
        $this->decision->equal('1', WifiWelcomeAction::class);
        // Option 0 or any other input ends the session
    }
}
