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
            ->line('Steps:')
            ->line('1) Connect to JayNet WiFi')
            ->line('2) Open browser')
            ->line('3) Goto jaynet.local.com')
            ->line('4) Enter phone number and 6-digit token')
            ->line('Support: 0554138989')
            ->line('1) Back to Main Menu')
            ->text('0) Exit');
    }

    protected function afterRendering(string $argument): void
    {
        $this->decision->equal('1', WifiWelcomeAction::class);
        $this->decision->equal('0', WifiWelcomeAction::class);
        $this->decision->any( HelpAction::class);
    }
}
