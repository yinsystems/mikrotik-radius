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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->onDelete('set null');
            $table->foreignId('package_id')->constrained('packages')->onDelete('restrict');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('GHS'); // or your local currency
            $table->enum('payment_method', ['mobile_money', 'cash', 'bank_transfer', 'card', 'voucher']);
            $table->string('mobile_money_provider')->nullable(); // mtn, airtel, vodafone, etc.
            $table->string('mobile_number', 20)->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('external_reference')->nullable(); // Provider's reference
            $table->string('internal_reference')->unique(); // Our internal reference
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded'])->default('pending');
            $table->timestamp('payment_date')->nullable();
            $table->json('webhook_data')->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('refund_reason')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'payment_date']);
            $table->index(['customer_id', 'status']);
            $table->index('payment_method');
            $table->index('mobile_money_provider');
            $table->index('transaction_id');
            $table->index('external_reference');
            $table->index('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};