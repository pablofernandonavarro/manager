<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que cada caja informa de sí misma cada minuto (último stock bajado, ventas sin enviar,
 * facturas pendientes, cola). La última conexión sola no alcanza: una caja puede estar
 * "en línea" con el sync de stock trabado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('puntos_de_venta', function (Blueprint $table): void {
            $table->json('estado_caja')->nullable();
            $table->timestamp('estado_reportado_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('puntos_de_venta', function (Blueprint $table): void {
            $table->dropColumn(['estado_caja', 'estado_reportado_at']);
        });
    }
};
