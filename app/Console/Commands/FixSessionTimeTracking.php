<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use App\Models\Package;

class FixSessionTimeTracking extends Command
{
    protected $signature = 'radius:fix-session-tracking';
    protected $description = 'Fix session time tracking by implementing Max-All-Session';

    public function handle()
    {
        $this->info("🔧 FIXING SESSION TIME TRACKING ISSUE");
        $this->warn("Problem: Timer resets on reconnection with Session-Timeout");
        $this->info("Solution: Using Max-All-Session for cumulative time tracking\n");
        
        // 1. Update package groups (remove Session-Timeout)
        $this->info("📦 Updating package groups...");
        Package::setupAllRadiusGroups();
        $this->line("✅ Package groups updated (Session-Timeout removed)");
        
        // 2. Update active subscriptions (add Max-All-Session to individual users)
        $this->info("\n👤 Updating active subscriptions...");
        $activeSubscriptions = Subscription::where('status', 'active')->get();
        $updatedCount = 0;
        
        foreach ($activeSubscriptions as $subscription) {
            try {
                $subscription->updateRadiusUser();
                $updatedCount++;
                $this->line("  ✅ Updated user: {$subscription->username}");
            } catch (\Exception $e) {
                $this->error("  ❌ Failed to update user: {$subscription->username} - " . $e->getMessage());
            }
        }
        
        $this->info("\n📊 Summary:");
        $this->line("  - Total active subscriptions: " . $activeSubscriptions->count());
        $this->line("  - Successfully updated: {$updatedCount}");
        
        // 3. Verification
        $this->info("\n🔍 Verification:");
        $testUser = $activeSubscriptions->first();
        if ($testUser) {
            $this->call('radius:test-package-switch', [
                'username' => $testUser->username,
                'package_id' => $testUser->package_id
            ]);
        }
        
        $this->info("\n✅ SESSION TIME TRACKING FIX COMPLETED!");
        $this->warn("\n⚠️  IMPORTANT NOTES:");
        $this->line("  • Max-All-Session tracks CUMULATIVE time across all sessions");
        $this->line("  • Users will be disconnected when total time is used up");
        $this->line("  • Timer resets will NO LONGER give extra time");
        $this->line("  • Time tracking is now properly enforced!");
        
        return 0;
    }
}