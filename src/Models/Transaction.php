<?php

namespace Weexduunx\AidaGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'transaction_id',
        'external_id',
        'gateway',
        'status',
        'phone_number',
        'amount',
        'currency',
        'description',
        'metadata',
        'raw_response',
        'ip_address',
        'user_agent',
        'user_id',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'raw_response' => 'array',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('aida-gateway.transaction.table_name', 'aida_transactions');
    }

    /**
     * Get the user that owns the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Scope a query to only include successful transactions.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope a query to only include pending transactions.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include failed transactions.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to filter by gateway.
     */
    public function scopeByGateway($query, string $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    /**
     * Check if the transaction is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if the transaction is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the transaction failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Mark the transaction as successful.
     */
    public function markAsSuccessful(): self
    {
        $this->update([
            'status' => 'success',
            'completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark the transaction as failed.
     */
    public function markAsFailed(): self
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark the transaction as cancelled.
     */
    public function markAsCancelled(): self
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Get formatted amount with currency.
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }

    /**
     * Get gateway display name.
     */
    public function getGatewayDisplayNameAttribute(): string
    {
        return match ($this->gateway) {
            'orange_money' => 'Orange Money',
            'wave' => 'Wave',
            'free_money' => 'Free Money',
            'emoney' => 'E-money',
            default => ucfirst(str_replace('_', ' ', $this->gateway)),
        };
    }
}
