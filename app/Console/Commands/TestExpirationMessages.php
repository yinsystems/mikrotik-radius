<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use App\Models\RadCheck;
use App\Models\RadReply;

class TestExpirationMessages extends Command
{
    protected $signature = 'radius:test-expiration-messages {username?}';
    protected $description = 'Test RADIUS expiration messages for users';

    public function handle()
    {
        $username = $this->argument('username') ?: '0554138989';
        
        $this->info("=== TESTING RADIUS EXPIRATION MESSAGES ===\n");
        
        // Find the subscription
        $subscription = Subscription::whereHas('customer', function($query) use ($username) {
            $query->where('username', $username);
        })->first();
        
        if (!$subscription) {
            $this->error("No subscription found for username: {$username}");
            return 1;
        }
        
        $this->info("📊 Current Status:");
        $this->line("  Username: {$subscription->customer->username}");
        $this->line("  Status: {$subscription->status}");
        $this->line("  Expires: {$subscription->expires_at}");
        $this->line("  Is Expired: " . ($subscription->isExpired() ? "Yes" : "No"));
        
        // Test different scenarios
        $this->info("\n🧪 Testing Different Blocking Scenarios:");
        
        // 1. Test expiration message
        $this->line("\n1. Testing EXPIRATION blocking:");
        RadCheck::blockUserForExpiration($username);
        $this->showUserRadiusStatus($username);
        
        // 2. Test suspension message  
        $this->line("\n2. Testing SUSPENSION blocking:");
        RadCheck::blockUserForSuspension($username);
        $this->showUserRadiusStatus($username);
        
        // 3. Test pending message
        $this->line("\n3. Testing PENDING blocking:");
        RadCheck::blockUser($username, 'Your account is pending activation. Please wait for approval.');
        $this->showUserRadiusStatus($username);
        
        // 4. Test unblock (clear messages)
        $this->line("\n4. Testing UNBLOCK (clear messages):");
        RadCheck::unblockUser($username);
        $this->showUserRadiusStatus($username);
        
        // 5. Test with actual subscription sync
        $this->line("\n5. Testing with actual subscription syncRadiusStatus():");
        $subscription->syncRadiusStatus();
        $this->showUserRadiusStatus($username);
        
        $this->info("\n✅ EXPIRATION MESSAGING TEST COMPLETED!");
        $this->warn("\n💡 IMPORTANT NOTES:");
        $this->line("  • Users will now see clear expiration messages");
        $this->line("  • Messages tell users to login and subscribe to new package");
        $this->line("  • Different statuses have different appropriate messages");
        $this->line("  • Reply-Message attribute provides user feedback");
        
        return 0;
    }
    
    private function showUserRadiusStatus($username)
    {
        // Show RadCheck attributes
        $radCheck = RadCheck::where('username', $username)->get(['attribute', 'op', 'value']);
        $this->line("   RadCheck:");
        if ($radCheck->isEmpty()) {
            $this->line("     (no attributes)");
        } else {
            foreach ($radCheck as $attr) {
                $this->line("     {$attr->attribute} {$attr->op} {$attr->value}");
            }
        }
        
        // Show RadReply attributes (including Reply-Message)
        $radReply = RadReply::where('username', $username)->get(['attribute', 'op', 'value']);
        $this->line("   RadReply:");
        if ($radReply->isEmpty()) {
            $this->line("     (no attributes)");
        } else {
            foreach ($radReply as $attr) {
                $this->line("     {$attr->attribute} {$attr->op} {$attr->value}");
            }
        }
    }
}