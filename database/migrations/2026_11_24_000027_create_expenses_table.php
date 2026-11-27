<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expenses')) {
            throw new RuntimeException(
                'Tabel expenses sudah ada. Periksa pemasangan sebelumnya.'
            );
        }

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->date('spent_on');
            $table->string('category', 30);
            $table->unsignedBigInteger('amount');
            $table->string('method', 20);
            $table->string('description', 500);
            $table->string('status', 20)->default('active');
            $table->foreignId('recorded_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('recorded_by_name', 255);
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('voided_by_name', 255)->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'spent_on']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        if (DB::table('expenses')->exists()) {
            throw new RuntimeException(
                'Rollback ditolak karena tabel pengeluaran berisi data.'
            );
        }

        Schema::drop('expenses');
    }
};
