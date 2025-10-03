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
        Schema::create('data_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->string('username')->index();
            $table->date('date')->index();
            $table->unsignedBigInteger('bytes_uploaded')->default(0);
            $table->unsignedBigInteger('bytes_downloaded')->default(0);
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->unsignedInteger('session_count')->default(0);
            $table->unsignedInteger('session_time')->default(0); // in seconds
            $table->unsignedInteger('peak_concurrent_sessions')->default(0);
            $table->timestamps();
            
            // Unique constraint to prevent duplicate entries per user per date
            $table->unique(['subscription_id', 'username', 'date']);
            
            // Indexes for better query performance
            $table->index(['username', 'date']);
            $table->index(['date', 'total_bytes']);
            $table->index('total_bytes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_usages');
    }
};