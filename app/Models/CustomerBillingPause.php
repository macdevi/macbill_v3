<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerBillingPause extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'paused_from' => 'immutable_date',
            'resumed_on' => 'immutable_date',
            'isolated_at' => 'immutable_datetime',
            'resumed_at' => 'immutable_datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }
}
