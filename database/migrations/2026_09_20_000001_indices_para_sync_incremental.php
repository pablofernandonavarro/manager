<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices de la sincronización incremental con las cajas: el delta y la paginación por
 * cursor recorren `(updated_at, id)`. Sin estos, cada página de 200.000 productos o de todo
 * el stock de una sucursal era un recorrido completo de la tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->index(['updated_at', 'id'], 'products_sync_cursor');
        });

        Schema::table('stock_sucursal', function (Blueprint $table): void {
            $table->index(['sucursal_id', 'updated_at', 'id'], 'stock_sucursal_sync_cursor');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropIndex('products_sync_cursor'));
        Schema::table('stock_sucursal', fn (Blueprint $table) => $table->dropIndex('stock_sucursal_sync_cursor'));
    }
};
