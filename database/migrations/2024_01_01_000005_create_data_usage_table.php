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
        Schema::create('data_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->onDelete('cascade');
            $table->string('username', 64); // For quick lookups
            $table->date('date');
            $table->bigInteger('bytes_uploaded')->default(0);
            $table->bigInteger('bytes_downloaded')->default(0);
            $table->bigInteger('total_bytes')->default(0);
            $table->integer('session_count')->default(0);
            $table->integer('session_time')->default(0); // total time in seconds
            $table->integer('peak_concurrent_sessions')->default(0);
            $table->timestamps();
            
            $table->unique(['subscription_id', 'date']);
            $table->index(['username', 'date']);
            $table->index('date');
            $table->index('total_bytes');
            $table->index(['date', 'total_bytes']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_usage');
    }
};