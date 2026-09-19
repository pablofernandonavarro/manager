<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El tablero y los reportes filtran por rango de fecha en "Todas las sucursales", y el
 * índice (sucursal_id, fecha) de ventas no sirve sin la sucursal: recorría toda la tabla y
 * el costo de cada refresco crecía con todo el historial. devoluciones no tenía ningún
 * índice sobre fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->index('fecha');
        });

        Schema::table('devoluciones', function (Blueprint $table): void {
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->dropIndex(['fecha']);
        });

        Schema::table('devoluciones', function (Blueprint $table): void {
            $table->dropIndex(['fecha']);
        });
    }
};
