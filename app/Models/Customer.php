<?php

namespace App\Models;

use App\Models\Scopes\WilayahScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class Customer extends Model
{
    use SoftDeletes;

    public static function trashedIds(): array
    {
        static $ids = null;

        return $ids ??= DB::table('customers')
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
                $pending = Schema::hasTable(
                    'customer_network_actions'
                ) && CustomerNetworkAction::where(
                    'open_customer_id', $this->getKey()
                )->exists();

                if ($pending) {
                    throw ValidationException::withMessages([
                        'customer' => 'Data pelanggan dikunci karena masih ada tindakan jaringan yang belum selesai.',
                    ]);
                }

                if (
                    $this->isDirty([
                        'network_router_id', 'internet_package_id',
                        'pppoe_username', 'service_type',
                        'service_status', 'invoice_day', 'started_at',
                    ])
                    && CustomerBillingPause::where(
                        'open_customer_id', $this->getKey()
                    )->exists()
                ) {
                    throw ValidationException::withMessages([
                        'customer' => 'Identitas jaringan, status, dan tanggal tagihan tidak boleh diubah selama jeda isolir terbuka.',
                    ]);
                }
            }

            return parent::save($options);
        });
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new WilayahScope);

        static::creating(function (self $customer) {
            if ($customer->wilayah_id === null) {
                $customer->wilayah_id = DB::table('wilayah')
                    ->orderBy('id')->value('id');
            }
        });
    }

    public function wilayah(): BelongsTo
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

    public function billingPause(): HasOne
    {
        return $this->hasOne(CustomerBillingPause::class)
            ->whereNotNull('open_customer_id');
    }

    public function networkAction(): HasOne
    {
        return $this->hasOne(CustomerNetworkAction::class)
            ->whereNotNull('open_customer_id');
    }

    public function latestChange(): HasOne
    {
        return $this->hasOne(CustomerChange::class, 'customer_id')
            ->latestOfMany();
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(NetworkRouter::class, 'network_router_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(InternetPackage::class, 'internet_package_id');
    }
}
