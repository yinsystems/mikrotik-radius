<?php

namespace App\Http\Ussd\States;

use App\Http\Ussd\Actions\WifiWelcomeAction;
use Sparors\Ussd\State;

class TrialNotEligibleState extends State
{
    protected function beforeRendering(): void
    {
        $error = $this->record->get('trial_error', 'Trial not available');
        
        $this->menu
            ->line('Trial Not Available')
            ->lineBreak()
            ->line($error)
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
