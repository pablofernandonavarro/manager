<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuándo y desde qué versión se actualizó cada caja, visto desde el Manager. El escritorio se
 * actualiza reemplazando la carpeta: la caja está cerrada unos minutos y al abrir informa una
 * versión distinta. Sin este dato, ese rato se veía como una caja caída.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('puntos_de_venta', function (Blueprint $table): void {
            $table->string('version_anterior', 50)->nullable();
            $table->timestamp('version_actualizada_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('puntos_de_venta', function (Blueprint $table): void {
            $table->dropColumn(['version_anterior', 'version_actualizada_at']);
        });
    }
};
