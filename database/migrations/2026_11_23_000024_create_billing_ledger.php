<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('payments')->exists()) {
            throw new RuntimeException(
                'Masih ada riwayat pembayaran lama. Perlu migrasi data, bukan penghapusan.'
            );
        }

        if (DB::table('invoices')->where('status', 'paid')->exists()) {
            throw new RuntimeException(
                'Ada invoice Lunas. Periksa data pembayaran sebelum melanjutkan.'
            );
        }

        foreach ([
            'billing_receipts',
            'billing_allocations',
            'billing_balance_entries',
        ] as $table) {
            if (Schema::hasTable($table)) {
                throw new RuntimeException(
                    "Tabel {$table} sudah ada. Periksa pemasangan sebelumnya."
                );
            }
        }

        if (Schema::hasColumn('invoices', 'paid_amount')) {
            throw new RuntimeException(
                'Kolom invoices.paid_amount sudah ada. Periksa struktur terlebih dahulu.'
            );
        }

        Schema::create('billing_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('customer_id')
                ->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->unsignedBigInteger('amount');
            $table->string('method', 20);
            $table->date('paid_on');
            $table->string('reference', 120)->nullable();
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
            $table->index(['customer_id', 'status']);
        });

        Schema::create('billing_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')
                ->constrained('billing_receipts')->restrictOnDelete();
            $table->foreignId('invoice_id')
                ->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['receipt_id', 'invoice_id']);
            $table->index(['invoice_id', 'status']);
        });

        Schema::create('billing_balance_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained()->restrictOnDelete();

            $table->foreignId('receipt_id')->nullable()
                ->constrained('billing_receipts')->restrictOnDelete();

            $table->foreignId('invoice_id')->nullable()
                ->constrained()->restrictOnDelete();

            $table->foreignId('source_entry_id')->nullable()
                ->constrained('billing_balance_entries')->restrictOnDelete();

            $table->string('type', 20);
            $table->bigInteger('amount');
            $table->string('status', 20)->default('active');
            $table->string('note', 255)->nullable();

            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('created_by_name', 255);

            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('voided_by_name', 255)->nullable();
            $table->text('void_reason')->nullable();

            $table->timestamps();
            $table->index(['customer_id', 'status']);
            $table->index(['source_entry_id', 'status']);
            $table->index(['invoice_id', 'status']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('paid_amount')->default(0);
        });
    }

    public function down(): void
    {
        if (
            DB::table('billing_receipts')->exists()
            || DB::table('billing_allocations')->exists()
            || DB::table('billing_balance_entries')->exists()
        ) {
            throw new RuntimeException(
                'Rollback ditolak karena ledger berisi riwayat transaksi.'
            );
        }

        Schema::drop('billing_balance_entries');
        Schema::drop('billing_allocations');
        Schema::drop('billing_receipts');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
};
