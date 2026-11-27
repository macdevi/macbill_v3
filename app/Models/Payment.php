<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\CustomerOwnedScope);
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

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
