<?php

namespace App\Http\Ussd\States;

use App\Http\Ussd\Actions\SelectPackageAction;
use App\Http\Ussd\Actions\GenerateTrialTokenAction;
use App\Http\Ussd\Actions\CheckActiveTokenAction;
use App\Http\Ussd\States\HelpState;
use App\Models\Package;
use Sparors\Ussd\State;

class WifiMainState extends State
{
    protected function beforeRendering(): void
    {
        $customer = $this->record->get('customer');
        $customerName = $customer ? $customer->name : 'Guest';

        // Get trial package information dynamically
        $trialPackage = Package::where('is_trial', true)
            ->where('is_active', true)
            ->first();

        $trialText = 'Get Free Trial';
        if ($trialPackage) {
            $duration = $trialPackage->duration_value;
            $durationType = $trialPackage->duration_type;

            // Format duration display
            $durationDisplay = match($durationType) {
                'minutely' => $duration . 'min',
                'hourly' => $duration . 'hr',
                'daily' => $duration . 'day' . ($duration > 1 ? 's' : ''),
                'weekly' => $duration . 'wk' . ($duration > 1 ? 's' : ''),
                'monthly' => $duration . 'mo' . ($duration > 1 ? 's' : ''),
                default => $duration . ' ' . $durationType
            };

            $trialText = "Get Free Trial ({$durationDisplay})";
        }

        $this->menu->line(env("APP_NAME"))
            ->lineBreak()
            ->listing([
                'Buy Internet Package',
                $trialText,
                'My Active Token',
                'Help & Support'
            ]);
    }

    protected function afterRendering(string $argument): void
    {
        $this->decision->equal('1', SelectPackageAction::class);
        $this->decision->equal('2', GenerateTrialTokenAction::class);
        $this->decision->equal('3', CheckActiveTokenAction::class);
        $this->decision->equal('4', HelpState::class);
    }
}
