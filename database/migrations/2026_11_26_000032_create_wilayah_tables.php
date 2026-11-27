<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wilayah', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 30)->unique();
            $table->timestamps();
        });

        Schema::create('wilayah_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wilayah_id')->constrained('wilayah')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['wilayah_id', 'user_id']);
        });

        Schema::create('network_router_wilayah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wilayah_id')->constrained('wilayah')->cascadeOnDelete();
            $table->foreignId('network_router_id')->constrained('network_routers')->cascadeOnDelete();
            $table->unique(['wilayah_id', 'network_router_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('wilayah_id')->nullable()
                ->constrained('wilayah')->restrictOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('wilayah_id')->nullable()
                ->constrained('wilayah')->restrictOnDelete();
        });

        DB::transaction(function () {
            $now = now();
            $id = DB::table('wilayah')->insertGetId([
                'name' => 'Utama',
                'code' => 'UTAMA',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('customers')->update(['wilayah_id' => $id]);
            DB::table('expenses')->update(['wilayah_id' => $id]);

            foreach (DB::table('network_routers')->pluck('id') as $routerId) {
                DB::table('network_router_wilayah')->insert([
                    'wilayah_id' => $id,
                    'network_router_id' => $routerId,
                ]);
            }

            foreach (DB::table('users')->where('role', 'admin')->pluck('id') as $userId) {
                DB::table('wilayah_user')->insert([
                    'wilayah_id' => $id,
                    'user_id' => $userId,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wilayah_id');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wilayah_id');
        });
        Schema::dropIfExists('network_router_wilayah');
        Schema::dropIfExists('wilayah_user');
        Schema::dropIfExists('wilayah');
    }
};
