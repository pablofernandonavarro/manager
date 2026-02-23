<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Crea roles y permisos para el sistema.
     */
    public function run(): void
    {
        // Limpiar caché de permisos
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear permisos agrupados por módulo

        // Módulo: Usuarios
        $usuariosPermisos = [
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.eliminar',
        ];

        // Módulo: Productos
        $productosPermisos = [
            'productos.ver',
            'productos.crear',
            'productos.editar',
            'productos.eliminar',
        ];

        // Módulo: Ventas
        $ventasPermisos = [
            'ventas.ver',
            'ventas.exportar',
            'ventas.anular',
        ];

        // Módulo: Configuración
        $configuracionPermisos = [
            'configuracion.ver',
            'configuracion.editar',
        ];

        // Crear todos los permisos
        $todosLosPermisos = array_merge(
            $usuariosPermisos,
            $productosPermisos,
            $ventasPermisos,
            $configuracionPermisos
        );

        foreach ($todosLosPermisos as $permiso) {
            Permission::create(['name' => $permiso, 'guard_name' => 'web']);
        }

        $this->command->info('Permisos creados: ' . count($todosLosPermisos));

        // Refrescar caché de permisos después de crearlos
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear roles y asignar permisos

        // Rol: Admin (todos los permisos)
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo($todosLosPermisos);
        $this->command->info('Rol admin creado con ' . $adminRole->permissions->count() . ' permisos');

        // Rol: Supervisor (ver/exportar/anular ventas, ver productos, ver usuarios)
        $supervisorRole = Role::create(['name' => 'supervisor', 'guard_name' => 'web']);
        $supervisorRole->givePermissionTo([
            'ventas.ver',
            'ventas.exportar',
            'ventas.anular',
            'productos.ver',
            'usuarios.ver',
        ]);
        $this->command->info('Rol supervisor creado con ' . $supervisorRole->permissions->count() . ' permisos');

        // Rol: Cajero (solo lo necesario para el POS)
        $cajeroRole = Role::create(['name' => 'cajero', 'guard_name' => 'web']);
        $cajeroRole->givePermissionTo([
            'productos.ver',
        ]);
        $this->command->info('Rol cajero creado con ' . $cajeroRole->permissions->count() . ' permisos');

        $this->command->info('Roles y permisos creados correctamente.');
    }
}
