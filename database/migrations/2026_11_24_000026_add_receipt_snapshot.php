<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('billing_receipts', 'receipt_snapshot')) {
            throw new RuntimeException('Kolom receipt_snapshot sudah ada.');
        }

        Schema::table('billing_receipts', function (Blueprint $table) {
            $table->text('receipt_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('billing_receipts')->whereNotNull('receipt_snapshot')->exists()) {
            throw new RuntimeException(
                'Rollback ditolak: sudah ada snapshot nota tersimpan.'
            );
        }

        Schema::table('billing_receipts', function (Blueprint $table) {
            $table->dropColumn('receipt_snapshot');
        });
    }
};
