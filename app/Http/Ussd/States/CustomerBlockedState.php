<?php

namespace App\Http\Ussd\States;

use Sparors\Ussd\State;

class CustomerBlockedState extends State
{
    protected function beforeRendering(): void
    {
        $this->menu
            ->line('Account Blocked')
            ->lineBreak()
            ->line('Your account has been')
            ->line('suspended or blocked.')
            ->lineBreak()
            ->line('Please contact support')
            ->line('for assistance.')
            ->lineBreak()
            ->text('Thank you.');
    }

    protected function afterRendering(string $argument): void
    {
        // This is a terminal state - session ends here
    }
}
