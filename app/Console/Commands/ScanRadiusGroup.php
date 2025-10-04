<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RadGroupCheck;
use App\Models\RadGroupReply;

class ScanRadiusGroup extends Command
{
    protected $signature = 'radius:scan-group {groupname=default}';
    protected $description = 'Scan RADIUS group attributes';

    public function handle()
    {
        $groupname = $this->argument('groupname');
        
        $this->info("=== RADIUS SCAN FOR GROUP: {$groupname} ===");
        
        // 1. RadGroupCheck
        $this->line("\n1. RadGroupCheck (Group Authentication/Authorization):");
        $checks = RadGroupCheck::where('groupname', $groupname)->get();
        if ($checks->isEmpty()) {
            $this->warn("   No RadGroupCheck entries found");
        } else {
            foreach ($checks as $check) {
                $this->line("   {$check->groupname} | {$check->attribute} | {$check->op} | {$check->value}");
            }
        }
        
        // 2. RadGroupReply
        $this->line("\n2. RadGroupReply (Group Reply Attributes):");
        $replies = RadGroupReply::where('groupname', $groupname)->get();
        if ($replies->isEmpty()) {
            $this->warn("   No RadGroupReply entries found");
        } else {
            foreach ($replies as $reply) {
                $this->line("   {$reply->groupname} | {$reply->attribute} | {$reply->op} | {$reply->value}");
            }
        }
        
        return 0;
    }
}