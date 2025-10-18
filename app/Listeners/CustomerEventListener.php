<?php

namespace App\Listeners;

use App\Models\Customer;
use App\Models\Package;
use App\Services\NotificationService;

class CustomerEventListener
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function created(Customer $customer)
    {
        // Set registration date if not already set
        if (!$customer->registration_date) {
            $customer->update(['registration_date' => now()]);
        }

        // Auto-assign trial package for new customers
//        $this->assignTrialPackage($customer);

        // Send welcome notification with account details
//        $this->sendWelcomeNotification($customer);
    }

    public function updated(Customer $customer)
    {
        // Check if status changed
        $dirty = $customer->getDirty();

        if (isset($dirty['status'])) {
            $oldStatus = $customer->getOriginal('status');
            $newStatus = $customer->status;

            // Handle status change logic
            switch ($newStatus) {
                case 'suspended':
                    if ($oldStatus !== 'suspended') {
                        // Customer was just suspended - sync all RADIUS status
                        $customer->syncAllRadiusStatus();
                    }
                    break;

                case 'active':
                    if (in_array($oldStatus, ['suspended', 'blocked'])) {
                        // Customer was resumed/unblocked - sync all RADIUS status
                        $customer->syncAllRadiusStatus();
                    }
                    break;

                case 'blocked':
                    if ($oldStatus !== 'blocked') {
                        // Customer was just blocked - sync all RADIUS status and terminate sessions
                        $customer->syncAllRadiusStatus();
                        $customer->terminateAllActiveSessions('Customer Status Changed to Blocked');
                    }
                    break;
            }
        }
    }

    public function deleting(Customer $customer)
    {
        // Clean up all RADIUS users before deletion
        $customer->deleteAllRadiusUsers();
    }

    /**
     * Auto-assign trial package to new customers
     */
    protected function assignTrialPackage(Customer $customer)
    {
        try {
            // Check if auto-assignment is enabled
            if (!config('hotspot.auto_assign_trial', true)) {
                return;
            }

            // Check if customer already has any subscriptions
            if ($customer->subscriptions()->count() > 0) {
                return;
            }

            // Check if customer is eligible for trial
            if (!$customer->isEligibleForTrial()) {
                return;
            }

            // Find available trial packages
            $trialPackage = Package::where('is_trial', true)
                                 ->where('is_active', true)
                                 ->first();

            if ($trialPackage) {
                // Create trial subscription
                $subscription = $customer->createSubscription($trialPackage->id);

                // Activate the trial subscription immediately
                $subscription->activate();

                // Generate internet token for trial users
                if (!$customer->hasValidInternetToken()) {
                    $customer->generateInternetToken();
                }

                // Log the trial assignment
                $customer->update([
                    'notes' => ($customer->notes ? $customer->notes . "\n" : '') .
                              "Auto-assigned trial package '{$trialPackage->name}' on " . now()->format('Y-m-d H:i:s')
                ]);

                \Log::info("Trial package '{$trialPackage->name}' auto-assigned to customer {$customer->id}");

                // Send trial assignment notification
                $this->sendTrialAssignmentNotification($customer, $trialPackage, $subscription);
            }
        } catch (\Exception $e) {
            // Log error but don't fail customer creation
            \Log::error('Failed to assign trial package to customer ' . $customer->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Send welcome notification with account details
     */
    protected function sendWelcomeNotification(Customer $customer): void
    {
        try {
            // Get default credentials for the customer
            $credentials = [
                'username' => $customer->username ?: $customer->getDefaultUsername(),
                'password' => $customer->password ?: 'Please contact support for password'
            ];

            $this->notificationService->sendWelcome([
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ], $credentials);

            \Log::info("Welcome notification sent to customer {$customer->id}");

        } catch (\Exception $e) {
            \Log::error("Failed to send welcome notification to customer {$customer->id}: " . $e->getMessage());
        }
    }

    /**
     * Send trial package assignment notification
     */
    protected function sendTrialAssignmentNotification(Customer $customer, Package $package, $subscription): void
    {
        try {
            $this->notificationService->sendTrialAssignment([
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ], [
                'name' => $package->name,
                'expires_at' => $subscription->expires_at->format('Y-m-d H:i:s'),
            ]);

            \Log::info("Trial assignment notification sent to customer {$customer->id} for package {$package->id}");

        } catch (\Exception $e) {
            \Log::error("Failed to send trial assignment notification to customer {$customer->id}: " . $e->getMessage());
        }
    }
}
