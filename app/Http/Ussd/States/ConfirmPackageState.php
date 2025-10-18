<?php

namespace App\Http\Ussd\States;

use App\Http\Ussd\Actions\ProcessPaymentAction;
use App\Http\Ussd\Actions\SelectPackageAction;
use Sparors\Ussd\State;

class ConfirmPackageState extends State
{
    protected function beforeRendering(): void
    {
        $packageName = $this->record->get('selected_package_name');
        $packagePrice = $this->record->get('selected_package_price');
        $packageDuration = $this->record->get('selected_package_duration');
        $packageData = $this->record->get('selected_package_data');
        $packageUsers = $this->record->get('selected_package_users');
        
        // Format price with appropriate decimal places
        $priceDisplay = $packagePrice == floor($packagePrice) ? 
            number_format($packagePrice, 0) : 
            number_format($packagePrice, 2);
        
        $this->menu
            ->line('Confirm Purchase:')
            ->line($packageName)
            ->line('GH¢' . $priceDisplay . ' | ' . $packageData . ' | ' . $packageDuration)
            ->line($packageUsers . ' users max')
            ->lineBreak()
            ->line('1) Confirm & Pay')
            ->line('0) Back to Packages');
    }

    protected function afterRendering(string $argument): void
    {
        $this->decision->equal('1', ProcessPaymentAction::class);
        $this->decision->equal('0', SelectPackageAction::class);
    }
}
