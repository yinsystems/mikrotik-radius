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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('package_id')->constrained('packages')->onDelete('restrict');
            $table->enum('status', ['active', 'expired', 'suspended', 'pending', 'blocked'])->default('pending');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->bigInteger('data_used')->default(0); // bytes used
            $table->integer('sessions_used')->default(0);
            $table->boolean('is_trial')->default(false);
            $table->boolean('auto_renew')->default(false);
            $table->foreignId('renewal_package_id')->nullable()->constrained('packages')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'expires_at']);
            $table->index(['customer_id', 'status']);
            $table->index('expires_at');
            $table->index('is_trial');
            $table->index('auto_renew');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};