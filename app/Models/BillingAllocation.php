<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingAllocation extends Model
{
    protected $table = 'billing_allocations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function receipt()
    {
        return $this->belongsTo(
            BillingReceipt::class,
            'receipt_id'
        );
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
