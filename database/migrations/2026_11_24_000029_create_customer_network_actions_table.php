<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_network_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('customer_id')
                ->constrained('customers')->restrictOnDelete();
            $table->unsignedBigInteger('open_customer_id')->nullable()->unique();
            $table->string('open_target_key', 64)->nullable()->unique();
            $table->string('kind', 20);
            $table->string('status', 20);
            $table->foreignId('actor_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('actor_name', 255);
            $table->text('reason');
            $table->json('context');
            $table->json('router_before')->nullable();
            $table->json('router_result')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        if (DB::table('customer_network_actions')->exists()) {
            throw new RuntimeException(
                'Rollback ditolak: jurnal tindakan jaringan sudah berisi data.'
            );
        }

        Schema::drop('customer_network_actions');
    }
};
