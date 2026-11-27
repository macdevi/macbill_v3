<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingReceipt extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\CustomerOwnedScope);
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

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function allocations()
    {
        return $this->hasMany(
            BillingAllocation::class,
            'receipt_id'
        );
    }

    public function balanceEntries()
    {
        return $this->hasMany(
            BillingBalanceEntry::class,
            'receipt_id'
        );
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
