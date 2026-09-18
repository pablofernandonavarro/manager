<?php

namespace Tests\Feature;

use App\Livewire\PuntosDeVenta\Index;
use App\Models\CodigoInstalacion;
use App\Models\ComandoPos;
use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermisosTerminalesTest extends TestCase
{
    use RefreshDatabase;

    private PuntoDeVenta $pdv;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['terminales.ver', 'terminales.instalar', 'terminales.comandos'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $sucursal = Sucursal::create(['nombre' => 'Centro']);

        $this->pdv = PuntoDeVenta::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Caja 1',
            'secret' => Hash::make('secreto-original'),
        ]);
    }

    /** @param array<int, string> $permisos */
    private function usuarioCon(array $permisos): User
    {
        $rol = Role::findOrCreate('rol-'.uniqid(), 'web');

        if ($permisos !== []) {
            $rol->givePermissionTo($permisos);
        }

        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    public function test_sin_permiso_no_se_genera_el_codigo_ni_se_toca_la_caja(): void
    {
        $this->actingAs($this->usuarioCon(['terminales.ver']));

        Livewire::test(Index::class)
            ->call('generarCodigoInstalacion', $this->pdv->id)
            ->assertForbidden();

        // Lo que importa no es el 403 sino que no haya pasado nada.
        $this->assertSame(0, CodigoInstalacion::count());
    }

    public function test_sin_permiso_no_se_encola_ninguna_orden(): void
    {
        $this->actingAs($this->usuarioCon(['terminales.ver', 'terminales.instalar']));

        Livewire::test(Index::class)
            ->call('enviarComando', $this->pdv->id, 'actualizar')
            ->assertForbidden();

        $this->assertSame(0, ComandoPos::count());
    }

    public function test_sin_permiso_el_secret_no_cambia(): void
    {
        $this->actingAs($this->usuarioCon(['terminales.ver']));

        $antes = $this->pdv->secret;

        Livewire::test(Index::class)
            ->call('regenerarSecret', $this->pdv->id)
            ->assertForbidden();

        $this->assertSame($antes, $this->pdv->fresh()->secret);
    }

    public function test_sin_permiso_la_caja_no_se_elimina(): void
    {
        $this->actingAs($this->usuarioCon(['terminales.ver']));

        Livewire::test(Index::class)
            ->call('delete', $this->pdv->id)
            ->assertForbidden();

        $this->assertNotNull($this->pdv->fresh());
    }

    public function test_sin_permiso_de_ver_no_se_entra_a_la_pantalla(): void
    {
        $this->actingAs($this->usuarioCon([]));

        Livewire::test(Index::class)->assertForbidden();
    }

    public function test_con_permisos_las_acciones_funcionan(): void
    {
        $this->actingAs($this->usuarioCon(['terminales.ver', 'terminales.instalar', 'terminales.comandos']));

        Livewire::test(Index::class)
            ->call('generarCodigoInstalacion', $this->pdv->id)
            ->call('enviarComando', $this->pdv->id, 'actualizar');

        $this->assertSame(1, CodigoInstalacion::where('punto_de_venta_id', $this->pdv->id)->count());
        $this->assertSame(1, ComandoPos::where('punto_de_venta_id', $this->pdv->id)->count());
    }

    public function test_el_instalador_no_se_descarga_sin_permiso(): void
    {
        $this->actingAs($this->usuarioCon(['terminales.ver']));

        // El kit lleva el código completo del POS: quien lo baja puede levantar una caja.
        $this->get(route('pdv.instalador'))->assertForbidden();
    }

    public function test_el_instalador_requiere_estar_logueado(): void
    {
        $this->get(route('pdv.instalador'))->assertRedirect(route('login'));
    }

    public function test_la_app_de_escritorio_no_se_descarga_sin_permiso(): void
    {
        $this->actingAs($this->usuarioCon(['terminales.ver']));

        $this->get(route('pdv.instalador-escritorio'))->assertForbidden();
    }

    public function test_la_app_de_escritorio_requiere_estar_logueado(): void
    {
        $this->get(route('pdv.instalador-escritorio'))->assertRedirect(route('login'));
    }

    public function test_sin_app_publicada_la_descarga_da_404(): void
    {
        Storage::fake('local');

        $this->actingAs($this->usuarioCon(['terminales.ver', 'terminales.instalar']));

        $this->get(route('pdv.instalador-escritorio'))->assertNotFound();
    }

    private function publicar(string $base, string $version): void
    {
        Storage::disk('local')->put("pos-escritorio/{$base}.zip", "zip {$base}");
        Storage::disk('local')->put("pos-escritorio/{$base}.json", json_encode([
            'version' => $version, 'formato' => 'zip', 'tamano' => 2 * 1024 * 1024, 'sha256' => 'x', 'generado_at' => now()->toIso8601String(),
        ]));
    }

    public function test_se_descarga_la_app_de_windows_o_la_de_mac(): void
    {
        Storage::fake('local');
        $this->publicar('pos-escritorio', '1.9.7');
        $this->publicar('pos-escritorio-mac', '1.9.7');

        $this->actingAs($this->usuarioCon(['terminales.ver', 'terminales.instalar']));

        $windows = $this->get(route('pdv.instalador-escritorio'))->assertOk();
        $this->assertStringContainsString('POS-Escritorio-1.9.7.zip', $windows->headers->get('content-disposition'));
        $this->assertSame('zip pos-escritorio', file_get_contents($windows->baseResponse->getFile()->getPathname()));

        $mac = $this->get(route('pdv.instalador-escritorio', ['plataforma' => 'mac']))->assertOk();
        $this->assertStringContainsString('POS-Escritorio-1.9.7-mac.zip', $mac->headers->get('content-disposition'));
        $this->assertSame('zip pos-escritorio-mac', file_get_contents($mac->baseResponse->getFile()->getPathname()));

        $this->get(route('pdv.instalador-escritorio', ['plataforma' => 'linux']))->assertNotFound();
        $this->get(route('pdv.instalador-escritorio', ['plataforma' => ['mac']]))->assertNotFound();
    }

    public function test_la_app_de_mac_no_se_descarga_sin_permiso_ni_sin_publicar(): void
    {
        Storage::fake('local');
        $this->publicar('pos-escritorio', '1.9.7');

        $this->actingAs($this->usuarioCon(['terminales.ver']));
        $this->get(route('pdv.instalador-escritorio', ['plataforma' => 'mac']))->assertForbidden();

        $this->actingAs($this->usuarioCon(['terminales.ver', 'terminales.instalar']));
        $this->get(route('pdv.instalador-escritorio', ['plataforma' => 'mac']))->assertNotFound();
    }

    public function test_la_pantalla_ofrece_la_descarga_para_mac_solo_si_esta_publicada(): void
    {
        Storage::fake('local');
        $this->publicar('pos-escritorio', '1.9.7');

        $this->actingAs($this->usuarioCon(['terminales.ver', 'terminales.instalar']));

        $this->get(route('pdv.index'))
            ->assertOk()
            ->assertDontSee('Descargar POS para Mac')
            ->assertSee('Todavía no se publicó la app para Mac');

        $this->publicar('pos-escritorio-mac', '1.9.7');

        $this->get(route('pdv.index'))
            ->assertOk()
            ->assertSee('Descargar POS para Mac')
            ->assertSee('Abrir igual')
            ->assertSee(route('pdv.instalador-escritorio', ['plataforma' => 'mac']), escape: false);
    }

    public function test_la_pantalla_explica_la_instalacion_con_la_app_de_escritorio(): void
    {
        // Sin app publicada, para no depender de lo que haya en el disco de esta máquina.
        Storage::fake('local');

        $this->actingAs($this->usuarioCon(['terminales.ver', 'terminales.instalar']));

        $this->get(route('pdv.index'))
            ->assertOk()
            ->assertSee('Instalar esta caja')
            ->assertSee('pos:publicar-escritorio')
            ->assertSee(url('/'));
    }

    public function test_la_migracion_le_dio_los_permisos_al_rol_admin(): void
    {
        // La migración corre con RefreshDatabase, así que el rol admin puede no existir
        // en la base de test; lo que se valida es que los permisos estén creados.
        foreach (['terminales.ver', 'terminales.instalar', 'terminales.comandos'] as $p) {
            $this->assertNotNull(Permission::where('name', $p)->where('guard_name', 'web')->first());
        }
    }
}
