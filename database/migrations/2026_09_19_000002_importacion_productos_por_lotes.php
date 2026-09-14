<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La importación de productos pasa a correr por lotes en la cola: contadores de progreso,
 * errores por fila (una fila con error no frena las demás) y registro de lotes terminados
 * (lo que hace que un reintento no procese dos veces el mismo lote).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('importaciones_productos', function (Blueprint $table): void {
            $table->renameColumn('filas', 'total_filas');
            $table->renameColumn('error', 'mensaje');
            $table->renameColumn('terminado_at', 'finalizado_at');
        });

        Schema::table('importaciones_productos', function (Blueprint $table): void {
            // "completada_con_errores" no entra en 20.
            $table->string('estado', 30)->default('pendiente')->change();
            $table->dropColumn('resultado');
            $table->unsignedInteger('filas_procesadas')->default(0)->after('total_filas');
            $table->unsignedInteger('filas_exitosas')->default(0)->after('filas_procesadas');
            $table->unsignedInteger('filas_con_error')->default(0)->after('filas_exitosas');
            $table->unsignedInteger('creados')->default(0)->after('filas_con_error');
            $table->unsignedInteger('actualizados')->default(0)->after('creados');
            $table->unsignedInteger('modelos_nuevos')->default(0)->after('actualizados');
            $table->unsignedInteger('cambios_stock')->default(0)->after('modelos_nuevos');
            $table->unsignedSmallInteger('lotes_total')->nullable()->after('cambios_stock');
        });

        Schema::create('importacion_producto_errores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('importacion_id')->constrained('importaciones_productos')->cascadeOnDelete();
            $table->unsignedInteger('fila');
            $table->string('codigo', 100)->nullable();
            $table->text('mensaje');
            $table->json('datos')->nullable();
            $table->timestamps();
            // Un reintento no puede registrar dos veces el error de la misma fila.
            $table->unique(['importacion_id', 'fila']);
        });

        Schema::create('importacion_producto_lotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('importacion_id')->constrained('importaciones_productos')->cascadeOnDelete();
            $table->unsignedSmallInteger('lote');
            $table->unsignedInteger('desde');
            $table->unsignedInteger('hasta');
            $table->unsignedInteger('procesadas')->default(0);
            $table->unsignedInteger('exitosas')->default(0);
            $table->unsignedInteger('errores')->default(0);
            $table->unsignedInteger('duracion_ms')->default(0);
            $table->unsignedInteger('memoria_mb')->default(0);
            $table->timestamps();
            // Se inserta en la misma transacción que procesa el lote: si existe, ya se aplicó.
            $table->unique(['importacion_id', 'lote']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacion_producto_lotes');
        Schema::dropIfExists('importacion_producto_errores');

        Schema::table('importaciones_productos', function (Blueprint $table): void {
            $table->dropColumn(['filas_procesadas', 'filas_exitosas', 'filas_con_error', 'creados', 'actualizados', 'modelos_nuevos', 'cambios_stock', 'lotes_total']);
            $table->json('resultado')->nullable();
        });

        Schema::table('importaciones_productos', function (Blueprint $table): void {
            $table->renameColumn('total_filas', 'filas');
            $table->renameColumn('mensaje', 'error');
            $table->renameColumn('finalizado_at', 'terminado_at');
        });
    }
};
