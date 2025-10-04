<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Package;
use App\Models\RadGroupCheck;
use App\Models\RadGroupReply;

class TestPackageSetup extends Command
{
    protected $signature = 'radius:test-package-setup {package_id?}';
    protected $description = 'Test package RADIUS group setup using the working default template';

    public function handle()
    {
        $packageId = $this->argument('package_id');
        
        if ($packageId) {
            $package = Package::find($packageId);
            if (!$package) {
                $this->error("Package with ID {$packageId} not found");
                return 1;
            }
        } else {
            $package = Package::first();
            if (!$package) {
                $this->error("No packages found in database");
                return 1;
            }
        }

        $this->info("Testing RADIUS group setup for package: {$package->name} (ID: {$package->id})");
        
        // Setup the RADIUS group
        $package->setupRadiusGroup();
        $groupname = $package->getGroupName();
        
        $this->info("RADIUS group created: {$groupname}");
        
        // Check the created RadGroupCheck entries
        $this->info("\n=== RadGroupCheck entries ===");
        $checks = RadGroupCheck::where('groupname', $groupname)->get();
        foreach ($checks as $check) {
            $this->line("{$check->groupname} | {$check->attribute} | {$check->op} | {$check->value}");
        }
        
        // Check the created RadGroupReply entries
        $this->info("\n=== RadGroupReply entries ===");
        $replies = RadGroupReply::where('groupname', $groupname)->get();
        foreach ($replies as $reply) {
            $this->line("{$reply->groupname} | {$reply->attribute} | {$reply->op} | {$reply->value}");
        }
        
        $this->info("\nPackage RADIUS group setup completed successfully!");
        
        return 0;
    }
}