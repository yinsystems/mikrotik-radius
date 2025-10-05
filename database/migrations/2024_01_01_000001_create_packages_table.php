<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('duration_type', ['hourly', 'daily', 'weekly', 'monthly', 'trial']);
            $table->integer('duration_value'); // Number of hours/days/weeks/months
            $table->decimal('price', 10, 2);
            $table->integer('bandwidth_upload')->nullable(); // Kbps
            $table->integer('bandwidth_download')->nullable(); // Kbps
            $table->integer('data_limit')->nullable(); // MB (null = unlimited)
            $table->integer('simultaneous_users')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_trial')->default(false);
            $table->integer('trial_duration_hours')->nullable();
            $table->integer('vlan_id')->nullable();
            $table->integer('priority')->default(0);
            $table->timestamps();
            
            $table->index(['is_active', 'is_trial']);
            $table->index('duration_type');
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};