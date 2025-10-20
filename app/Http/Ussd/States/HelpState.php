<?php

namespace App\Http\Ussd\States;

use App\Http\Ussd\Actions\HelpAction;
use App\Http\Ussd\Actions\WifiWelcomeAction;
use Sparors\Ussd\State;

class HelpState extends State
{
    protected function beforeRendering(): void
    {
        $this->menu
            ->line('WiFi Connection Steps:')
            ->line('1) Connect to any JayNet WiFi')
            ->line('2) Open browser')
            ->line('3) Goto http://wifi.jaynet.org')
            ->line('3) Enter your phone number and 6-digit token')
            ->line('4) Start browsing')
            ->line('Support: 0123456789')
            ->line('1) Back to Main Menu')
            ->text('0) Exit');
    }

    protected function afterRendering(string $argument): void
    {
        $this->decision->equal('1', WifiWelcomeAction::class);
        $this->decision->equal('0', WifiWelcomeAction::class);
        $this->decision->any( HelpAction::class);
        // Option 0 or any other input ends the session
    }
}
