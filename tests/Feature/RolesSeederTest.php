<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * En una base nueva los permisos de módulos los crean las migraciones antes de que
     * existan los roles: el seeder tiene que dárselos. Pasó con migrate:fresh --seed: el
     * admin quedaba sin acceso a remitos, cajas, facturación, clientes...
     */
    public function test_el_admin_recibe_todos_los_permisos_incluidos_los_de_las_migraciones(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = Role::findByName('admin', 'web');
        $faltan = Permission::pluck('name')->reject(fn ($p) => $admin->hasPermissionTo($p));

        $this->assertSame([], $faltan->values()->all());
        $this->assertTrue(Permission::where('name', 'stock.ajustar')->exists(), 'sanity: hay permisos de migraciones');

        $supervisor = Role::findByName('supervisor', 'web');
        $this->assertTrue($supervisor->hasPermissionTo('remitos.recibir'));
        $this->assertTrue($supervisor->hasPermissionTo('remitos.crear'));
        $this->assertFalse($supervisor->hasPermissionTo('remitos.cancelar'));
        $this->assertTrue($supervisor->hasPermissionTo('clientes.gestionar'));
        $this->assertFalse($supervisor->hasPermissionTo('stock.ajustar'));
        $this->assertFalse($supervisor->hasPermissionTo('facturacion.configurar'));
    }
}
