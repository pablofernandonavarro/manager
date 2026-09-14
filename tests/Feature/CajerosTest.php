<?php

namespace Tests\Feature;

use App\Livewire\Cajeros\Index as Cajeros;
use App\Models\Cajero;
use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CajerosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $rol = Role::findOrCreate('rol-'.uniqid(), 'web');
        $rol->givePermissionTo('cajeros.gestionar');
        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    public function test_alta_con_pin_hasheado_y_edicion_sin_cambiar_el_pin(): void
    {
        $this->actingAs($this->admin());
        $sucursal = Sucursal::create(['nombre' => 'Villa Bosh']);

        Livewire::test(Cajeros::class)
            ->call('crear')
            ->set('nombre', 'Ana')->set('rol', 'supervisor')->set('pin', '4321')->set('sucursales', [(string) $sucursal->id])
            ->call('guardar')->assertHasNoErrors();

        $ana = Cajero::sole();
        $this->assertTrue(Hash::check('4321', $ana->pin_hash));
        $this->assertSame([$sucursal->id], $ana->sucursales);
        $hashOriginal = $ana->pin_hash;

        Livewire::test(Cajeros::class)
            ->call('editar', $ana->id)->set('nombre', 'Ana María')->set('pin', '')
            ->call('guardar')->assertHasNoErrors();

        $this->assertSame('Ana María', $ana->fresh()->nombre);
        $this->assertSame($hashOriginal, $ana->fresh()->pin_hash, 'PIN vacío al editar = no cambia');

        Livewire::test(Cajeros::class)->call('editar', $ana->id)->set('pin', '999999')->call('guardar');
        $this->assertTrue(Hash::check('999999', $ana->fresh()->pin_hash));
    }

    public function test_validaciones_del_pin(): void
    {
        $this->actingAs($this->admin());

        foreach (['', '12', 'abcd', '1234567'] as $pin) {
            Livewire::test(Cajeros::class)->set('nombre', 'X')->set('pin', $pin)->call('guardar')->assertHasErrors(['pin']);
        }

        $this->assertSame(0, Cajero::count());
    }

    public function test_sin_permiso_no_se_gestionan(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('cajeros.index'))->assertForbidden();
    }

    public function test_la_caja_recibe_los_cajeros_activos_de_su_sucursal_con_el_hash(): void
    {
        $vb = Sucursal::create(['nombre' => 'Villa Bosh']);
        $centro = Sucursal::create(['nombre' => 'Centro']);
        $caja = PuntoDeVenta::create(['sucursal_id' => $vb->id, 'nombre' => 'caja 2', 'secret' => Hash::make('x')]);

        $todas = Cajero::create(['nombre' => 'Ana', 'pin_hash' => Hash::make('1111'), 'rol' => 'supervisor']);
        $mia = Cajero::create(['nombre' => 'Beto', 'pin_hash' => Hash::make('2222'), 'sucursales' => [$vb->id]]);
        Cajero::create(['nombre' => 'Otra sucursal', 'pin_hash' => Hash::make('3333'), 'sucursales' => [$centro->id]]);
        Cajero::create(['nombre' => 'Inactivo', 'pin_hash' => Hash::make('4444'), 'activo' => false]);

        $r = $this->withToken($caja->createToken('pos-sync')->plainTextToken)->getJson('/api/v1/sync/cajeros')->assertOk();

        $this->assertEqualsCanonicalizing([$todas->id, $mia->id], array_column($r->json('data'), 'id'));
        $beto = collect($r->json('data'))->firstWhere('id', $mia->id);
        $this->assertTrue(Hash::check('2222', $beto['pin_hash']));
        $this->assertSame(['id', 'nombre', 'rol', 'pin_hash'], array_keys($beto));
    }
}
