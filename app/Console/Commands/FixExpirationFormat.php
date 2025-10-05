<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RadCheck;
use App\Models\Subscription;
use Carbon\Carbon;

class FixExpirationFormat extends Command
{
    protected $signature = 'radius:fix-expiration-format {username?}';
    protected $description = 'Fix RADIUS expiration date format for proper authentication';

    public function handle()
    {
        $username = $this->argument('username');
        
        if ($username) {
            // Fix specific user
            $this->fixUserExpiration($username);
        } else {
            // Fix all users with expiration attributes
            $this->fixAllExpirations();
        }
        
        return 0;
    }
    
    private function fixUserExpiration($username)
    {
        $this->info("Fixing expiration format for user: {$username}");
        
        // Find the user's expiration entry
        $expiration = RadCheck::where('username', $username)
                             ->where('attribute', 'Expiration')
                             ->first();
        
        if (!$expiration) {
            $this->warn("No expiration attribute found for user: {$username}");
            return;
        }
        
        $this->line("Current expiration: {$expiration->value}");
        
        // Try to parse the current date and reformat it
        try {
            // Handle different possible formats
            $dateValue = $expiration->value;
            
            // Try parsing common formats
            $parsedDate = null;
            $formats = [
                'M d Y H:i',     // Current format: "Oct 04 2025 19:02"
                'M d Y H:i:s',   // Target format: "Oct 04 2025 19:02:00"
                'Y-m-d H:i:s',   // Alternative: "2025-10-04 19:02:00"
                'Y-m-d H:i',     // Alternative: "2025-10-04 19:02"
            ];
            
            foreach ($formats as $format) {
                try {
                    $parsedDate = Carbon::createFromFormat($format, $dateValue);
                    break;
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            if (!$parsedDate) {
                $this->error("Could not parse date: {$dateValue}");
                return;
            }
            
            // Format in proper RADIUS format
            $newFormat = $parsedDate->format('M d Y H:i:s');
            
            // Update the database
            $expiration->update(['value' => $newFormat]);
            
            $this->info("Updated expiration format: {$newFormat}");
            
        } catch (\Exception $e) {
            $this->error("Error fixing expiration for {$username}: " . $e->getMessage());
        }
    }
    
    private function fixAllExpirations()
    {
        $this->info("Fixing expiration format for all users...");
        
        $expirations = RadCheck::where('attribute', 'Expiration')->get();
        
        if ($expirations->isEmpty()) {
            $this->warn("No expiration attributes found in database");
            return;
        }
        
        $fixed = 0;
        $errors = 0;
        
        foreach ($expirations as $expiration) {
            try {
                $this->line("Processing user: {$expiration->username}");
                $this->fixUserExpiration($expiration->username);
                $fixed++;
            } catch (\Exception $e) {
                $this->error("Error processing {$expiration->username}: " . $e->getMessage());
                $errors++;
            }
        }
        
        $this->info("Completed! Fixed: {$fixed}, Errors: {$errors}");
    }
}