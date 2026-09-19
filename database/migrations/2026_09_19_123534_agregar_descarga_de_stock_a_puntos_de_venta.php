<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguimiento de la descarga de stock de cada caja, visto desde el Manager: la caja baja el
 * stock por páginas y anota "última sincronización" recién al terminar, así que una descarga
 * larga (caja nueva, reconciliación diaria) parecía un sync trabado. Con esto se puede
 * distinguir "está bajando" de "dejó de bajar".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('puntos_de_venta', function (Blueprint $table): void {
            $table->timestamp('stock_descarga_iniciada_at')->nullable();
            $table->timestamp('stock_descarga_avance_at')->nullable();
            $table->timestamp('stock_descarga_terminada_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('puntos_de_venta', function (Blueprint $table): void {
            $table->dropColumn(['stock_descarga_iniciada_at', 'stock_descarga_avance_at', 'stock_descarga_terminada_at']);
        });
    }
};
