<?php

namespace Tests\Feature;

use App\Models\MovimientoCaja;
use App\Models\PagoVenta;
use App\Models\Product;
use App\Models\PromocionBancaria;
use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PosCajaSyncTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $villaBosh;

    private Sucursal $centro;

    private PuntoDeVenta $caja;

    private PuntoDeVenta $otraCaja;

    private Product $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->villaBosh = Sucursal::create(['nombre' => 'Villa Bosh']);
        $this->centro = Sucursal::create(['nombre' => 'Centro']);
        $this->caja = PuntoDeVenta::create(['sucursal_id' => $this->villaBosh->id, 'nombre' => 'caja 2', 'secret' => Hash::make('x')]);
        $this->otraCaja = PuntoDeVenta::create(['sucursal_id' => $this->centro->id, 'nombre' => 'caja centro', 'secret' => Hash::make('x')]);
        $this->producto = Product::factory()->create(['codigo_interno' => 'ZAP001']);
    }

    private function como(PuntoDeVenta $pdv, string $metodo, string $uri, array $datos = []): TestResponse
    {
        // En producción cada request autentica de cero; dentro de un test el guard queda con
        // el usuario del request anterior y dos cajas distintas parecerían la misma.
        $this->app['auth']->forgetGuards();

        return $this->withToken($pdv->createToken('pos-sync')->plainTextToken)->json($metodo, $uri, $datos);
    }

    private function promo(array $datos = []): PromocionBancaria
    {
        return PromocionBancaria::create(array_merge([
            'nombre' => 'Galicia 20% crédito',
            'banco' => 'Galicia',
            'medios' => ['credito'],
            'modalidad' => 'descuento',
            'porcentaje' => 20,
            'activa' => true,
        ], $datos));
    }

    // ---- Promociones ----------------------------------------------------------

    public function test_la_caja_recibe_solo_las_promociones_activas_vigentes_y_de_su_sucursal(): void
    {
        $todas = $this->promo(['nombre' => 'Para todas']);
        $mia = $this->promo(['nombre' => 'Solo Villa Bosh', 'sucursales' => [$this->villaBosh->id]]);
        $this->promo(['nombre' => 'Solo Centro', 'sucursales' => [$this->centro->id]]);
        $this->promo(['nombre' => 'Inactiva', 'activa' => false]);
        $this->promo(['nombre' => 'Vencida', 'vigencia_hasta' => now()->subDay()->toDateString()]);
        $futura = $this->promo(['nombre' => 'Empieza la semana que viene', 'vigencia_desde' => now()->addWeek()->toDateString()]);

        $r = $this->como($this->caja, 'GET', '/api/v1/sync/promociones')->assertOk();

        $this->assertEqualsCanonicalizing(
            [$todas->id, $mia->id, $futura->id],
            array_column($r->json('data'), 'id')
        );
        $this->assertEquals(20, collect($r->json('data'))->firstWhere('id', $todas->id)['porcentaje']);
    }

    // ---- Ventas con pagos -----------------------------------------------------

    private function venta(array $extra = []): array
    {
        return array_merge([
            'uuid' => (string) Str::uuid(),
            'numero_venta' => 'PDV04-000010',
            'fecha' => now()->toIso8601String(),
            'subtotal' => 1000,
            'descuento' => 100,
            'total' => 900,
            'items' => [['product_id' => $this->producto->id, 'cantidad' => 1, 'precio_unitario' => 1000, 'subtotal' => 1000]],
        ], $extra);
    }

    public function test_la_venta_guarda_turno_cajero_y_pagos_con_la_promocion(): void
    {
        $promo = $this->promo();
        $turno = (string) Str::uuid();

        $this->como($this->caja, 'POST', '/api/v1/sync/ventas', ['ventas' => [$this->venta([
            'turno_uuid' => $turno,
            'cajero' => 'Ana',
            'metodo_pago' => 'mixto',
            'pagos' => [
                ['medio' => 'efectivo', 'monto' => 500, 'importe' => 500],
                ['medio' => 'credito', 'monto' => 500, 'descuento' => 100, 'importe' => 400, 'tarjeta' => 'visa', 'banco' => 'Galicia', 'cuotas' => 3, 'promocion_id' => $promo->id, 'promocion_nombre' => $promo->nombre],
            ],
        ])]])->assertOk();

        $venta = Venta::with('pagos')->sole();
        $this->assertSame($turno, $venta->turno_uuid);
        $this->assertSame('Ana', $venta->cajero);
        $this->assertCount(2, $venta->pagos);

        $credito = $venta->pagos->firstWhere('medio', 'credito');
        $this->assertSame($promo->id, $credito->promocion_bancaria_id);
        $this->assertSame('400.00', $credito->importe);
        $this->assertSame(3, $credito->cuotas);
    }

    public function test_una_promo_borrada_en_el_manager_no_rechaza_la_venta(): void
    {
        $this->como($this->caja, 'POST', '/api/v1/sync/ventas', ['ventas' => [$this->venta([
            'pagos' => [['medio' => 'credito', 'monto' => 1000, 'descuento' => 100, 'importe' => 900, 'promocion_id' => 999999, 'promocion_nombre' => 'Promo vieja']],
        ])]])->assertOk();

        $pago = PagoVenta::sole();
        $this->assertNull($pago->promocion_bancaria_id);
        $this->assertSame('Promo vieja', $pago->promocion_nombre);
    }

    public function test_una_caja_vieja_sin_pagos_sigue_pudiendo_enviar_ventas(): void
    {
        $this->como($this->caja, 'POST', '/api/v1/sync/ventas', ['ventas' => [$this->venta()]])->assertOk();

        $this->assertSame(1, Venta::count());
        $this->assertSame(0, PagoVenta::count());
    }

    public function test_un_medio_de_pago_desconocido_se_rechaza(): void
    {
        $this->como($this->caja, 'POST', '/api/v1/sync/ventas', ['ventas' => [$this->venta([
            'pagos' => [['medio' => 'bitcoin', 'monto' => 1000, 'importe' => 1000]],
        ])]])->assertUnprocessable();
    }

    // ---- Turnos ---------------------------------------------------------------

    private function turno(array $extra = []): array
    {
        return array_merge([
            'uuid' => (string) Str::uuid(),
            'numero' => 1,
            'cajero' => 'Ana',
            'estado' => 'abierto',
            'fondo_inicial' => 5000,
            'abierto_at' => now()->subHours(8)->toIso8601String(),
            'cantidad_ventas' => 3,
            'total_ventas' => 2700,
            'movimientos' => [],
        ], $extra);
    }

    public function test_el_turno_abierto_se_actualiza_y_al_cerrarse_queda_inmutable(): void
    {
        $datos = $this->turno();
        $retiro = ['uuid' => (string) Str::uuid(), 'tipo' => 'retiro', 'monto' => 1000, 'motivo' => 'Depósito', 'fecha' => now()->toIso8601String()];

        $this->como($this->caja, 'POST', '/api/v1/sync/turnos', ['turnos' => [$datos]])
            ->assertOk()->assertJsonPath('resultados.0.status', 'abierto');

        // Siguiente envío del mismo turno abierto, con más ventas y un retiro
        $this->como($this->caja, 'POST', '/api/v1/sync/turnos', ['turnos' => [array_merge($datos, [
            'cantidad_ventas' => 5, 'total_ventas' => 4000, 'movimientos' => [$retiro],
        ])]])->assertOk();

        $cierre = array_merge($datos, [
            'estado' => 'cerrado', 'cerrado_at' => now()->toIso8601String(), 'cantidad_ventas' => 5, 'total_ventas' => 4000,
            'efectivo_esperado' => 6500, 'efectivo_contado' => 6400, 'diferencia' => -100,
            'resumen' => ['por_medio' => ['efectivo' => 2500, 'credito' => 1500]], 'movimientos' => [$retiro],
        ]);
        $this->como($this->caja, 'POST', '/api/v1/sync/turnos', ['turnos' => [$cierre]])
            ->assertOk()->assertJsonPath('resultados.0.status', 'cerrado');

        // Reenvío del cierre (respuesta perdida) con datos alterados: no se toca
        $this->como($this->caja, 'POST', '/api/v1/sync/turnos', ['turnos' => [array_merge($cierre, ['efectivo_contado' => 999999])]])
            ->assertOk()->assertJsonPath('resultados.0.status', 'duplicado');

        $turno = TurnoCaja::with('movimientos')->sole();
        $this->assertSame('cerrado', $turno->estado);
        $this->assertSame('6400.00', $turno->efectivo_contado);
        $this->assertSame('-100.00', $turno->diferencia);
        $this->assertSame($this->villaBosh->id, $turno->sucursal_id);
        $this->assertCount(1, $turno->movimientos);
        $this->assertSame(1500, $turno->resumen['por_medio']['credito']);
    }

    public function test_un_turno_de_otra_caja_no_se_pisa(): void
    {
        $datos = $this->turno();
        $this->como($this->otraCaja, 'POST', '/api/v1/sync/turnos', ['turnos' => [$datos]])->assertOk();

        $this->como($this->caja, 'POST', '/api/v1/sync/turnos', ['turnos' => [array_merge($datos, ['cajero' => 'Intruso'])]])
            ->assertOk()->assertJsonPath('resultados.0.status', 'rechazado');

        $this->assertSame('Ana', TurnoCaja::sole()->cajero);
    }

    public function test_una_caja_reinstalada_puede_repetir_numero_de_turno(): void
    {
        $this->como($this->caja, 'POST', '/api/v1/sync/turnos', ['turnos' => [$this->turno(['numero' => 1])]])->assertOk();
        $this->como($this->caja, 'POST', '/api/v1/sync/turnos', ['turnos' => [$this->turno(['numero' => 1])]])->assertOk();

        $this->assertSame(2, TurnoCaja::count());
    }

    // ---- Descuento manual y devoluciones -----------------------------------------

    public function test_la_venta_guarda_el_descuento_manual_y_quien_lo_autorizo(): void
    {
        $this->como($this->caja, 'POST', '/api/v1/sync/ventas', ['ventas' => [$this->venta([
            'descuento_manual' => 100, 'descuento_autorizado_por' => 'Ana (supervisora)',
        ])]])->assertOk();

        $venta = Venta::sole();
        $this->assertSame('100.00', $venta->descuento_manual);
        $this->assertSame('Ana (supervisora)', $venta->descuento_autorizado_por);
    }

    private function devolucion(string $ventaUuid, array $extra = []): array
    {
        return array_merge([
            'uuid' => (string) Str::uuid(),
            'venta_uuid' => $ventaUuid,
            'numero' => 'DEV04-000001',
            'tipo' => 'parcial',
            'motivo' => 'Talle equivocado',
            'reintegro' => 'efectivo',
            'total' => 450,
            'autorizado_por' => 'Ana',
            'fecha' => now()->toIso8601String(),
            'items' => [['product_id' => $this->producto->id, 'cantidad' => 1, 'importe' => 450]],
        ], $extra);
    }

    public function test_devoluciones_idempotentes_y_sin_tocar_stock(): void
    {
        $venta = $this->venta();
        $this->como($this->caja, 'POST', '/api/v1/sync/ventas', ['ventas' => [$venta]])->assertOk();
        $stockAntes = \App\Models\StockSucursal::where('product_id', $this->producto->id)->sum('cantidad');

        $dev = $this->devolucion($venta['uuid']);

        $this->como($this->caja, 'POST', '/api/v1/sync/devoluciones', ['devoluciones' => [$dev]])
            ->assertOk()->assertJsonPath('resultados.0.status', 'creada');
        $this->como($this->caja, 'POST', '/api/v1/sync/devoluciones', ['devoluciones' => [$dev]])
            ->assertOk()->assertJsonPath('resultados.0.status', 'duplicada');

        $devolucion = \App\Models\Devolucion::with('items')->sole();
        $this->assertSame($this->villaBosh->id, $devolucion->sucursal_id);
        $this->assertCount(1, $devolucion->items);
        $this->assertSame($venta['uuid'], Venta::sole()->devoluciones()->sole()->venta_uuid);
        $this->assertEquals($stockAntes, \App\Models\StockSucursal::where('product_id', $this->producto->id)->sum('cantidad'));
    }

    public function test_devolucion_invalida_se_rechaza(): void
    {
        $this->como($this->caja, 'POST', '/api/v1/sync/devoluciones', ['devoluciones' => [$this->devolucion((string) Str::uuid(), ['reintegro' => 'bitcoin'])]])
            ->assertUnprocessable();

        $this->assertSame(0, \App\Models\Devolucion::count());
    }

    public function test_un_turno_cerrado_sin_fecha_de_cierre_se_rechaza(): void
    {
        $this->como($this->caja, 'POST', '/api/v1/sync/turnos', ['turnos' => [$this->turno(['estado' => 'cerrado'])]])
            ->assertUnprocessable();

        $this->assertSame(0, TurnoCaja::count());
        $this->assertSame(0, MovimientoCaja::count());
    }
}
