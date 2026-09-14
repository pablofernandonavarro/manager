<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Las acciones sobre terminales no estaban cubiertas por ningún permiso: alcanzaba con
 * estar logueado para generar un código de instalación (secuestrar una caja) o mandar la
 * orden de actualizar, que hace que la caja baje y ejecute código. Eso tiene que quedar
 * restringido a admin.
 *
 * Va como migración y no como seeder para que se aplique también en instalaciones que
 * ya están andando.
 */
return new class extends Migration
{
    private const PERMISOS = [
        'terminales.ver',
        'terminales.instalar',
        'terminales.comandos',
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

        // El supervisor puede mirar el estado de las cajas, pero no instalarlas ni
        // mandarles órdenes.
        if ($supervisor = Role::where('name', 'supervisor')->where('guard_name', 'web')->first()) {
            $supervisor->givePermissionTo('terminales.ver');
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
