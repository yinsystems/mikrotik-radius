<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;

class ExpireSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:expire 
                           {--auto-renew : Also process auto-renewals}
                           {--send-notices : Send expiration notices}
                           {--cleanup : Cleanup expired sessions}
                           {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire old subscriptions and handle auto-renewals';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting subscription management process...');
        
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->warn('🧪 DRY RUN MODE - No changes will be made');
        }

        // 1. Send expiration notices if requested
        if ($this->option('send-notices')) {
            $this->info('📧 Sending expiration notices...');
            
            if (!$isDryRun) {
                $noticeResult = Subscription::sendExpirationNotices();
                $this->displayNoticeResults($noticeResult);
            } else {
                $this->line('   Would send expiration notices to upcoming expired subscriptions');
            }
        }

        // 2. Process auto-renewals if requested
        if ($this->option('auto-renew')) {
            $this->info('🔄 Processing auto-renewals...');
            
            if (!$isDryRun) {
                $renewalResult = Subscription::autoRenewSubscriptions();
                $this->displayRenewalResults($renewalResult);
            } else {
                $autoRenewCount = Subscription::where('status', 'active')
                                            ->where('auto_renew', true)
                                            ->where('expires_at', '<=', now()->addHours(24))
                                            ->where('expires_at', '>', now())
                                            ->count();
                $this->line("   Would auto-renew {$autoRenewCount} subscriptions");
            }
        }

        // 3. Expire old subscriptions
        $this->info('⏰ Expiring old subscriptions...');
        
        if (!$isDryRun) {
            $expireResult = Subscription::expireOldSubscriptions();
            $this->displayExpireResults($expireResult);
        } else {
            $expiredCount = Subscription::where('status', 'active')
                                      ->where('expires_at', '<', now())
                                      ->count();
            $this->line("   Would expire {$expiredCount} subscriptions");
        }

        // 4. Cleanup expired sessions if requested
        if ($this->option('cleanup')) {
            $this->info('🧹 Cleaning up expired sessions...');
            
            if (!$isDryRun) {
                $cleanupResult = Subscription::cleanupExpiredSessions();
                $this->displayCleanupResults($cleanupResult);
            } else {
                $this->line('   Would cleanup old expired sessions');
            }
        }

        $this->info('✅ Subscription management process completed!');
        
        return 0;
    }

    private function displayNoticeResults($result)
    {
        $this->info("   📧 Expiration notices sent:");
        $total = $result['notifications_sent']['total'] ?? 0;
        $this->line("      - Total notices sent: {$total}");
        
        if ($total > 0) {
            $this->line("      - Subscription IDs: " . implode(', ', $result['subscription_ids'] ?? []));
        }
    }

    private function displayRenewalResults($result)
    {
        $this->info("   🔄 Auto-renewal results:");
        $this->line("      - Successfully renewed: {$result['renewed_count']}");
        $this->line("      - Failed renewals: {$result['failed_count']}");
        $this->line("      - Total found: {$result['total_found']}");
    }

    private function displayExpireResults($result)
    {
        $this->info("   ⏰ Expiration results:");
        $this->line("      - Expired subscriptions: {$result['expired_count']}");
        $this->line("      - Total found: {$result['total_found']}");
        
        if ($result['expired_count'] > 0) {
            $this->warn("      ⚠️  {$result['expired_count']} subscriptions have been expired and users disconnected");
        }
    }

    private function displayCleanupResults($result)
    {
        $this->info("   🧹 Cleanup results:");
        $this->line("      - Sessions cleaned: {$result['cleaned_sessions']}");
    }
}
