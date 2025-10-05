<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\RadCheck;

class MigrateToMaxAllSession extends Command
{
    protected $signature = 'radius:migrate-max-all-session {--dry-run : Show what would be changed without making changes}';
    protected $description = 'Migrate from individual user expiration to package group Max-All-Session';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
            $this->line('');
        }

        $this->info('Migrating from individual expiration to Max-All-Session...');
        
        // Step 1: Update all package groups to include Max-All-Session
        $this->info('Step 1: Updating package groups...');
        $packages = Package::all();
        $packageCount = 0;
        
        foreach ($packages as $package) {
            $this->line("Processing package: {$package->name} (ID: {$package->id})");
            
            if (!$dryRun) {
                $package->setupRadiusGroup();
                $packageCount++;
            }
        }
        
        if (!$dryRun) {
            $this->info("Updated {$packageCount} package groups with Max-All-Session");
        }
        
        // Step 2: Remove individual user expiration attributes
        $this->info('Step 2: Removing individual user expiration attributes...');
        
        $expirationEntries = RadCheck::where('attribute', 'Expiration')->get();
        $this->info("Found {$expirationEntries->count()} user expiration entries");
        
        $removedCount = 0;
        foreach ($expirationEntries as $entry) {
            $this->line("  Removing expiration for user: {$entry->username}");
            
            if (!$dryRun) {
                $entry->delete();
                $removedCount++;
            }
        }
        
        if (!$dryRun) {
            $this->info("Removed {$removedCount} individual expiration entries");
        }
        
        // Step 3: Show summary
        $this->line('');
        $this->info('Migration Summary:');
        $this->table(['Action', 'Count'], [
            ['Package groups updated', $packageCount],
            ['Individual expiration entries removed', $removedCount],
        ]);
        
        if ($dryRun) {
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        } else {
            $this->info('Migration completed successfully!');
            $this->line('');
            $this->info('Next steps:');
            $this->line('1. Test RADIUS authentication with time-based packages');
            $this->line('2. Verify Max-All-Session is working correctly');
            $this->line('3. Monitor session duration limits');
        }
        
        return 0;
    }
}