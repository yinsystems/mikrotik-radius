<?php

namespace App\Console\Commands;

use App\Models\Package;
use App\Models\RadGroupCheck;
use App\Models\RadGroupReply;
use Illuminate\Console\Command;

class ResetPackageGroups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'radius:reset-package-groups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset all package RADIUS groups to remove Mikrotik-Group attributes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Resetting package RADIUS groups...');

        // Get all packages
        $packages = Package::all();

        if ($packages->isEmpty()) {
            $this->warn('No packages found.');
            return;
        }

        $this->line('Found ' . $packages->count() . ' packages to process');

        foreach ($packages as $package) {
            $this->line("Processing package: {$package->name}");
            
            // Remove any existing Mikrotik-Group attributes from RadGroupReply
            RadGroupReply::where('groupname', $package->name)
                ->where('attribute', 'Mikrotik-Group')
                ->delete();

            // Regenerate the package group
            $package->setupRadiusGroup();
            
            $this->info("✓ Reset group for package: {$package->name}");
        }

        $this->info('All package groups have been reset successfully!');
        $this->line('Mikrotik-Group attributes have been removed from all packages.');
    }
}
