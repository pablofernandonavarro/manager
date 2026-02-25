<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sucursales', function (Blueprint $table): void {
            $table->boolean('is_central')->default(false)->after('activo');
        });

        // Crear la sucursal Central si no existe
        if (DB::table('sucursales')->where('is_central', true)->doesntExist()) {
            DB::table('sucursales')->insert([
                'nombre' => 'Central',
                'activo' => true,
                'is_central' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('sucursales', function (Blueprint $table): void {
            $table->dropColumn('is_central');
        });
    }
};
