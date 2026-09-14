<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Users\Create;
use App\Livewire\Users\Edit;
use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Cajeros y supervisores de caja son usuarios del Manager con PIN y sucursales.
 */
class UsuariosDeCajaTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $villaBosh;

    private Sucursal $centro;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        Role::findOrCreate('admin', 'web')->givePermissionTo(['usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar']);
        Role::findOrCreate('supervisor', 'web');
        Role::findOrCreate('cajero', 'web');

        $this->villaBosh = Sucursal::create(['nombre' => 'Villa Bosh']);
        $this->centro = Sucursal::create(['nombre' => 'Centro']);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_alta_de_cajero_sin_email_ni_contrasena_con_pin_y_una_sucursal(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Create::class)
            ->set('name', 'Beto Cajero')
            ->set('role', 'cajero')
            ->set('pin', '2468')
            ->call('elegirSucursal', $this->villaBosh->id)
            ->call('save')
            ->assertHasNoErrors();

        $beto = User::where('name', 'Beto Cajero')->sole();
        $this->assertNull($beto->email);
        $this->assertTrue(Hash::check('2468', $beto->pin_hash));
        $this->assertTrue($beto->hasRole('cajero'));
        $this->assertSame([$this->villaBosh->id], $beto->sucursales->pluck('id')->all());

        $this->get(route('usuarios.index'))->assertOk()
            ->assertSee('Beto Cajero')->assertSee('Caja: Villa Bosh')->assertSee('Sin acceso al Manager');
    }

    public function test_cajero_en_una_sola_sucursal_y_supervisor_en_varias(): void
    {
        $this->actingAs($this->admin());
        $dos = [$this->villaBosh->id, $this->centro->id];

        Livewire::test(Create::class)
            ->set('name', 'X')->set('role', 'cajero')->set('pin', '1234')->set('sucursales', $dos)
            ->call('save')
            ->assertHasErrors(['sucursales' => 'max']);

        Livewire::test(Create::class)
            ->set('name', 'Ana Supervisora')->set('email', 'ana@example.com')->set('password', 'secreta123')
            ->set('role', 'supervisor')->set('pin', '9876')->set('sucursales', $dos)
            ->call('save')
            ->assertHasNoErrors();

        $ana = User::where('email', 'ana@example.com')->sole();
        $this->assertEqualsCanonicalizing($dos, $ana->sucursales->pluck('id')->all());
        $this->assertSame('supervisor', $ana->rolDeCaja());
    }

    public function test_validaciones_de_pin_sucursal_y_datos_de_acceso(): void
    {
        $this->actingAs($this->admin());

        foreach (['', '12', 'abcd', '1234567'] as $pin) {
            Livewire::test(Create::class)
                ->set('name', 'X')->set('role', 'cajero')->set('pin', $pin)->call('elegirSucursal', $this->villaBosh->id)
                ->call('save')->assertHasErrors(['pin']);
        }

        Livewire::test(Create::class)
            ->set('name', 'X')->set('role', 'cajero')->set('pin', '1234')
            ->call('save')->assertHasErrors(['sucursales' => 'required']);

        // El supervisor sí entra al Manager: email y contraseña obligatorios.
        Livewire::test(Create::class)
            ->set('name', 'X')->set('role', 'supervisor')->set('pin', '1234')->set('sucursales', [$this->villaBosh->id])
            ->call('save')->assertHasErrors(['email' => 'required', 'password' => 'required']);

        $this->assertSame(1, User::count(), 'Solo el admin del test');
    }

    public function test_editar_sin_pin_lo_conserva_y_pasar_a_admin_saca_pin_y_sucursales(): void
    {
        $this->actingAs($this->admin());
        $cajero = User::create(['name' => 'Beto', 'pin_hash' => Hash::make('2468')]);
        $cajero->assignRole('cajero');
        $cajero->sucursales()->attach($this->villaBosh);
        $hash = $cajero->pin_hash;

        Livewire::test(Edit::class, ['userId' => $cajero->id])
            ->assertSet('sucursales', [$this->villaBosh->id])
            ->set('name', 'Roberto')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertSame('Roberto', $cajero->fresh()->name);
        $this->assertSame($hash, $cajero->fresh()->pin_hash);

        Livewire::test(Edit::class, ['userId' => $cajero->id])
            ->set('role', 'admin')->set('email', 'roberto@example.com')->set('password', 'secreta123')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertNull($cajero->fresh()->pin_hash);
        $this->assertSame(0, $cajero->fresh()->sucursales()->count());
    }

    public function test_la_caja_recibe_los_usuarios_de_caja_de_su_sucursal_con_el_hash(): void
    {
        $caja = PuntoDeVenta::create(['sucursal_id' => $this->villaBosh->id, 'nombre' => 'Caja 2', 'secret' => Hash::make('x')]);

        $crear = function (string $nombre, string $rol, array $sucursales, array $extra = []): User {
            $u = User::create(['name' => $nombre, 'pin_hash' => Hash::make('1111'), ...$extra]);
            $u->assignRole($rol);
            $u->sucursales()->attach($sucursales);

            return $u;
        };

        $beto = $crear('Beto', 'cajero', [$this->villaBosh->id]);
        $ana = $crear('Ana', 'supervisor', [$this->villaBosh->id, $this->centro->id]);
        $crear('Otra sucursal', 'cajero', [$this->centro->id]);
        $crear('Inactivo', 'cajero', [$this->villaBosh->id], ['active' => false]);
        $crear('Sin PIN', 'cajero', [$this->villaBosh->id], ['pin_hash' => null]);
        $crear('Admin con sucursal', 'admin', [$this->villaBosh->id]);

        $r = $this->withToken($caja->createToken('pos-sync')->plainTextToken)->getJson('/api/v1/sync/cajeros')->assertOk();

        $this->assertEqualsCanonicalizing([$beto->id, $ana->id], array_column($r->json('data'), 'id'));
        $datosAna = collect($r->json('data'))->firstWhere('id', $ana->id);
        $this->assertSame(['id', 'nombre', 'rol', 'pin_hash'], array_keys($datosAna));
        $this->assertSame('supervisor', $datosAna['rol']);
        $this->assertTrue(Hash::check('1111', $datosAna['pin_hash']));
    }

    public function test_cajeros_e_inactivos_no_entran_al_manager(): void
    {
        $cajero = User::factory()->create(['email' => 'cajero@example.com', 'password' => 'secreta123']);
        $cajero->assignRole('cajero');
        User::factory()->create(['email' => 'inactivo@example.com', 'password' => 'secreta123', 'active' => false]);
        $supervisor = User::factory()->create(['email' => 'super@example.com', 'password' => 'secreta123']);
        $supervisor->assignRole('supervisor');

        foreach (['cajero@example.com', 'inactivo@example.com'] as $email) {
            Livewire::test(Login::class)->set('email', $email)->set('password', 'secreta123')->call('login')->assertHasErrors(['email']);
            $this->assertGuest();
        }

        Livewire::test(Login::class)->set('email', 'super@example.com')->set('password', 'secreta123')->call('login')->assertHasNoErrors();
        $this->assertAuthenticatedAs($supervisor);
    }

    public function test_sin_permiso_no_se_gestionan_usuarios_ni_roles(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');
        $this->actingAs($supervisor);

        $this->get(route('usuarios.index'))->assertForbidden();
        $this->get(route('usuarios.create'))->assertForbidden();
        $this->get(route('roles.index'))->assertForbidden();
        $this->get(route('permissions.index'))->assertForbidden();
        Livewire::test(Create::class)->assertForbidden();
    }
}
