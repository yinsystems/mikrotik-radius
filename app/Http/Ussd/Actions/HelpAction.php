<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\HelpState;
use Sparors\Ussd\Action;

class HelpAction extends Action
{
    public function run(): string
    {
        return HelpState::class; // The state after this
    }
}
