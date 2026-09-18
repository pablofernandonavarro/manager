<?php

namespace Tests\Feature;

use App\Livewire\Auditoria\Sincronizacion;
use App\Models\CodigoInstalacion;
use App\Models\ComandoPos;
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
 * Vista en vivo del estado de sincronización de cada caja, sin historial propio: reusa
 * SaludCaja::evaluar() (ya cubierto por SaludCajasTest) y el mismo permiso de
 * "Puntos de venta".
 */
class AuditoriaSincronizacionTest extends TestCase
{
    use RefreshDatabase;

    private PuntoDeVenta $caja;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['terminales.ver', 'terminales.comandos'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $sucursal = Sucursal::create(['nombre' => 'Villa Bosh']);
        $this->caja = PuntoDeVenta::create(['sucursal_id' => $sucursal->id, 'nombre' => 'Caja1', 'secret' => Hash::make('x')]);
        CodigoInstalacion::generarPara($this->caja, null)->update(['usado_at' => now()]);
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

    /** @param array<string, mixed> $extra */
    private function reportar(array $extra = []): void
    {
        $reporte = array_merge([
            'generado_at' => now()->toIso8601String(),
            'ultima_sincronizacion_stock' => now()->subSeconds(40)->toIso8601String(),
            'ultima_sincronizacion_productos' => now()->subHour()->toIso8601String(),
            'catalogo_pendiente' => false,
            'ventas_pendientes' => 0,
            'venta_pendiente_mas_vieja' => null,
            'movimientos_pendientes' => 0,
            'devoluciones_pendientes' => 0,
            'facturas_pendientes' => 0,
            'factura_pendiente_mas_vieja' => null,
            'facturas_rechazadas' => 0,
            'jobs_en_cola' => 0,
            'jobs_fallidos' => 0,
            'turno_abierto' => null,
        ], $extra);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->caja->createToken('pos-sync')->plainTextToken)
            ->postJson('/api/v1/pos/estado', $reporte)
            ->assertOk();
        $this->caja->refresh();
    }

    public function test_sin_permiso_no_se_entra_a_la_pantalla(): void
    {
        $this->actingAs($this->usuarioCon([]));

        Livewire::test(Sincronizacion::class)->assertForbidden();
    }

    public function test_muestra_el_detalle_completo_de_una_caja_con_problemas(): void
    {
        $this->reportar([
            'jobs_fallidos' => 1,
            'movimientos_pendientes' => 2,
            'devoluciones_pendientes' => 3,
            'turno_abierto' => ['numero' => 2, 'cajero' => 'Pablo fernando Navarro', 'abierto_at' => now()->toIso8601String()],
        ]);

        $this->actingAs($this->usuarioCon(['terminales.ver']));

        Livewire::test(Sincronizacion::class)
            ->assertSee('Villa Bosh')
            ->assertSee('Caja1')
            ->assertSee('Revisar')
            ->assertSee('1 envío(s) fallidos en la cola de la caja')
            ->assertSee('Abierto (Pablo fernando Navarro)')
            // Campos que la tabla de Puntos de venta nunca mostró.
            ->assertSee('2', escape: false)
            ->assertSee('3', escape: false);
    }

    public function test_filtra_por_sucursal_y_por_nivel(): void
    {
        $otraSucursal = Sucursal::create(['nombre' => 'Centro']);
        $otraCaja = PuntoDeVenta::create(['sucursal_id' => $otraSucursal->id, 'nombre' => 'Caja2', 'secret' => Hash::make('y')]);
        CodigoInstalacion::generarPara($otraCaja, null)->update(['usado_at' => now()]);

        $this->reportar();

        $this->actingAs($this->usuarioCon(['terminales.ver']));

        Livewire::test(Sincronizacion::class)
            ->set('sucursalId', $this->caja->sucursal_id)
            ->assertSee('Caja1')
            ->assertDontSee('Caja2')
            ->set('sucursalId', null)
            ->set('nivel', 'ok')
            ->assertSee('Caja1')
            ->assertDontSee('Caja2');
    }

    public function test_limpiar_fallidos_encola_la_orden_solo_con_permiso(): void
    {
        $this->reportar(['jobs_fallidos' => 1]);

        $this->actingAs($this->usuarioCon(['terminales.ver']));
        Livewire::test(Sincronizacion::class)
            ->call('limpiarFallidos', $this->caja->id)
            ->assertForbidden();
        $this->assertSame(0, ComandoPos::count());

        $this->actingAs($this->usuarioCon(['terminales.ver', 'terminales.comandos']));
        Livewire::test(Sincronizacion::class)
            ->call('limpiarFallidos', $this->caja->id)
            ->assertHasNoErrors();

        $comando = ComandoPos::where('punto_de_venta_id', $this->caja->id)->sole();
        $this->assertSame('limpiar_fallidos', $comando->comando->value);
    }

    public function test_una_caja_sin_instalar_no_aparece(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Sin instalar']);
        PuntoDeVenta::create(['sucursal_id' => $sucursal->id, 'nombre' => 'Nunca instalada', 'secret' => Hash::make('z')]);

        $this->reportar();
        $this->actingAs($this->usuarioCon(['terminales.ver']));

        Livewire::test(Sincronizacion::class)
            ->assertSee('Caja1')
            ->assertDontSee('Nunca instalada');
    }
}
