<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_routers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('host', 253);
            $table->unsignedInteger('port');
            $table->boolean('use_tls')->default(false);
            $table->string('username', 100);
            $table->text('password');
            $table->string('wan_interface', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_routers');
    }
};
