<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('default_due_day')->nullable();
            $table->string('missing_day_policy', 30)->default('last_day');
            $table->string('due_change_scope', 30)->default('future_invoices');
            $table->string('isolation_mode', 20)->default('manual');
            $table->timestamps();
        });

        DB::table('billing_settings')->insert([
            'id' => 1,
            'default_due_day' => null,
            'missing_day_policy' => 'last_day',
            'due_change_scope' => 'future_invoices',
            'isolation_mode' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
