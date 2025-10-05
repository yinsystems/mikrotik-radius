<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the enum to include 'minutely'
        DB::statement("ALTER TABLE packages MODIFY COLUMN duration_type ENUM('minutely', 'hourly', 'daily', 'weekly', 'monthly', 'trial')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if any packages use 'minutely' before removing it
        $minutelyPackages = DB::table('packages')->where('duration_type', 'minutely')->count();
        
        if ($minutelyPackages > 0) {
            throw new Exception("Cannot rollback: {$minutelyPackages} packages are using 'minutely' duration type. Please update or delete them first.");
        }
        
        // Remove 'minutely' from enum
        DB::statement("ALTER TABLE packages MODIFY COLUMN duration_type ENUM('hourly', 'daily', 'weekly', 'monthly', 'trial')");
    }
};
