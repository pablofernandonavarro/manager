<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los remitos mueven stock entre sucursales y alcanzaba con estar logueado para crear,
 * confirmar o cancelar uno. Van separados porque en la práctica son personas distintas:
 * quien despacha, quien recibe en la sucursal y quien puede anular.
 *
 * Como migración y no seeder, para que se aplique en instalaciones que ya están andando.
 */
return new class extends Migration
{
    private const PERMISOS = [
        'remitos.ver',
        'remitos.crear',
        'remitos.recibir',
        'remitos.cancelar',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISOS as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        if ($admin = Role::where('name', 'admin')->where('guard_name', 'web')->first()) {
            $admin->givePermissionTo(self::PERMISOS);
        }

        // El supervisor opera la sucursal: ve y recibe mercadería, pero no despacha ni anula.
        if ($supervisor = Role::where('name', 'supervisor')->where('guard_name', 'web')->first()) {
            $supervisor->givePermissionTo(['remitos.ver', 'remitos.recibir']);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', self::PERMISOS)->where('guard_name', 'web')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
