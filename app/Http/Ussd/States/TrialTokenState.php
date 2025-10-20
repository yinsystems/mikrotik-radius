<?php

namespace App\Http\Ussd\States;

use Sparors\Ussd\State;

class TrialTokenState extends State
{
    protected function beforeRendering(): void
    {
        $token = $this->record->get('trial_token');
        $duration = $this->record->get('trial_duration');

        $this->menu
            ->line('Free Trial Token!')
            ->lineBreak()
            ->line('Your ' . $duration . ' Token: ' . $token)
            ->lineBreak()
            ->line('SMS sent with details.')
            ->line('Connect to: JayNet WIFI')
            ->line('Login at: jaynet.local.com')
            ->lineBreak()
            ->text('Enjoy your free internet!');
    }

    protected function afterRendering(string $argument): void
    {
        // This is a terminal state - session ends here
    }
}
