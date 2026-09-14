<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Promociones bancarias cambian lo que se cobra en todas las cajas: solo admin.
 * Los cierres de caja tienen plata y diferencias de arqueo: admin y supervisor los ven.
 */
return new class extends Migration
{
    private const PERMISOS = [
        'promociones.gestionar',
        'cajas.ver',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISOS as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        Role::where('name', 'admin')->where('guard_name', 'web')->first()?->givePermissionTo(self::PERMISOS);
        Role::where('name', 'supervisor')->where('guard_name', 'web')->first()?->givePermissionTo('cajas.ver');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', self::PERMISOS)->where('guard_name', 'web')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
