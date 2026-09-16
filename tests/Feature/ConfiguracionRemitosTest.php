<?php

namespace Tests\Feature;

use App\Models\ConfiguracionRemitos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConfiguracionRemitosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $supervisorRole = Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);

        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'remitos.configurar', 'guard_name' => 'web']);
        $adminRole->givePermissionTo($permission);
    }

    public function test_solo_admin_ve_la_pantalla_de_configuracion(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->syncRoles('supervisor');

        $this->actingAs($supervisor)
            ->get(route('remitos.configuracion'))
            ->assertForbidden();
    }

    public function test_admin_accede_a_la_pantalla_de_configuracion(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles('admin');

        $this->actingAs($admin)
            ->get(route('remitos.configuracion'))
            ->assertOk();
    }

    public function test_cargar_configuracion_usa_id_1(): void
    {
        ConfiguracionRemitos::create([
            'id' => 1,
            'ruta_directa' => true,
            'destino_rechazados' => 'origen',
        ]);

        $admin = User::factory()->create();
        $admin->syncRoles('admin');

        $this->be($admin);

        $component = Livewire::test('remitos.configuracion');

        $this->assertTrue($component->get('rutaDirecta'));
        $this->assertEquals('origen', $component->get('destinoRechazados'));
    }

    public function test_guardar_actualiza_la_configuracion(): void
    {
        ConfiguracionRemitos::create([
            'id' => 1,
            'ruta_directa' => true,
            'destino_rechazados' => 'origen',
        ]);

        $admin = User::factory()->create();
        $admin->syncRoles('admin');

        $this->be($admin);

        Livewire::test('remitos.configuracion')
            ->set('rutaDirecta', false)
            ->set('destinoRechazados', 'manager')
            ->call('guardar');

        $config = ConfiguracionRemitos::find(1);
        $this->assertFalse($config->ruta_directa);
        $this->assertEquals('manager', $config->destino_rechazados);
    }

    public function test_rechaza_destino_invalido(): void
    {
        ConfiguracionRemitos::create([
            'id' => 1,
            'ruta_directa' => true,
            'destino_rechazados' => 'origen',
        ]);

        $admin = User::factory()->create();
        $admin->syncRoles('admin');

        $this->be($admin);

        Livewire::test('remitos.configuracion')
            ->set('destinoRechazados', 'invalido')
            ->call('guardar')
            ->assertHasErrors('destinoRechazados');
    }
}
