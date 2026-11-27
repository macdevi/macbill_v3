<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('whatsapp', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('service_type', 20);
            $table->foreignId('network_router_id')
                ->constrained('network_routers')->restrictOnDelete();
            $table->foreignId('internet_package_id')
                ->constrained('internet_packages')->restrictOnDelete();
            $table->string('pppoe_username', 100)->nullable();
            $table->text('pppoe_password')->nullable();
            $table->string('static_ip', 45)->nullable();
            $table->date('started_at');
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->string('service_status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['network_router_id', 'pppoe_username'],
                'customer_router_pppoe_unique'
            );
            $table->unique(
                ['network_router_id', 'static_ip'],
                'customer_router_ip_unique'
            );
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
