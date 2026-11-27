<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('billing_settings')->update([
                'default_invoice_day' => DB::raw(
                    'COALESCE(default_invoice_day, default_due_day)'
                ),
            ]);

            DB::table('billing_settings')->update([
                'default_due_day' => DB::raw('default_invoice_day'),
            ]);

            DB::table('customers')->update([
                'invoice_day' => DB::raw('COALESCE(invoice_day, due_day)'),
            ]);

            DB::table('customers')->update([
                'due_day' => DB::raw('invoice_day'),
            ]);
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Penyamaan tanggal tidak dibalik otomatis. Gunakan cadangan nilai tanggal untuk pemulihan terencana.'
        );
    }
};
