<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->string('active_period_key', 40)->nullable()->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('manual');
            $table->string('customer_name', 150);
            $table->string('customer_code', 40);
            $table->date('issued_on');
            $table->date('due_on');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('net');
            $table->unsignedBigInteger('tax');
            $table->unsignedBigInteger('total');
            $table->string('status', 20)->default('unpaid');
            $table->text('snapshot');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('voided_by_name', 255)->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'period_start']);
            $table->index(['status', 'issued_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
