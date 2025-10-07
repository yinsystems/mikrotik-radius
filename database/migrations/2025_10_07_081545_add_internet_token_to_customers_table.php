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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('internet_token', 6)->nullable()->index()->comment('6-digit token for WiFi authentication');
            $table->timestamp('token_generated_at')->nullable()->comment('When the current token was generated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['internet_token', 'token_generated_at']);
        });
    }
};
