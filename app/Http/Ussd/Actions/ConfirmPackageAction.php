<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\ConfirmPackageState;
use Sparors\Ussd\Action;

class ConfirmPackageAction extends Action
{
    public function run(): string
    {
        // Simply redirect to package confirmation state
        return ConfirmPackageState::class;
    }
}
