<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\ActiveTokenState;
use App\Http\Ussd\States\NoActiveTokenState;
use Sparors\Ussd\Action;

class CheckActiveTokenAction extends Action
{
    public function run(): string
    {
        $customer = $this->record->get('customer');
        
        if ($customer->hasActiveSubscription() && $customer->hasValidInternetToken()) {
            $activeSubscription = $customer->getActiveSubscription();
            
            // Calculate time remaining more accurately
            $expiresAt = $activeSubscription->expires_at;
            $now = now();
            
            if ($expiresAt->isPast()) {
                $timeRemaining = 'Expired';
            } else {
                $totalMinutes = $expiresAt->diffInMinutes($now);
                $days = intval($totalMinutes / (24 * 60));
                $hours = intval(($totalMinutes % (24 * 60)) / 60);
                $minutes = $totalMinutes % 60;
                
                if ($days > 0) {
                    $timeRemaining = $days . ' day' . ($days > 1 ? 's' : '');
                } elseif ($hours > 0) {
                    $timeRemaining = $hours . ' hour' . ($hours > 1 ? 's' : '');
                } elseif ($minutes > 0) {
                    $timeRemaining = $minutes . ' min';
                } else {
                    $timeRemaining = 'Less than 1 min';
                }
            }
            
            $this->record->setMultiple([
                'current_token' => $customer->getInternetToken(),
                'package_name' => $activeSubscription->package->name,
                'expires_at' => $activeSubscription->expires_at->format('d/m/Y H:i'),
                'time_remaining' => $timeRemaining
            ]);
            
            return ActiveTokenState::class;
        }
        
        return NoActiveTokenState::class;
    }
}
