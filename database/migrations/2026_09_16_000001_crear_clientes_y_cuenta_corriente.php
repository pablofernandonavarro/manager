<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Clientes (con sus datos fiscales para facturar) y su cuenta corriente. Se cargan acá y
 * bajan a las cajas con el saldo, para vender a cuenta y cobrar deudas sin conexión.
 *
 * El saldo nunca se guarda: es la suma de los movimientos (+ deuda, − pago).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 150);
            // Códigos de AFIP: 80 CUIT, 96 DNI, 99 sin identificar; condición 5 consumidor final.
            $table->unsignedSmallInteger('doc_tipo')->default(99);
            $table->string('documento', 20)->nullable();
            $table->unsignedSmallInteger('condicion_iva')->default(5);
            $table->string('email', 150)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('domicilio', 200)->nullable();
            $table->boolean('cuenta_corriente')->default(false);
            // null = sin límite.
            $table->decimal('limite_credito', 16, 2)->nullable();
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['doc_tipo', 'documento']);
            $table->index('nombre');
        });

        Schema::create('movimientos_cuenta_corriente', function (Blueprint $table): void {
            $table->id();
            // Los que nacen en una caja traen su uuid: reenviarlos no los duplica.
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->enum('tipo', ['venta', 'pago', 'devolucion', 'ajuste']);
            $table->decimal('importe', 16, 2);
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->foreignId('devolucion_id')->nullable()->constrained('devoluciones')->nullOnDelete();
            $table->foreignId('punto_de_venta_id')->nullable()->constrained('puntos_de_venta')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('medio', 20)->nullable();
            $table->string('descripcion', 200);
            $table->dateTime('fecha');
            $table->timestamps();

            $table->index(['cliente_id', 'fecha']);
        });

        Schema::table('ventas', function (Blueprint $table): void {
            $table->foreignId('cliente_id')->nullable()->after('cliente_documento')->constrained('clientes')->nullOnDelete();
        });

        Schema::table('pagos_venta', function (Blueprint $table): void {
            $table->enum('medio', ['efectivo', 'debito', 'credito', 'transferencia', 'qr', 'cuenta_corriente'])->change();
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'clientes.gestionar', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'clientes.cuenta_corriente', 'guard_name' => 'web']);

        Role::where('name', 'admin')->where('guard_name', 'web')->first()?->givePermissionTo(['clientes.gestionar', 'clientes.cuenta_corriente']);
        // El supervisor carga clientes y ve la cuenta; los pagos y ajustes desde el Manager, admin.
        Role::where('name', 'supervisor')->where('guard_name', 'web')->first()?->givePermissionTo('clientes.gestionar');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('pagos_venta', function (Blueprint $table): void {
            $table->enum('medio', ['efectivo', 'debito', 'credito', 'transferencia', 'qr'])->change();
        });

        Schema::table('ventas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cliente_id');
        });

        Schema::dropIfExists('movimientos_cuenta_corriente');
        Schema::dropIfExists('clientes');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::whereIn('name', ['clientes.gestionar', 'clientes.cuenta_corriente'])->where('guard_name', 'web')->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
