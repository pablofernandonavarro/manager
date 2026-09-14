<?php

namespace Tests\Feature;

use App\Livewire\Sucursales\AjusteStock;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AjusteStockPermisoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_permiso_no_se_entra_ni_se_aplica_un_ajuste(): void
    {
        Sucursal::create(['nombre' => 'Villa Bosh', 'activo' => true]);
        $this->actingAs(User::factory()->create());

        $this->get(route('sucursales.ajuste-stock'))->assertForbidden();
        $this->get(route('sucursales.ajuste-stock.plantilla'))->assertForbidden();
        Livewire::test(AjusteStock::class)->assertForbidden();
    }

    public function test_con_permiso_se_accede(): void
    {
        Sucursal::create(['nombre' => 'Villa Bosh', 'activo' => true]);
        $usuario = User::factory()->create();
        $usuario->assignRole(tap(Role::findOrCreate('inventario-'.uniqid(), 'web'))->givePermissionTo('stock.ajustar'));
        $this->actingAs($usuario);

        $this->get(route('sucursales.ajuste-stock'))->assertOk();
    }
}
