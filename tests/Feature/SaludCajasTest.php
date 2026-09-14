<?php

namespace Tests\Feature;

use App\Livewire\PuntosDeVenta\Index;
use App\Models\CodigoInstalacion;
use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\SaludCaja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SaludCajasTest extends TestCase
{
    use RefreshDatabase;

    private PuntoDeVenta $caja;

    protected function setUp(): void
    {
        parent::setUp();

        $sucursal = Sucursal::create(['nombre' => 'Villa Bosh']);
        $this->caja = PuntoDeVenta::create(['sucursal_id' => $sucursal->id, 'nombre' => 'caja 2', 'secret' => Hash::make('x')]);
    }

    /** @param array<string, mixed> $extra */
    private function reporte(array $extra = []): array
    {
        return array_merge([
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
            'turno_abierto' => ['numero' => 3, 'cajero' => 'Ana', 'abierto_at' => now()->subHours(2)->toIso8601String()],
        ], $extra);
    }

    private function reportar(array $datos): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($this->caja->createToken('pos-sync')->plainTextToken)
            ->postJson('/api/v1/pos/estado', $datos)
            ->assertOk();
        $this->caja->refresh();
    }

    public function test_la_caja_informa_su_estado_y_si_esta_todo_bien_no_hay_problemas(): void
    {
        $this->reportar($this->reporte());

        $this->assertSame('Ana', $this->caja->estado_caja['turno_abierto']['cajero']);
        $this->assertNotNull($this->caja->ultima_conexion_at, 'el middleware registra la conexión');
        $this->assertSame(['nivel' => 'ok', 'problemas' => []], $this->caja->salud());
    }

    public function test_detecta_el_sync_de_stock_trabado_aunque_la_caja_este_en_linea(): void
    {
        // Lo que le pasó a la caja 2: pos:comandos seguía respondiendo, el stock no.
        $this->reportar($this->reporte(['ultima_sincronizacion_stock' => now()->subHours(11)->toIso8601String()]));

        $salud = $this->caja->salud();

        $this->assertTrue($this->caja->estaConectada());
        $this->assertSame('critico', $salud['nivel']);
        $this->assertStringContainsString('No baja stock hace 11 h', $salud['problemas'][0]['texto']);
    }

    public function test_las_antiguedades_se_miden_con_el_reloj_de_la_caja(): void
    {
        // Reloj de la caja 3 horas atrasado, pero el stock se bajó hace 1 minuto para ella.
        $reloj = now()->subHours(3);
        $this->reportar($this->reporte([
            'generado_at' => $reloj->toIso8601String(),
            'ultima_sincronizacion_stock' => $reloj->copy()->subMinute()->toIso8601String(),
        ]));

        $this->assertSame('ok', $this->caja->salud()['nivel']);
    }

    public function test_ventas_sin_enviar_facturas_y_cola(): void
    {
        $this->reportar($this->reporte([
            'ventas_pendientes' => 4,
            'venta_pendiente_mas_vieja' => now()->subHours(2)->toIso8601String(),
            'facturas_pendientes' => 2,
            'factura_pendiente_mas_vieja' => now()->subHour()->toIso8601String(),
            'facturas_rechazadas' => 1,
            'jobs_fallidos' => 3,
            'jobs_en_cola' => 50,
        ]));

        $textos = array_column($this->caja->salud()['problemas'], 'texto');

        $this->assertSame('critico', $this->caja->salud()['nivel']);
        foreach (['4 venta(s) sin enviar', '2 factura(s) sin CAE', '1 factura(s) rechazada(s)', '3 envío(s) fallidos', '50 trabajos acumulados'] as $esperado) {
            $this->assertNotEmpty(array_filter($textos, fn ($t) => str_contains($t, $esperado)), "falta «{$esperado}»");
        }
    }

    public function test_sin_conexion_version_vieja_y_cajas_que_no_informan(): void
    {
        $this->assertSame('sin_datos', $this->caja->salud()['nivel'], 'nunca se conectó');

        $this->caja->forceFill(['ultima_conexion_at' => now(), 'version_pos' => '1.3.0'])->save();
        $salud = $this->caja->salud('1.4.2');
        $this->assertSame('alerta', $salud['nivel']);
        $this->assertStringContainsString('1.3.0 desactualizada', $salud['problemas'][0]['texto']);
        $this->assertStringContainsString('No informa su estado', $salud['problemas'][1]['texto']);

        $this->reportar($this->reporte());
        $this->caja->forceFill(['ultima_conexion_at' => now()->subHours(2)])->save();
        $this->assertSame('critico', SaludCaja::evaluar($this->caja->fresh())['nivel']);

        $this->caja->forceFill(['activo' => false])->save();
        $this->assertSame('inactiva', $this->caja->fresh()->salud()['nivel']);
    }

    public function test_reporte_invalido_se_rechaza_y_sin_token_no_entra(): void
    {
        $this->postJson('/api/v1/pos/estado', $this->reporte())->assertUnauthorized();

        $this->withToken($this->caja->createToken('pos-sync')->plainTextToken)
            ->postJson('/api/v1/pos/estado', ['ventas_pendientes' => -1])
            ->assertUnprocessable();
    }

    public function test_puntos_de_venta_muestra_el_estado_y_avisa(): void
    {
        CodigoInstalacion::generarPara($this->caja, null)->update(['usado_at' => now()]);
        $this->reportar($this->reporte(['ultima_sincronizacion_stock' => now()->subHours(11)->toIso8601String()]));

        $usuario = User::factory()->create();
        $usuario->assignRole(tap(Role::findOrCreate('terminales-'.uniqid(), 'web'))->givePermissionTo('terminales.ver'));
        $this->actingAs($usuario);

        Livewire::test(Index::class)
            ->assertSee('1 caja(s) necesitan atención')
            ->assertSee('Con problemas')
            ->assertSee('No baja stock hace 11 h')
            ->assertSee('caja abierta (Ana)');
    }
}
