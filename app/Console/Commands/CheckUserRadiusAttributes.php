<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RadCheck;

class CheckUserRadiusAttributes extends Command
{
    protected $signature = 'radius:check-user {username}';
    protected $description = 'Check RADIUS attributes for a specific user';

    public function handle()
    {
        $username = $this->argument('username');
        
        $this->info("=== RADIUS ATTRIBUTES FOR USER: {$username} ===\n");
        
        $radCheckAttributes = RadCheck::where('username', $username)->get();
        
        if ($radCheckAttributes->isEmpty()) {
            $this->warn("No RadCheck attributes found for user: {$username}");
        } else {
            $this->info("RadCheck (Individual User Attributes):");
            foreach ($radCheckAttributes as $attr) {
                $this->line("  {$attr->attribute} {$attr->op} {$attr->value}");
            }
        }
        
        return 0;
    }
}