<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->foreignId('user_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->string('actor_name', 255);
            $table->string('event', 20);
            $table->string('source', 120);
            $table->longText('changes');
            $table->timestamps();
            $table->index(['customer_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_changes');
    }
};
