<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RadCheck;
use App\Models\RadReply;
use App\Models\RadGroupCheck;
use App\Models\RadGroupReply;
use App\Models\RadUserGroup;

class ScanRadiusUser extends Command
{
    protected $signature = 'radius:scan-user {username=testuser}';
    protected $description = 'Scan all RADIUS tables for a specific user';

    public function handle()
    {
        $username = $this->argument('username');
        
        $this->info("=== RADIUS SCAN FOR USER: {$username} ===");
        
        // 1. RadCheck (User Authentication)
        $this->line("\n1. RadCheck (User Authentication):");
        $checks = RadCheck::where('username', $username)->get();
        if ($checks->isEmpty()) {
            $this->warn("   No RadCheck entries found");
        } else {
            foreach ($checks as $check) {
                $this->line("   {$check->username} | {$check->attribute} | {$check->op} | {$check->value}");
            }
        }
        
        // 2. RadReply (User Replies)
        $this->line("\n2. RadReply (User Replies):");
        $replies = RadReply::where('username', $username)->get();
        if ($replies->isEmpty()) {
            $this->warn("   No RadReply entries found");
        } else {
            foreach ($replies as $reply) {
                $this->line("   {$reply->username} | {$reply->attribute} | {$reply->op} | {$reply->value}");
            }
        }
        
        // 3. RadUserGroup (User Group Assignment)
        $this->line("\n3. RadUserGroup (Group Assignment):");
        $userGroups = RadUserGroup::where('username', $username)->get();
        if ($userGroups->isEmpty()) {
            $this->warn("   No RadUserGroup entries found");
        } else {
            foreach ($userGroups as $userGroup) {
                $this->line("   {$userGroup->username} | {$userGroup->groupname} | Priority: {$userGroup->priority}");
                
                // Show group check attributes
                $this->line("\n   Group Check Attributes for '{$userGroup->groupname}':");
                $groupChecks = RadGroupCheck::where('groupname', $userGroup->groupname)->get();
                if ($groupChecks->isEmpty()) {
                    $this->warn("     No RadGroupCheck entries found");
                } else {
                    foreach ($groupChecks as $groupCheck) {
                        $this->line("     {$groupCheck->groupname} | {$groupCheck->attribute} | {$groupCheck->op} | {$groupCheck->value}");
                    }
                }
                
                // Show group reply attributes
                $this->line("\n   Group Reply Attributes for '{$userGroup->groupname}':");
                $groupReplies = RadGroupReply::where('groupname', $userGroup->groupname)->get();
                if ($groupReplies->isEmpty()) {
                    $this->warn("     No RadGroupReply entries found");
                } else {
                    foreach ($groupReplies as $groupReply) {
                        $this->line("     {$groupReply->groupname} | {$groupReply->attribute} | {$groupReply->op} | {$groupReply->value}");
                    }
                }
            }
        }
        
        return 0;
    }
}