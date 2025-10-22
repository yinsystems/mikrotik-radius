<?php

namespace App\Http\Ussd\States;

use App\Http\Ussd\Actions\HelpAction;
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
            ->line('#) Main Menu');
    }

    protected function afterRendering(string $argument): void
    {
        $this->decision->equal('#', WifiWelcomeAction::class);
        $this->decision->any( WifiWelcomeAction::class);
    }
}
