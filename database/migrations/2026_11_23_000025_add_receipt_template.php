<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('billing_settings', 'receipt_template')) {
            throw new RuntimeException('Kolom receipt_template sudah ada.');
        }

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->text('receipt_template')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn('receipt_template');
        });
    }
};
