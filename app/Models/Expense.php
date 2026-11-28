<?php

namespace App\Models;

use App\Models\Scopes\WilayahScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Expense extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new WilayahScope);

        static::creating(function ($model) {
            if ($model->wilayah_id !== null) {
                return;
            }

            $allowed = WilayahScope::allowedIds();
            $query = DB::table('wilayah')->orderBy('id');

            if ($allowed !== null) {
                $query->whereIn('id', $allowed);
            }

            $model->wilayah_id = $query->value('id');
        });
    }

    public const CATEGORIES = [
        'upstream' => 'Internet / upstream',
        'listrik' => 'Listrik',
        'sewa' => 'Sewa',
        'perawatan' => 'Perawatan jaringan',
        'perangkat' => 'Pembelian perangkat',
        'transportasi' => 'Transportasi',
        'honor' => 'Honor',
        'lainnya' => 'Lainnya',
    ];

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Wilayah, $this>
     */
    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class);
    }

    protected function casts(): array
    {
        return [
            'spent_on' => 'immutable_date',
            'amount' => 'integer',
            'voided_at' => 'immutable_datetime',
        ];
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Lainnya';
    }

    public function getMethodLabelAttribute(): string
    {
        return $this->method === 'cash' ? 'Tunai' : 'Transfer bank';
    }
}
