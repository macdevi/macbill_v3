<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Wilayah extends Model
{
    protected $table = 'wilayah';

    protected $fillable = ['name', 'code'];

    public function customers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'wilayah_user');
    }

    public function routers(): BelongsToMany
    {
        return $this->belongsToMany(NetworkRouter::class, 'network_router_wilayah');
    }
}
