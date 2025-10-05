<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\RadUserGroup;
use App\Models\RadGroupCheck;

class TestPackageSwitch extends Command
{
    protected $signature = 'radius:test-package-switch {phone} {package_id}';
    protected $description = 'Test what happens when a user switches to a different package';

    public function handle()
    {
        $phone = $this->argument('phone');
        $packageId = $this->argument('package_id');

        // Find customer
        $customer = Customer::where('phone', $phone)->first();
        if (!$customer) {
            $this->error("Customer with phone {$phone} not found");
            return 1;
        }

        // Find package
        $package = Package::find($packageId);
        if (!$package) {
            $this->error("Package with ID {$packageId} not found");
            return 1;
        }

        $this->info("=== BEFORE PACKAGE SWITCH ===");
        $this->showUserGroups($customer->username);
        $this->showPackageAttributes($customer->username);

        // Create new subscription (simulating purchase)
        $this->info("\nCreating new subscription for package: {$package->name}");
        $subscription = $customer->createSubscription($packageId);
        $subscription->activate();

        $this->info("\n=== AFTER PACKAGE SWITCH ===");
        $this->showUserGroups($customer->username);
        $this->showPackageAttributes($customer->username);

        return 0;
    }

    private function showUserGroups($username)
    {
        $this->info("User Groups for {$username}:");
        $groups = RadUserGroup::where('username', $username)->get();
        
        if ($groups->isEmpty()) {
            $this->line("  No groups assigned");
            return;
        }

        foreach ($groups as $group) {
            $this->line("  - {$group->groupname} (priority: {$group->priority})");
        }
    }

    private function showPackageAttributes($username)
    {
        $this->info("Package Group Attributes:");
        $groups = RadUserGroup::where('username', $username)->get();
        
        foreach ($groups as $userGroup) {
            $this->line("\nGroup: {$userGroup->groupname}");
            
            $attributes = RadGroupCheck::where('groupname', $userGroup->groupname)->get();
            foreach ($attributes as $attr) {
                $this->line("  {$attr->attribute} {$attr->op} {$attr->value}");
            }
        }
    }
}