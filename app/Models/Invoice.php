<?php

namespace App\Models;

use App\Models\Scopes\CustomerOwnedScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new CustomerOwnedScope);
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'issued_on' => 'immutable_date',
            'due_on' => 'immutable_date',
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'voided_at' => 'immutable_datetime',
            'price' => 'integer',
            'net' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'paid_amount' => 'integer',
            'snapshot' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(BillingAllocation::class)->where('status', 'active');
    }

    public function creditUses(): HasMany
    {
        return $this->hasMany(BillingBalanceEntry::class)
            ->where('type', 'use')->where('status', 'active');
    }

    public function historyAllocations(): HasMany
    {
        return $this->hasMany(BillingAllocation::class)
            ->orderBy('id');
    }

    public function historyCreditUses(): HasMany
    {
        return $this->hasMany(BillingBalanceEntry::class)
            ->where('type', 'use')
            ->orderBy('id');
    }

    public function getRemainingAttribute(): int
    {
        return max(0, (int) $this->total - (int) $this->paid_amount);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'void' => 'Dibatalkan',
            'paid' => 'Lunas',
            'partial' => 'Dibayar sebagian',
            default => 'Belum dibayar',
        };
    }
}
