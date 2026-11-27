<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->string('tax_name', 50)->default('Pajak');
            $table->unsignedSmallInteger('tax_rate_bps')->default(0);
            $table->string('tax_mode', 10)->default('include');
            $table->boolean('default_tax_enabled')->default(false);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('price_mode', 10)->default('package');
            $table->unsignedBigInteger('custom_monthly_price')->nullable();
            $table->boolean('tax_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'price_mode', 'custom_monthly_price', 'tax_enabled',
            ]);
        });

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn([
                'tax_name', 'tax_rate_bps', 'tax_mode', 'default_tax_enabled',
            ]);
        });
    }
};
