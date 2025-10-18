<?php

namespace App\Http\Ussd\States;

use App\Http\Ussd\Actions\CheckActiveTokenAction;
use App\Http\Ussd\Actions\WifiWelcomeAction;
use Sparors\Ussd\State;

class ActiveTokenState extends State
{
    protected function beforeRendering(): void
    {
        $token = $this->record->get('current_token');
        $packageName = $this->record->get('package_name');
        $expiresAt = $this->record->get('expires_at');
        $timeRemaining = $this->record->get('time_remaining');

        $this->menu
            ->line('Your Active Token')
            ->lineBreak()
            ->line('WiFi Token: ' . $token)
            ->line('Package: ' . $packageName)
            ->line('Expires: ' . $expiresAt)
            ->line('Time Left: ' . $timeRemaining)
            ->line('0) Refresh')
            ->line('1) Back to Main Menu');
    }

    protected function afterRendering(string $argument): void
    {
        $this->decision->equal('1', WifiWelcomeAction::class);
        $this->decision->equal('0', CheckActiveTokenAction::class);
        // Option 0 or any other input ends the session
    }
}
