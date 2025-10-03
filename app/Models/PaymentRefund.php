<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentRefund extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'refund_amount',
        'refund_reason',
        'refund_type', // 'full', 'partial'
        'refund_method', // 'auto', 'manual', 'provider_api'
        'refund_status', // 'pending', 'processing', 'completed', 'failed'
        'refund_transaction_id',
        'refund_reference',
        'provider_response',
        'processed_by',
        'processed_at',
        'notes'
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'provider_response' => 'json',
        'processed_at' => 'datetime'
    ];

    // Relationships
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // Helper methods
    public function isCompleted()
    {
        return $this->refund_status === 'completed';
    }

    public function isPending()
    {
        return in_array($this->refund_status, ['pending', 'processing']);
    }

    public function isFailed()
    {
        return $this->refund_status === 'failed';
    }

    public function isFullRefund()
    {
        return $this->refund_type === 'full';
    }

    public function isPartialRefund()
    {
        return $this->refund_type === 'partial';
    }

    public function getFormattedAmountAttribute()
    {
        return number_format($this->refund_amount, 2) . ' ' . strtoupper($this->payment->currency ?? 'USD');
    }

    public function getStatusColorAttribute()
    {
        return match($this->refund_status) {
            'completed' => 'success',
            'pending', 'processing' => 'warning',
            'failed' => 'danger',
            default => 'secondary'
        };
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('refund_status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->whereIn('refund_status', ['pending', 'processing']);
    }

    public function scopeFailed($query)
    {
        return $query->where('refund_status', 'failed');
    }

    public function scopeFullRefunds($query)
    {
        return $query->where('refund_type', 'full');
    }

    public function scopePartialRefunds($query)
    {
        return $query->where('refund_type', 'partial');
    }

    // Status management
    public function markAsCompleted($transactionId = null, $providerResponse = null)
    {
        $this->update([
            'refund_status' => 'completed',
            'processed_at' => now(),
            'refund_transaction_id' => $transactionId ?? $this->refund_transaction_id,
            'provider_response' => $providerResponse ?? $this->provider_response
        ]);

        return $this;
    }

    public function markAsFailed($reason = null, $providerResponse = null)
    {
        $this->update([
            'refund_status' => 'failed',
            'notes' => $reason,
            'provider_response' => $providerResponse ?? $this->provider_response
        ]);

        return $this;
    }

    public function markAsProcessing()
    {
        $this->update(['refund_status' => 'processing']);

        return $this;
    }
}