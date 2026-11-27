<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    public static function trashedIds(): array
    {
        static $ids = null;

        return $ids ??= \Illuminate\Support\Facades\DB::table('customers')
            ->whereNotNull('deleted_at')->pluck('id')->flip()->all();
    }

    protected $fillable = [
        'name', 'whatsapp', 'address', 'service_type',
        'network_router_id', 'internet_package_id',
        'pppoe_username', 'pppoe_password', 'static_ip',
        'started_at', 'due_day', 'service_status', 'notes',
        'invoice_day', 'first_billing_mode',
        'price_mode', 'custom_monthly_price', 'tax_enabled',
    ];

    protected $hidden = ['pppoe_password'];

    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(function () use ($options) {
            if ($this->exists) {
                $this->newModelQuery()->whereKey($this->getKey())
                    ->lockForUpdate()->firstOrFail(['id']);
            }

            if ($this->exists) {
                $pending = \Illuminate\Support\Facades\Schema::hasTable(
                    'customer_network_actions'
                ) && \App\Models\CustomerNetworkAction::where(
                    'open_customer_id', $this->getKey()
                )->exists();

                if ($pending) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'customer' => 'Data pelanggan dikunci karena masih ada tindakan jaringan yang belum selesai.',
                    ]);
                }

                if (
                    $this->isDirty([
                        'network_router_id', 'internet_package_id',
                        'pppoe_username', 'service_type',
                        'service_status', 'invoice_day', 'started_at',
                    ])
                    && \App\Models\CustomerBillingPause::where(
                        'open_customer_id', $this->getKey()
                    )->exists()
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'customer' => 'Identitas jaringan, status, dan tanggal tagihan tidak boleh diubah selama jeda isolir terbuka.',
                    ]);
                }
            }

            return parent::save($options);
        });
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\WilayahScope);

        static::creating(function (self $customer) {
            if ($customer->wilayah_id === null) {
                $customer->wilayah_id = \Illuminate\Support\Facades\DB::table('wilayah')
                    ->orderBy('id')->value('id');
            }
        });
    }

    public function wilayah()
    {
        return $this->belongsTo(Wilayah::class, 'wilayah_id');
    }

    protected function casts(): array
    {
        return [
            'pppoe_password' => 'encrypted',
            'started_at' => 'date',
            'due_day' => 'integer',
            'invoice_day' => 'integer',
            'custom_monthly_price' => 'integer',
            'tax_enabled' => 'boolean',
        ];
    }

    public function getCodeAttribute(): string
    {
        return 'PLG-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function billingPause()
    {
        return $this->hasOne(CustomerBillingPause::class)
            ->whereNotNull('open_customer_id');
    }

    public function networkAction()
    {
        return $this->hasOne(CustomerNetworkAction::class)
            ->whereNotNull('open_customer_id');
    }

    public function latestChange()
    {
        return $this->hasOne(CustomerChange::class, 'customer_id')
            ->latestOfMany();
    }

    public function router()
    {
        return $this->belongsTo(NetworkRouter::class, 'network_router_id');
    }

    public function package()
    {
        return $this->belongsTo(InternetPackage::class, 'internet_package_id');
    }
}
