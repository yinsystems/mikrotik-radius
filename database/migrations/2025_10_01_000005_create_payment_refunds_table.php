<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->decimal('refund_amount', 10, 2);
            $table->string('refund_reason')->nullable();
            $table->enum('refund_type', ['full', 'partial'])->default('partial');
            $table->enum('refund_method', ['auto', 'manual', 'provider_api'])->default('manual');
            $table->enum('refund_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('refund_transaction_id')->nullable();
            $table->string('refund_reference')->unique()->nullable();
            $table->json('provider_response')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('processed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'refund_status']);
            $table->index('refund_reference');
            $table->index('processed_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_refunds');
    }
};