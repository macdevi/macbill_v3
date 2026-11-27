<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }
}
