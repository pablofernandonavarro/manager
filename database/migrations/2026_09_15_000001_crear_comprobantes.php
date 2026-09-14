<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Comprobantes electrónicos (facturas y notas de crédito) de las ventas de las cajas.
 * El Manager es el único que habla con AFIP: numera, pide el CAE y le devuelve el
 * resultado a la caja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venta_id')->nullable()->unique()->constrained('ventas')->nullOnDelete();
            $table->foreignId('devolucion_id')->nullable()->unique()->constrained('devoluciones')->nullOnDelete();
            $table->foreignId('comprobante_asociado_id')->nullable()->constrained('comprobantes')->nullOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('punto_de_venta_id')->nullable()->constrained('puntos_de_venta')->nullOnDelete();

            $table->enum('entorno', ['homologacion', 'produccion']);
            $table->unsignedInteger('afip_punto_venta');
            $table->unsignedSmallInteger('tipo');
            // Se reserva antes de llamar a AFIP: si la respuesta se pierde, el próximo intento
            // consulta ese número en vez de pedir otro (evita emitir dos veces).
            $table->unsignedBigInteger('numero')->nullable();
            $table->date('fecha');

            $table->unsignedSmallInteger('receptor_doc_tipo');
            $table->string('receptor_doc_nro', 20);
            $table->string('receptor_nombre', 150)->nullable();
            $table->unsignedSmallInteger('receptor_condicion_iva');

            $table->decimal('importe_total', 16, 2);
            $table->decimal('importe_neto', 16, 2);
            $table->decimal('importe_iva', 16, 2);
            $table->json('alicuotas');

            $table->enum('estado', ['pendiente', 'autorizado', 'rechazado'])->default('pendiente')->index();
            $table->string('cae', 20)->nullable();
            $table->date('cae_vencimiento')->nullable();
            $table->json('observaciones')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('intentos')->default(0);
            $table->timestamp('autorizado_at')->nullable();
            $table->timestamps();

            $table->unique(['entorno', 'afip_punto_venta', 'tipo', 'numero'], 'comprobantes_numeracion_unica');
        });

        // Ver comprobantes: admin y supervisor. Reintentar ante AFIP sigue siendo de
        // facturacion.configurar (admin).
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'facturacion.ver', 'guard_name' => 'web']);
        Role::where('name', 'admin')->where('guard_name', 'web')->first()?->givePermissionTo('facturacion.ver');
        Role::where('name', 'supervisor')->where('guard_name', 'web')->first()?->givePermissionTo('facturacion.ver');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::where('name', 'facturacion.ver')->where('guard_name', 'web')->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
