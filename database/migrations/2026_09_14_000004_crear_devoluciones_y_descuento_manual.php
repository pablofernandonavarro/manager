<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devoluciones y anulaciones hechas en las cajas, y el descuento manual de cada venta
 * con quién lo autorizó. El stock de una devolución llega por movimientos_stock (tipo
 * devolucion); estas tablas son el comprobante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->decimal('descuento_manual', 16, 2)->default(0)->after('descuento');
            $table->string('descuento_autorizado_por', 100)->nullable()->after('descuento_manual');
        });

        Schema::create('devoluciones', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('punto_de_venta_id')->constrained('puntos_de_venta')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            // Por uuid y sin FK, como los turnos: llegan en envíos separados.
            $table->uuid('venta_uuid')->index();
            $table->uuid('turno_uuid')->nullable()->index();
            $table->string('numero', 30);
            $table->enum('tipo', ['anulacion', 'parcial']);
            $table->string('motivo', 200);
            $table->enum('reintegro', ['efectivo', 'medio_original']);
            $table->decimal('total', 16, 2);
            $table->string('autorizado_por', 100);
            $table->dateTime('fecha');
            $table->timestamps();
        });

        Schema::create('devolucion_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('devolucion_id')->constrained('devoluciones')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('cantidad');
            $table->decimal('importe', 16, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucion_items');
        Schema::dropIfExists('devoluciones');

        Schema::table('ventas', function (Blueprint $table): void {
            $table->dropColumn(['descuento_manual', 'descuento_autorizado_por']);
        });
    }
};
