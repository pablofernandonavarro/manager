<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cajeros de las cajas: se identifican con un PIN para abrir la caja, y los supervisores
 * además autorizan anulaciones y descuentos. Se gestionan acá y bajan a las cajas con el
 * PIN hasheado, para que la verificación funcione sin conexión.
 *
 * No son usuarios del Manager: un cajero no entra al Manager y un usuario del Manager
 * no necesariamente atiende una caja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajeros', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 100);
            $table->string('pin_hash');
            $table->enum('rol', ['cajero', 'supervisor'])->default('cajero');
            $table->json('sucursales')->nullable(); // null = todas
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'cajeros.gestionar', 'guard_name' => 'web']);
        Role::where('name', 'admin')->where('guard_name', 'web')->first()?->givePermissionTo('cajeros.gestionar');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('cajeros');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::where('name', 'cajeros.gestionar')->where('guard_name', 'web')->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
