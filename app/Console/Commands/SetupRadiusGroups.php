<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Package;
use App\Models\RadGroupCheck;
use App\Models\RadGroupReply;

class SetupRadiusGroups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'radius:setup-groups {--force : Force recreation of existing groups}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup RADIUS groups for all packages with proper MikroTik attributes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Setting up RADIUS groups for all packages...');
        
        $packages = Package::all();
        
        if ($packages->isEmpty()) {
            $this->warn('No packages found.');
            return 0;
        }
        
        $this->info("Found {$packages->count()} packages to process.");
        
        $setupCount = 0;
        $skipCount = 0;
        
        foreach ($packages as $package) {
            $groupname = "package_{$package->id}";
            
            // Check if group already exists
            $existingCheck = RadGroupCheck::where('groupname', $groupname)->exists();
            $existingReply = RadGroupReply::where('groupname', $groupname)->exists();
            
            if (($existingCheck || $existingReply) && !$this->option('force')) {
                $this->line("Skipping {$package->name} (group exists, use --force to recreate)");
                $skipCount++;
                continue;
            }
            
            if ($this->option('force')) {
                // Clean up existing entries
                RadGroupCheck::where('groupname', $groupname)->delete();
                RadGroupReply::where('groupname', $groupname)->delete();
                $this->line("Cleaned up existing group for {$package->name}");
            }
            
            // Setup the group
            $package->setupRadiusGroup();
            $setupCount++;
            
            $this->info("✓ Setup RADIUS group for: {$package->name}");
            
            // Show what was created
            $checkCount = RadGroupCheck::where('groupname', $groupname)->count();
            $replyCount = RadGroupReply::where('groupname', $groupname)->count();
            
            $this->line("  - RadGroupCheck entries: {$checkCount}");
            $this->line("  - RadGroupReply entries: {$replyCount}");
        }
        
        $this->newLine();
        $this->info("Summary:");
        $this->info("- Packages processed: {$setupCount}");
        $this->info("- Packages skipped: {$skipCount}");
        
        if ($skipCount > 0 && !$this->option('force')) {
            $this->comment("Use --force option to recreate existing groups.");
        }
        
        $this->info('RADIUS groups setup completed!');
        
        return 0;
    }
}