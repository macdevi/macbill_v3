<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internet_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedBigInteger('monthly_price');
            $table->string('service_type', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('internet_package_router', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internet_package_id')
                ->constrained('internet_packages')->cascadeOnDelete();
            $table->foreignId('network_router_id')
                ->constrained('network_routers')->restrictOnDelete();
            $table->string('normal_profile', 255);
            $table->unique(
                ['internet_package_id', 'network_router_id'],
                'package_router_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internet_package_router');
        Schema::dropIfExists('internet_packages');
    }
};
