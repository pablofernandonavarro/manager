<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Facturación electrónica (AFIP WSFEv1), centralizada en el Manager: un emisor (CUIT)
 * con su certificado digital, y un punto de venta de AFIP por sucursal. Las cajas le
 * piden el CAE al Manager.
 *
 * Clave privada, certificado y ticket de acceso se guardan cifrados con APP_KEY:
 * si se cambia la APP_KEY hay que volver a cargar el certificado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_fiscal', function (Blueprint $table): void {
            $table->id();
            $table->string('razon_social', 150)->nullable();
            $table->string('cuit', 11)->nullable();
            $table->enum('condicion_iva', ['responsable_inscripto', 'monotributo', 'exento'])->nullable();
            $table->string('ingresos_brutos', 30)->nullable();
            $table->date('inicio_actividades')->nullable();
            $table->string('domicilio_comercial', 200)->nullable();
            $table->enum('entorno', ['homologacion', 'produccion'])->default('homologacion');
            $table->boolean('facturacion_activa')->default(false);

            $table->text('clave_privada')->nullable();   // cifrada
            $table->text('csr')->nullable();
            $table->text('certificado')->nullable();     // cifrado
            $table->string('certificado_alias', 100)->nullable();
            $table->string('certificado_emisor', 150)->nullable();
            $table->dateTime('certificado_vence')->nullable();

            $table->text('ta_token')->nullable();        // cifrado
            $table->text('ta_sign')->nullable();         // cifrado
            $table->dateTime('ta_expira')->nullable();

            $table->dateTime('ultima_prueba_at')->nullable();
            $table->boolean('ultima_prueba_ok')->nullable();
            $table->json('ultima_prueba_detalle')->nullable();
            $table->timestamps();
        });

        Schema::table('sucursales', function (Blueprint $table): void {
            $table->unsignedInteger('afip_punto_venta')->nullable()->unique();
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'facturacion.configurar', 'guard_name' => 'web']);
        Role::where('name', 'admin')->where('guard_name', 'web')->first()?->givePermissionTo('facturacion.configurar');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('sucursales', function (Blueprint $table): void {
            $table->dropUnique(['afip_punto_venta']);
            $table->dropColumn('afip_punto_venta');
        });

        Schema::dropIfExists('configuracion_fiscal');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::where('name', 'facturacion.configurar')->where('guard_name', 'web')->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
