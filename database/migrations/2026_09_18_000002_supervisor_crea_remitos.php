<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * El supervisor de sucursal también arma remitos (antes solo los veía y recibía).
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permiso = Permission::firstOrCreate(['name' => 'remitos.crear', 'guard_name' => 'web']);
        Role::where('name', 'supervisor')->where('guard_name', 'web')->first()?->givePermissionTo($permiso);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::where('name', 'supervisor')->where('guard_name', 'web')->first()?->revokePermissionTo('remitos.crear');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
