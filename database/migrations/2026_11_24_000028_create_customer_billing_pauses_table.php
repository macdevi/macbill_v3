<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_billing_pauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained('customers')->restrictOnDelete();

            $table->unsignedBigInteger('open_customer_id')->nullable()->unique();

            $table->date('paused_from');
            $table->date('resumed_on')->nullable();

            $table->timestamp('isolated_at');
            $table->foreignId('isolated_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('isolated_by_name', 255);
            $table->text('isolation_reason');

            $table->timestamp('resumed_at')->nullable();
            $table->foreignId('resumed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('resumed_by_name', 255)->nullable();
            $table->text('resume_reason')->nullable();

            $table->timestamps();
            $table->index(['customer_id', 'paused_from']);
        });
    }

    public function down(): void
    {
        if (DB::table('customer_billing_pauses')->exists()) {
            throw new RuntimeException(
                'Rollback ditolak: riwayat jeda pelanggan sudah berisi data.'
            );
        }

        Schema::drop('customer_billing_pauses');
    }
};
