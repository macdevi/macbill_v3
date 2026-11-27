<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkRouter extends Model
{
    protected $fillable = [
        'name', 'host', 'port', 'use_tls',
        'username', 'password', 'wan_interface',
        'isolation_method', 'isolation_profile',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'use_tls' => 'boolean',
            'port' => 'integer',
        ];
    }
}
