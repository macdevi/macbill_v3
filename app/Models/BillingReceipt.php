<?php

namespace App\Models;

use App\Models\Scopes\CustomerOwnedScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingReceipt extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new CustomerOwnedScope);
    }

    protected $table = 'billing_receipts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'receipt_snapshot' => 'array',
            'paid_on' => 'immutable_date',
            'voided_at' => 'immutable_datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(
            BillingAllocation::class,
            'receipt_id'
        );
    }

    public function balanceEntries(): HasMany
    {
        return $this->hasMany(
            BillingBalanceEntry::class,
            'receipt_id'
        );
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
