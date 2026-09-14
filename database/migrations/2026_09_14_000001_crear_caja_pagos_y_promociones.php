<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 1 del POS profesional: promociones bancarias (se definen acá y bajan a las
 * cajas), turnos de caja con su cierre Z, movimientos de efectivo y el detalle de cómo
 * se cobró cada venta, que hasta ahora no llegaba al Manager.
 *
 * Todo lo que viene de la caja se identifica por uuid (idempotencia del sync) y se
 * vincula por uuid y no por FK: turnos y ventas llegan en envíos separados y en
 * cualquier orden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promociones_bancarias', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 120);
            $table->string('banco', 80)->nullable();              // null = cualquier banco
            $table->json('medios');                                 // ['credito','debito','qr']
            $table->json('tarjetas')->nullable();                   // ['visa','mastercard'], null = todas
            $table->json('dias_semana')->nullable();                // [1..7] ISO, null = todos
            $table->json('sucursales')->nullable();                 // ids, null = todas
            $table->date('vigencia_desde')->nullable();
            $table->date('vigencia_hasta')->nullable();
            $table->enum('modalidad', ['descuento', 'reintegro'])->default('descuento');
            $table->decimal('porcentaje', 5, 2)->default(0);
            $table->decimal('tope', 12, 2)->nullable();
            $table->decimal('monto_minimo', 12, 2)->nullable();
            $table->unsignedTinyInteger('cuotas_sin_interes')->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('turnos_caja', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('punto_de_venta_id')->constrained('puntos_de_venta')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->string('cajero', 100);
            $table->enum('estado', ['abierto', 'cerrado'])->default('abierto');
            $table->decimal('fondo_inicial', 14, 2)->default(0);
            $table->dateTime('abierto_at');
            $table->dateTime('cerrado_at')->nullable();
            $table->unsignedInteger('cantidad_ventas')->default(0);
            $table->decimal('total_ventas', 16, 2)->default(0);
            $table->decimal('efectivo_esperado', 16, 2)->nullable();
            $table->decimal('efectivo_contado', 16, 2)->nullable();
            $table->decimal('diferencia', 16, 2)->nullable();
            $table->json('resumen')->nullable();
            $table->text('observaciones')->nullable();
            $table->dateTime('sincronizado_at')->nullable();
            $table->timestamps();

            $table->index(['sucursal_id', 'abierto_at']);
            // No único: una caja reinstalada vuelve a numerar sus turnos desde 1.
            $table->index(['punto_de_venta_id', 'numero']);
        });

        Schema::create('movimientos_caja', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('turno_caja_id')->constrained('turnos_caja')->cascadeOnDelete();
            $table->enum('tipo', ['ingreso', 'retiro', 'gasto']);
            $table->decimal('monto', 14, 2);
            $table->string('motivo', 200);
            $table->dateTime('fecha');
            $table->timestamps();
        });

        Schema::table('ventas', function (Blueprint $table): void {
            $table->uuid('turno_uuid')->nullable()->after('lista_precio_id')->index();
            $table->string('cajero', 100)->nullable()->after('turno_uuid');
            $table->string('metodo_pago', 20)->nullable()->after('total');
            $table->string('cliente_nombre', 150)->nullable()->after('metodo_pago');
            $table->string('cliente_documento', 30)->nullable()->after('cliente_nombre');
        });

        Schema::create('pagos_venta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->enum('medio', ['efectivo', 'debito', 'credito', 'transferencia', 'qr']);
            $table->decimal('monto', 16, 2);        // parte de la venta que cubre
            $table->decimal('descuento', 16, 2)->default(0);
            $table->decimal('importe', 16, 2);      // lo efectivamente cobrado (monto - descuento)
            $table->string('tarjeta', 30)->nullable();
            $table->string('banco', 80)->nullable();
            $table->unsignedTinyInteger('cuotas')->nullable();
            $table->foreignId('promocion_bancaria_id')->nullable()->constrained('promociones_bancarias')->nullOnDelete();
            $table->string('promocion_nombre', 120)->nullable();
            $table->string('referencia', 60)->nullable();
            $table->timestamps();

            $table->index('medio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_venta');

        Schema::table('ventas', function (Blueprint $table): void {
            $table->dropIndex(['turno_uuid']);
            $table->dropColumn(['turno_uuid', 'cajero', 'metodo_pago', 'cliente_nombre', 'cliente_documento']);
        });

        Schema::dropIfExists('movimientos_caja');
        Schema::dropIfExists('turnos_caja');
        Schema::dropIfExists('promociones_bancarias');
    }
};
