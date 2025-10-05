<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            // Add new refund-related columns
            $table->decimal('refund_amount', 10, 2)->nullable()->after('refunded_at');
            $table->string('refund_transaction_id')->nullable()->after('refund_amount');
            $table->string('refund_reference')->unique()->nullable()->after('refund_transaction_id');
            $table->foreignId('refunded_by')->nullable()->constrained('users')->onDelete('set null')->after('processed_by');
            
            // Update status enum to include partially_refunded
            $table->dropColumn('status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('status', [
                'pending', 
                'processing', 
                'completed', 
                'failed', 
                'cancelled', 
                'refunded', 
                'partially_refunded'
            ])->default('pending')->after('internal_reference');
        });
    }

    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'refund_amount',
                'refund_transaction_id', 
                'refund_reference'
            ]);
            $table->dropForeign(['refunded_by']);
            $table->dropColumn('refunded_by');
            
            // Restore original status enum
            $table->dropColumn('status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('status', [
                'pending', 
                'processing', 
                'completed', 
                'failed', 
                'cancelled', 
                'refunded'
            ])->default('pending')->after('internal_reference');
        });
    }
};