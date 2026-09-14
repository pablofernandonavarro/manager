<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo informa cada caja en los encabezados de sus llamadas a la API. Sin esto no había
 * forma de saber desde el Manager qué versión corre cada sucursal ni si la caja está
 * conectada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('puntos_de_venta', function (Blueprint $table): void {
            $table->string('version_pos', 50)->nullable()->after('activo');
            $table->string('tipo_instalacion', 20)->nullable()->after('version_pos');
            $table->timestamp('ultima_conexion_at')->nullable()->after('tipo_instalacion');
        });
    }

    public function down(): void
    {
        Schema::table('puntos_de_venta', function (Blueprint $table): void {
            $table->dropColumn(['version_pos', 'tipo_instalacion', 'ultima_conexion_at']);
        });
    }
};
