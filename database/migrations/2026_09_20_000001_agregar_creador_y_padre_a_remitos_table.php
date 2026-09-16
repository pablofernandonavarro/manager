<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remitos', function (Blueprint $table): void {
            $table->foreignId('creado_por_punto_de_venta_id')->nullable()->after('user_id')
                ->constrained('puntos_de_venta')->nullOnDelete();
            $table->foreignId('remito_origen_id')->nullable()->after('creado_por_punto_de_venta_id')
                ->constrained('remitos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('remitos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('remito_origen_id');
            $table->dropConstrainedForeignId('creado_por_punto_de_venta_id');
        });
    }
};
