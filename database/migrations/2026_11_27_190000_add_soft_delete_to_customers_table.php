<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->string('deleted_by_name', 255)->nullable();
            $table->string('deleted_mode', 20)->nullable();
            $table->string('deleted_username', 100)->nullable();
            $table->string('deleted_static_ip', 45)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'deleted_by', 'deleted_by_name', 'deleted_mode',
                'deleted_username', 'deleted_static_ip',
            ]);
        });
    }
};
