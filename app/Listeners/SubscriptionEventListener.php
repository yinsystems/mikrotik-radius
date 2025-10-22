<?php

namespace App\Listeners;

use App\Models\Subscription;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Model;

class SubscriptionEventListener
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function created(Subscription $subscription)
    {
        // Create RADIUS user using customer credentials
        $subscription->createRadiusUser();
        
        // Sync initial status
        $subscription->syncRadiusStatus();
        
        // Send setup instructions notification
        $this->sendSetupInstructions($subscription);
    }

    public function updated(Subscription $subscription)
    {
        // Check what changed
        $dirty = $subscription->getDirty();
        
        // If package changed, update RADIUS group assignment
        if (isset($dirty['package_id'])) {
            $subscription->updateRadiusUser();
        }
        
        // If status changed, sync RADIUS status
        if (isset($dirty['status'])) {
            $subscription->syncRadiusStatus();
        }
        
        // If expiration changed, update RADIUS expiration
        if (isset($dirty['expires_at'])) {
            $subscription->updateRadiusUser();
        }
        
        // If username or password changed, update RADIUS credentials
        if (isset($dirty['username']) || isset($dirty['password'])) {
            $subscription->updateRadiusUser();
        }
    }

    public function deleting(Subscription $subscription)
    {
        // Clean up RADIUS user before deletion
        $subscription->deleteRadiusUser();
    }

    /**
     * Send setup instructions notification
     */
    protected function sendSetupInstructions(Subscription $subscription): void
    {
        try {
            if (!$subscription->customer) {
                return;
            }

            $credentials = [
                'username' => $subscription->username,
                'password' => $subscription->password,
            ];

            $this->notificationService->sendSetupInstructions([
                'name' => $subscription->customer->name,
                'email' => $subscription->customer->email,
                'phone' => $subscription->customer->phone,
                // Include the customer's internet token so templates can populate {token}
                'internet_token' => $subscription->customer->internet_token,
            ], $credentials);

            \Log::info("Setup instructions sent for subscription {$subscription->id}");
            
        } catch (\Exception $e) {
            \Log::error("Failed to send setup instructions for subscription {$subscription->id}: " . $e->getMessage());
        }
    }
}