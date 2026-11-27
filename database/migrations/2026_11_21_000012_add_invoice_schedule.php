<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('default_invoice_day')->nullable();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedTinyInteger('invoice_day')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('invoice_day');
        });

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn('default_invoice_day');
        });
    }
};
