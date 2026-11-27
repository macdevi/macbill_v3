<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerChange extends Model
{
    protected $fillable = [
        'customer_id', 'user_id', 'actor_name',
        'event', 'source', 'changes',
    ];

    protected function casts(): array
    {
        return ['changes' => 'encrypted:array'];
    }
}
