<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingSetting extends Model
{
    protected $fillable = [
        'default_due_day',
        'missing_day_policy',
        'due_change_scope',
        'isolation_mode',
        'default_invoice_day',
        'tax_name', 'tax_rate_bps', 'tax_mode', 'default_tax_enabled',
        'due_grace_days', 'wa_template', 'receipt_template',
    ];

    protected function casts(): array
    {
        return [
            'default_due_day' => 'integer',
            'default_invoice_day' => 'integer',
            'tax_rate_bps' => 'integer',
            'default_tax_enabled' => 'boolean',
            'login_screen' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::findOrFail(1);
    }
}
