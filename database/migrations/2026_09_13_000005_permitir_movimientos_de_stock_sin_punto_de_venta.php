<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los movimientos de stock solo nacían en una caja (ventas y ajustes sincronizados), así
 * que el punto de venta era obligatorio. Un remito entre sucursales se hace desde el
 * Manager y no tiene caja: sin esto no quedaba registro de por qué cambió el stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table): void {
            $table->foreignId('punto_de_venta_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table): void {
            $table->foreignId('punto_de_venta_id')->nullable(false)->change();
        });
    }
};
