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
            ->line('1) Connect to JayNet WiFi')
            ->line('2) Open browser')
            ->line('3) Goto jaynet.local.com')
            ->line('4) Enter phone number and 6-digit WiFi token')
            ->line('#) Back to Main Menu')
            ->text('0) Exit');
    }

    protected function afterRendering(string $argument): void
    {
        $this->decision->equal('#', WifiWelcomeAction::class);
        $this->decision->any( HelpAction::class);
    }
}
