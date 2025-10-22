<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\TrialTokenState;
use App\Http\Ussd\States\TrialNotEligibleState;
use App\Models\Customer;
use App\Models\Package;
use App\Services\NotificationService;
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
            
            // Send trial assignment notification and setup instructions via NotificationService
            try {
                /** @var NotificationService $notifier */
                $notifier = app(NotificationService::class);

                // 1) Notify that a free trial package has been activated (humanized expiry)
                $notifier->sendTrialAssignment([
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ], [
                    'name' => $trialPackage->name,
                    'expires_at' => optional($subscription->expires_at)->format('Y-m-d H:i:s'),
                ]);

                // 2) Send setup instructions including WiFi token so user can connect immediately
                $notifier->sendSetupInstructions([
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'internet_token' => $token,
                ], [
                    'username' => $subscription->username,
                    'password' => $subscription->password, // same as internet_token per model accessor
                ]);
            } catch (\Exception $e) {
                \Log::warning('Failed to send trial notifications', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ]);
            }
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
