<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\TrialTokenState;
use App\Http\Ussd\States\TrialNotEligibleState;
use App\Models\Customer;
use App\Models\Package;
use Sparors\Ussd\Action;

class GenerateTrialTokenAction extends Action
{
    public function run(): string
    {
        $customer = $this->record->get('customer');
        
        // Check specific eligibility conditions with detailed messages
        if ($customer->hasActiveSubscription()) {
            $this->record->set('trial_error', 'You already have an active subscription. Trial not needed.');
            return TrialNotEligibleState::class;
        }
        
        if ($customer->hasUsedTrialPackage()) {
            $this->record->set('trial_error', 'You have already used your free trial. Please buy a package.');
            return TrialNotEligibleState::class;
        }
        
        try {
            // Find trial package
            $trialPackage = Package::where('is_trial', true)
                ->where('is_active', true)
                ->first();
                
            if (!$trialPackage) {
                $this->record->set('trial_error', 'Trial package not available at this time.');
                return TrialNotEligibleState::class;
            }
            
            // Assign trial package to customer
            $subscription = $customer->assignTrialPackage($trialPackage->id);
            
            // Generate internet token
            $token = $customer->generateInternetToken();
            
            $this->record->setMultiple([
                'trial_token' => $token,
                'trial_duration' => $trialPackage->duration_value . ' ' . $trialPackage->duration_type,
                'subscription_id' => $subscription->id
            ]);
            
            // TODO: Send SMS with trial token details
            \Log::info("Trial WiFi Token Generated via USSD", [
                'customer_id' => $customer->id,
                'phone' => $customer->phone,
                'token' => $token,
                'trial_package' => $trialPackage->name
            ]);
            
            return TrialTokenState::class;
            
        } catch (\Exception $e) {
            \Log::error('USSD Trial Error: ' . $e->getMessage());
            $this->record->set('trial_error', $e->getMessage());
            return TrialNotEligibleState::class;
        }
    }
}
