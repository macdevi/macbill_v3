<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internet_packages', function (Blueprint $table) {
            $table->decimal('download_mbps', 12, 3)->nullable();
            $table->decimal('upload_mbps', 12, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('internet_packages', function (Blueprint $table) {
            $table->dropColumn(['download_mbps', 'upload_mbps']);
        });
    }
};
