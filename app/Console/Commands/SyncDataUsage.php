<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DataUsage;
use Carbon\Carbon;

class SyncDataUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usage:sync 
                           {--date= : Specific date to sync (YYYY-MM-DD format)}
                           {--days=1 : Number of days to sync from today backwards}
                           {--check-limits : Check data limits after sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync data usage from RADIUS accounting records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Starting data usage synchronization...');

        $date = $this->option('date');
        $days = $this->option('days');
        $checkLimits = $this->option('check-limits');

        if ($date) {
            // Sync specific date
            try {
                $syncDate = Carbon::createFromFormat('Y-m-d', $date);
                $this->syncForDate($syncDate);
            } catch (\Exception $e) {
                $this->error("Invalid date format. Please use YYYY-MM-DD format.");
                return 1;
            }
        } else {
            // Sync multiple days
            $this->info("Syncing data usage for the last {$days} day(s)...");
            
            for ($i = 0; $i < $days; $i++) {
                $syncDate = now()->subDays($i);
                $this->syncForDate($syncDate);
            }
        }

        if ($checkLimits) {
            $this->info('🔍 Checking data limits...');
            $this->checkDataLimits();
        }

        $this->info('✅ Data usage synchronization completed!');
        return 0;
    }

    private function syncForDate($date)
    {
        $this->line("   📅 Syncing usage for {$date->format('Y-m-d')}...");
        
        $syncedCount = DataUsage::syncAllUsageFromRadius($date);
        
        $this->line("      ✓ Synced {$syncedCount} usage records");
    }

    private function checkDataLimits()
    {
        // Get today's usage records and check limits
        $todayUsage = DataUsage::whereDate('date', today())
                              ->with(['subscription.package'])
                              ->get();

        $limitsExceeded = 0;
        $warningsTriggered = 0;

        foreach ($todayUsage as $usage) {
            if ($usage->checkDataLimitExceeded()) {
                $limitsExceeded++;
                $this->warn("      ⚠️  Data limit exceeded for user: {$usage->username}");
            }

            // Check for warning level (90%)
            $subscription = $usage->subscription;
            if ($subscription && $subscription->package->data_limit) {
                $usagePercentage = ($usage->total_mb / $subscription->package->data_limit) * 100;
                if ($usagePercentage >= 90 && $usagePercentage < 100) {
                    $warningsTriggered++;
                    $this->line("      📊 Warning: {$usage->username} at {$usagePercentage}% of data limit");
                }
            }
        }

        $this->line("      📈 Limits exceeded: {$limitsExceeded}");
        $this->line("      ⚠️  Warnings triggered: {$warningsTriggered}");
    }
}
