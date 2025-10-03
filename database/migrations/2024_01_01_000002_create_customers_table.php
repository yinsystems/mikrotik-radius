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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('phone', 20);
            $table->enum('status', ['active', 'suspended', 'blocked'])->default('active');
            $table->timestamp('registration_date')->useCurrent();
            $table->timestamp('last_login')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'registration_date']);
            $table->index('phone');
            $table->index('username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
