<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Package;
use App\Models\RadGroupCheck;
use App\Models\RadGroupReply;

class CheckPackageAttributes extends Command
{
    protected $signature = 'radius:check-package-attributes {package_id?}';
    protected $description = 'Check all RADIUS attributes for packages';

    public function handle()
    {
        $packageId = $this->argument('package_id');
        
        if ($packageId) {
            $packages = Package::where('id', $packageId)->get();
        } else {
            $packages = Package::all();
        }

        foreach ($packages as $package) {
            $this->info("=== Package: {$package->name} (ID: {$package->id}) ===");
            $this->info("Duration: {$package->duration_value} {$package->duration_type}");
            $this->info("Data Limit: " . ($package->data_limit ? $package->data_limit . " MB" : "Unlimited"));
            $this->info("Bandwidth: {$package->bandwidth_upload}K/{$package->bandwidth_download}K");
            $this->info("Simultaneous Users: {$package->simultaneous_users}");
            $this->info("Is Trial: " . ($package->is_trial ? "Yes" : "No"));
            
            $groupName = $package->getGroupName();
            
            // Check RadGroupCheck attributes
            $this->info("\n--- Group Check Attributes (RadGroupCheck) ---");
            $groupChecks = RadGroupCheck::where('groupname', $groupName)->get();
            if ($groupChecks->isEmpty()) {
                $this->warn("No group check attributes found");
            } else {
                foreach ($groupChecks as $check) {
                    $this->line("  {$check->attribute} {$check->op} {$check->value}");
                }
            }
            
            // Check RadGroupReply attributes
            $this->info("\n--- Group Reply Attributes (RadGroupReply) ---");
            $groupReplies = RadGroupReply::where('groupname', $groupName)->get();
            if ($groupReplies->isEmpty()) {
                $this->warn("No group reply attributes found");
            } else {
                foreach ($groupReplies as $reply) {
                    $this->line("  {$reply->attribute} {$reply->op} {$reply->value}");
                }
            }
            
            $this->info("\n" . str_repeat("=", 60) . "\n");
        }
    }
}