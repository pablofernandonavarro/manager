<?php

namespace Tests\Feature;

use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosVersionInstaladaTest extends TestCase
{
    use RefreshDatabase;

    private PuntoDeVenta $caja;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $sucursal = Sucursal::create(['nombre' => 'Villa Bosh']);
        $this->caja = PuntoDeVenta::create(['sucursal_id' => $sucursal->id, 'nombre' => 'caja 2', 'secret' => Hash::make('x')]);
        $this->token = $this->caja->createToken('pos-sync')->plainTextToken;
    }

    private function llamar(array $cabeceras = []): void
    {
        $this->withToken($this->token)->withHeaders($cabeceras)->getJson('/api/v1/pos/remitos')->assertOk();
    }

    public function test_la_caja_informa_version_tipo_y_conexion_en_cada_llamada(): void
    {
        $this->llamar(['X-POS-Version' => '1.0.4', 'X-POS-Tipo' => 'escritorio']);

        $this->caja->refresh();
        $this->assertSame('1.0.4', $this->caja->version_pos);
        $this->assertSame('escritorio', $this->caja->tipo_instalacion);
        $this->assertTrue($this->caja->estaConectada());
    }

    public function test_una_caja_vieja_sin_encabezados_solo_registra_la_conexion(): void
    {
        $this->llamar();

        $this->caja->refresh();
        $this->assertNull($this->caja->version_pos);
        $this->assertNotNull($this->caja->ultima_conexion_at);
    }

    public function test_valores_raros_en_los_encabezados_se_ignoran(): void
    {
        $this->llamar(['X-POS-Version' => '<script>alert(1)</script>', 'X-POS-Tipo' => 'hackeada']);

        $this->caja->refresh();
        $this->assertNull($this->caja->version_pos);
        $this->assertNull($this->caja->tipo_instalacion);
    }

    public function test_no_escribe_en_cada_llamada_pero_si_cuando_cambia_la_version(): void
    {
        $this->llamar(['X-POS-Version' => '1.0.3', 'X-POS-Tipo' => 'escritorio']);
        $hace30s = now()->subSeconds(30);
        $this->caja->forceFill(['ultima_conexion_at' => $hace30s])->saveQuietly();

        // Misma versión dentro del minuto: no se toca
        $this->llamar(['X-POS-Version' => '1.0.3', 'X-POS-Tipo' => 'escritorio']);
        $this->assertSame($hace30s->timestamp, $this->caja->fresh()->ultima_conexion_at->timestamp);

        // Se actualizó la caja: se anota enseguida
        $this->llamar(['X-POS-Version' => '1.0.4', 'X-POS-Tipo' => 'escritorio']);
        $this->assertSame('1.0.4', $this->caja->fresh()->version_pos);
    }

    public function test_la_pantalla_muestra_la_version_y_si_esta_desactualizada(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('pos-escritorio/pos-escritorio.zip', 'zip');
        Storage::disk('local')->put('pos-escritorio/pos-escritorio.json', json_encode([
            'version' => '1.0.4', 'formato' => 'zip', 'tamano' => 3, 'sha256' => 'x', 'generado_at' => now()->toIso8601String(),
        ]));

        $this->llamar(['X-POS-Version' => '1.0.3', 'X-POS-Tipo' => 'escritorio']);

        Permission::findOrCreate('terminales.ver', 'web');
        $rol = Role::findOrCreate('mirar', 'web');
        $rol->givePermissionTo('terminales.ver');
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        $this->actingAs($usuario)->get(route('pdv.index'))
            ->assertOk()
            ->assertSee('1.0.3')
            ->assertSee('Escritorio')
            ->assertSee('Desactualizada · última 1.0.4')
            ->assertSee('En línea');
    }
}
