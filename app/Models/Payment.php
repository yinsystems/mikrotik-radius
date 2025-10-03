<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'subscription_id',
        'package_id',
        'amount',
        'currency',
        'payment_method', // 'mobile_money', 'cash', 'bank_transfer', 'card', 'voucher'
        'mobile_money_provider', // 'mtn', 'airtel', 'vodafone', 'tigo', etc.
        'mobile_number',
        'transaction_id',
        'external_reference', // Provider's transaction reference
        'internal_reference', // Our internal reference
        'status', // 'pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded', 'partially_refunded'
        'payment_date',
        'webhook_data',
        'failure_reason',
        'refund_reason',
        'refunded_at',
        'refund_amount', // Amount actually refunded (for partial refunds)
        'refund_transaction_id', // Provider's refund transaction ID
        'refund_reference', // Our internal refund reference
        'processed_by', // admin user who processed manual payments
        'refunded_by', // admin user who processed refund
        'notes',
        // New fields for Redde integration
        'method',
        'provider',
        'reference',
        'phone_number',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'webhook_data' => 'json',
        'refunded_at' => 'datetime',
        'metadata' => 'json',
    ];

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function refundedBy()
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    // Refund relationships
    public function refunds()
    {
        return $this->hasMany(PaymentRefund::class, 'payment_id');
    }

    // Helper methods
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isPending()
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function isFailed()
    {
        return $this->status === 'failed';
    }

    public function isRefunded()
    {
        return in_array($this->status, ['refunded', 'partially_refunded']);
    }

    public function isPartiallyRefunded()
    {
        return $this->status === 'partially_refunded';
    }

    public function isFullyRefunded()
    {
        return $this->status === 'refunded';
    }

    public function canBeRefunded()
    {
        return $this->isCompleted() && !$this->isFullyRefunded();
    }

    public function canBePartiallyRefunded()
    {
        return $this->isCompleted() && $this->getRemainingRefundableAmount() > 0;
    }

    public function getRemainingRefundableAmount()
    {
        if (!$this->isCompleted()) {
            return 0;
        }
        
        return $this->amount - ($this->refund_amount ?? 0);
    }

    public function getTotalRefundedAmount()
    {
        return $this->refund_amount ?? 0;
    }

    public function getRefundPercentage()
    {
        if ($this->amount <= 0) {
            return 0;
        }
        
        return ($this->getTotalRefundedAmount() / $this->amount) * 100;
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'completed' => 'success',
            'pending', 'processing' => 'warning',
            'failed', 'cancelled' => 'danger',
            'refunded' => 'info',
            default => 'secondary'
        };
    }

    public function getStatusIconAttribute()
    {
        return match($this->status) {
            'completed' => 'heroicon-o-check-circle',
            'pending' => 'heroicon-o-clock',
            'processing' => 'heroicon-o-arrow-path',
            'failed' => 'heroicon-o-x-circle',
            'cancelled' => 'heroicon-o-minus-circle',
            'refunded' => 'heroicon-o-arrow-uturn-left',
            default => 'heroicon-o-question-mark-circle'
        };
    }

    public function getProviderDisplayNameAttribute()
    {
        return match($this->mobile_money_provider) {
            'mtn' => 'MTN Mobile Money',
            'airtel' => 'Airtel Money',
            'vodafone' => 'Vodafone Cash',
            'tigo' => 'Tigo Cash',
            'orange' => 'Orange Money',
            'moov' => 'Moov Money',
            default => ucfirst($this->mobile_money_provider ?? 'Unknown')
        };
    }

    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2) . ' ' . strtoupper($this->currency);
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCompletedOrRefunded($query)
    {
        return $query->whereIn('status', ['completed', 'refunded', 'partially_refunded']);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'processing']);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRefunded($query)
    {
        return $query->whereIn('status', ['refunded', 'partially_refunded']);
    }

    public function scopeFullyRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    public function scopePartiallyRefunded($query)
    {
        return $query->where('status', 'partially_refunded');
    }

    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    public function scopeByProvider($query, $provider)
    {
        return $query->where('mobile_money_provider', $provider);
    }

    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('payment_date', [$start, $end]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('payment_date', today());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('payment_date', now()->month)
                    ->whereYear('payment_date', now()->year);
    }

    public function scopeThisYear($query)
    {
        return $query->whereYear('payment_date', now()->year);
    }

    // Static methods for payment processing
    public static function generateInternalReference()
    {
        do {
            $reference = 'PAY-' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        } while (self::where('internal_reference', $reference)->exists());
        
        return $reference;
    }

    // Status management methods
    public function markAsCompleted($transactionId = null, $webhookData = null)
    {
        $this->update([
            'status' => 'completed',
            'payment_date' => now(),
            'transaction_id' => $transactionId ?? $this->transaction_id,
            'webhook_data' => $webhookData ?? $this->webhook_data
        ]);
    }

    public function markAsFailed($reason = null)
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason
        ]);
    }

    public function markAsProcessing()
    {
        $this->update(['status' => 'processing']);
    }

    // Enhanced refund processing methods
    public function processFullRefund($reason = null, $refundedBy = null, $refundTransactionId = null)
    {
        if (!$this->canBeRefunded()) {
            throw new \Exception('Payment cannot be refunded');
        }

        $refundAmount = $this->getRemainingRefundableAmount();
        
        $this->update([
            'status' => 'refunded',
            'refund_reason' => $reason,
            'refunded_at' => now(),
            'refund_amount' => $this->amount,
            'refund_transaction_id' => $refundTransactionId,
            'refund_reference' => $this->generateRefundReference(),
            'refunded_by' => $refundedBy,
            'notes' => ($this->notes ? $this->notes . "\n" : '') . 
                      "Full refund processed: {$this->getFormattedAmount()} on " . now()->format('Y-m-d H:i:s')
        ]);

        // Handle subscription implications
        $this->handleRefundSubscriptionEffects();

        return $this;
    }

    public function processPartialRefund($refundAmount, $reason = null, $refundedBy = null, $refundTransactionId = null)
    {
        if (!$this->canBePartiallyRefunded()) {
            throw new \Exception('Payment cannot be partially refunded');
        }

        $maxRefundable = $this->getRemainingRefundableAmount();
        if ($refundAmount > $maxRefundable) {
            throw new \Exception("Refund amount ({$refundAmount}) exceeds refundable amount ({$maxRefundable})");
        }

        $totalRefunded = ($this->refund_amount ?? 0) + $refundAmount;
        $newStatus = ($totalRefunded >= $this->amount) ? 'refunded' : 'partially_refunded';

        $this->update([
            'status' => $newStatus,
            'refund_reason' => $reason,
            'refunded_at' => now(),
            'refund_amount' => $totalRefunded,
            'refund_transaction_id' => $refundTransactionId,
            'refund_reference' => $this->refund_reference ?? $this->generateRefundReference(),
            'refunded_by' => $refundedBy,
            'notes' => ($this->notes ? $this->notes . "\n" : '') . 
                      "Partial refund processed: " . number_format($refundAmount, 2) . " {$this->currency} on " . now()->format('Y-m-d H:i:s')
        ]);

        // Handle subscription implications for partial refunds
        $this->handlePartialRefundSubscriptionEffects($refundAmount);

        return $this;
    }

    public function reverseRefund($reason = null, $processedBy = null)
    {
        if (!$this->isRefunded()) {
            throw new \Exception('Payment is not refunded');
        }

        $this->update([
            'status' => 'completed',
            'refund_reason' => null,
            'refunded_at' => null,
            'refund_amount' => null,
            'refund_transaction_id' => null,
            'refund_reference' => null,
            'processed_by' => $processedBy,
            'notes' => ($this->notes ? $this->notes . "\n" : '') . 
                      "Refund reversed: {$reason} on " . now()->format('Y-m-d H:i:s')
        ]);

        // Reactivate subscription if applicable
        if ($this->subscription && $this->subscription->status === 'cancelled') {
            $this->subscription->activate();
        }

        return $this;
    }

    protected function handleRefundSubscriptionEffects()
    {
        if (!$this->subscription) {
            return;
        }

        // Cancel/suspend the subscription when payment is refunded
        switch ($this->subscription->status) {
            case 'active':
                $this->subscription->suspend('Payment refunded');
                break;
            case 'pending':
                $this->subscription->update(['status' => 'cancelled']);
                break;
        }
    }

    protected function handlePartialRefundSubscriptionEffects($refundAmount)
    {
        if (!$this->subscription || !$this->package) {
            return;
        }

        // Calculate proportional reduction in subscription benefits
        $refundPercentage = ($refundAmount / $this->amount) * 100;
        
        // For partial refunds, we might reduce data allowance or duration
        // This is business logic that can be customized
        if ($refundPercentage >= 50) {
            // If more than 50% refunded, suspend the subscription
            $this->subscription->suspend("Partial refund of {$refundPercentage}% processed");
        } else {
            // Add note about partial refund but keep subscription active
            $this->subscription->update([
                'notes' => ($this->subscription->notes ? $this->subscription->notes . "\n" : '') . 
                          "Partial refund of {$refundPercentage}% processed on " . now()->format('Y-m-d H:i:s')
            ]);
        }
    }

    public function generateRefundReference()
    {
        do {
            $reference = 'REF-' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        } while (self::where('refund_reference', $reference)->exists());
        
        return $reference;
    }

    public function cancel($reason = null)
    {
        if ($this->isPending()) {
            $this->update([
                'status' => 'cancelled',
                'failure_reason' => $reason
            ]);
            return true;
        }
        return false;
    }

    // Payment analytics
    public static function getTotalRevenue($start = null, $end = null)
    {
        $query = self::completedOrRefunded();
        
        if ($start && $end) {
            $query->whereBetween('payment_date', [$start, $end]);
        }
        
        return $query->sum('amount');
    }

    public static function getRevenueByProvider($start = null, $end = null)
    {
        $query = self::completedOrRefunded()->where('payment_method', 'mobile_money');
        
        if ($start && $end) {
            $query->whereBetween('payment_date', [$start, $end]);
        }
        
        return $query->groupBy('mobile_money_provider')
                    ->selectRaw('mobile_money_provider, SUM(amount) as total_amount, COUNT(*) as transaction_count')
                    ->get();
    }

    public static function getRevenueByPackage($start = null, $end = null)
    {
        $query = self::completedOrRefunded()->with('package');
        
        if ($start && $end) {
            $query->whereBetween('payment_date', [$start, $end]);
        }
        
        return $query->groupBy('package_id')
                    ->selectRaw('package_id, SUM(amount) as total_amount, COUNT(*) as transaction_count')
                    ->get();
    }

    // Refund analytics
    public static function getTotalRefunds($start = null, $end = null)
    {
        $query = self::refunded();
        
        if ($start && $end) {
            $query->whereBetween('refunded_at', [$start, $end]);
        }
        
        return $query->sum('refund_amount');
    }

    public static function getRefundRate($start = null, $end = null)
    {
        $totalPayments = self::completed();
        $totalRefunds = self::refunded();
        
        if ($start && $end) {
            $totalPayments->whereBetween('payment_date', [$start, $end]);
            $totalRefunds->whereBetween('refunded_at', [$start, $end]);
        }
        
        $paymentCount = $totalPayments->count();
        $refundCount = $totalRefunds->count();
        
        return $paymentCount > 0 ? ($refundCount / $paymentCount) * 100 : 0;
    }

    public static function getRefundsByReason($start = null, $end = null)
    {
        $query = self::refunded()->whereNotNull('refund_reason');
        
        if ($start && $end) {
            $query->whereBetween('refunded_at', [$start, $end]);
        }
        
        return $query->groupBy('refund_reason')
                    ->selectRaw('refund_reason, COUNT(*) as count, SUM(refund_amount) as total_amount')
                    ->get();
    }

    public static function getRefundsByPaymentMethod($start = null, $end = null)
    {
        $query = self::refunded();
        
        if ($start && $end) {
            $query->whereBetween('refunded_at', [$start, $end]);
        }
        
        return $query->groupBy('payment_method')
                    ->selectRaw('payment_method, COUNT(*) as count, SUM(refund_amount) as total_amount')
                    ->get();
    }

    public static function getNetRevenue($start = null, $end = null)
    {
        $totalRevenue = self::getTotalRevenue($start, $end);
        $totalRefunds = self::getTotalRefunds($start, $end);
        
        return $totalRevenue - $totalRefunds;
    }
}