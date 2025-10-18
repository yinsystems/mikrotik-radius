<?php

namespace App\Http\Ussd\States;

use App\Http\Ussd\Actions\WifiWelcomeAction;
use Sparors\Ussd\State;

class NoActiveTokenState extends State
{
    protected function beforeRendering(): void
    {
        $this->menu
            ->line('No Active Token')
            ->lineBreak()
            ->line('You dont have any active')
            ->line('internet subscription.')
            ->lineBreak()
            ->line('Select option 1 to buy a')
            ->line('package or option 2 for')
            ->line('free trial.')
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
