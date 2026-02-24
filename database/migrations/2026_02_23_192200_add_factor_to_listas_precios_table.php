<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listas_precios', function (Blueprint $table): void {
            $table->decimal('factor', 5, 4)->default(1.0000)->after('activo');
        });

        // También renombrar 'precio' a 'precio_override' en detalle_lista_precios
        // para dejar claro que es un override, no el precio canónico
        Schema::table('detalle_lista_precios', function (Blueprint $table): void {
            $table->renameColumn('precio', 'precio_override');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_lista_precios', function (Blueprint $table): void {
            $table->renameColumn('precio_override', 'precio');
        });

        Schema::table('listas_precios', function (Blueprint $table): void {
            $table->dropColumn('factor');
        });
    }
};
