<?php

namespace App\Models;

use App\Models\Scopes\CustomerOwnedScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingBalanceEntry extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new CustomerOwnedScope);
    }

    protected $table = 'billing_balance_entries';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'voided_at' => 'immutable_datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(
            BillingReceipt::class,
            'receipt_id'
        );
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function sourceEntry(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'source_entry_id'
        );
    }

    public function uses(): HasMany
    {
        return $this->hasMany(
            self::class,
            'source_entry_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
