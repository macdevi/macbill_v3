<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerNetworkAction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'router_before' => 'array',
            'router_result' => 'array',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
