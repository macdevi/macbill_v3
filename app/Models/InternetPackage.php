<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternetPackage extends Model
{
    protected $fillable = [
        'name', 'monthly_price', 'service_type', 'is_active',
        'download_mbps', 'upload_mbps', 'rate_limit',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'integer',
            'download_mbps' => 'decimal:3',
            'upload_mbps' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function routers()
    {
        return $this->belongsToMany(
            NetworkRouter::class,
            'internet_package_router',
            'internet_package_id',
            'network_router_id'
        )->withPivot('normal_profile');
    }
}
