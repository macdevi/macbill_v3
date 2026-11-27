<?php

namespace App\Models;

use App\Models\Scopes\CustomerOwnedScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new CustomerOwnedScope);
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'paid_on' => 'immutable_date',
            'voided_at' => 'immutable_datetime',
            'amount' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
