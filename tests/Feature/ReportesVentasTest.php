<?php

namespace Tests\Feature;

use App\Livewire\Reportes\Ventas as PantallaReportes;
use App\Models\Devolucion;
use App\Models\Product;
use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\ReporteVentasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportesVentasTest extends TestCase
{
    use RefreshDatabase;

    private PuntoDeVenta $cajaVb;

    private PuntoDeVenta $cajaCentro;

    private Product $producto;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.display_timezone' => 'America/Argentina/Buenos_Aires']);

        $vb = Sucursal::create(['nombre' => 'Villa Bosh']);
        $centro = Sucursal::create(['nombre' => 'Centro']);
        $this->cajaVb = PuntoDeVenta::create(['sucursal_id' => $vb->id, 'nombre' => 'caja 2', 'secret' => Hash::make('x')]);
        $this->cajaCentro = PuntoDeVenta::create(['sucursal_id' => $centro->id, 'nombre' => 'caja centro', 'secret' => Hash::make('x')]);
        $this->producto = Product::factory()->create();

        // 10/09 local: Ana vende 2 en efectivo y crédito con promo
        $this->venta($this->cajaVb, '2026-09-10 10:00', 'Ana', 1000, 0, [['medio' => 'efectivo', 'monto' => 1000, 'importe' => 1000]], 2);
        $this->venta($this->cajaVb, '2026-09-10 15:00', 'Ana', 5000, 800, [
            ['medio' => 'credito', 'monto' => 4000, 'descuento' => 800, 'importe' => 3200, 'tarjeta' => 'visa', 'banco' => 'Galicia', 'cuotas' => 3, 'promocion_nombre' => 'Galicia 20%'],
            ['medio' => 'efectivo', 'monto' => 1000, 'importe' => 1000],
        ], 1, descuentoManual: 0);
        // 10/09 23:30 local (= 11/09 02:30 UTC): tiene que contar el 10
        $this->venta($this->cajaCentro, '2026-09-10 23:30', 'Beto', 2000, 200, [['medio' => 'debito', 'monto' => 1800, 'importe' => 1800, 'tarjeta' => 'maestro']], 1, descuentoManual: 200);
        // 11/09: fuera del rango del 10
        $this->venta($this->cajaVb, '2026-09-11 09:00', 'Ana', 700, 0, [['medio' => 'qr', 'monto' => 700, 'importe' => 700]], 1);
        // Venta de una caja vieja, sin detalle de cobro
        $vieja = $this->venta($this->cajaVb, '2026-09-10 12:00', null, 300, 0, [], 1);

        Devolucion::create([
            'uuid' => (string) Str::uuid(), 'punto_de_venta_id' => $this->cajaVb->id, 'sucursal_id' => $this->cajaVb->sucursal_id,
            'venta_uuid' => Venta::where('cajero', 'Ana')->orderBy('fecha')->first()->uuid, 'numero' => 'DEV-1', 'tipo' => 'parcial',
            'motivo' => 'Talle', 'reintegro' => 'efectivo', 'total' => 500, 'autorizado_por' => 'Sup', 'fecha' => Carbon::parse('2026-09-10 18:00', 'America/Argentina/Buenos_Aires')->utc(),
        ]);
    }

    private function venta(PuntoDeVenta $caja, string $local, ?string $cajero, float $subtotal, float $descuento, array $pagos, int $unidades, float $descuentoManual = 0): Venta
    {
        $venta = Venta::create([
            'uuid' => (string) Str::uuid(),
            'punto_de_venta_id' => $caja->id,
            'sucursal_id' => $caja->sucursal_id,
            'cajero' => $cajero,
            'numero_venta' => 'V-'.Str::random(5),
            'fecha' => Carbon::parse($local, 'America/Argentina/Buenos_Aires')->utc(),
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'descuento_manual' => $descuentoManual,
            'total' => $subtotal - $descuento,
        ]);
        $venta->items()->create(['product_id' => $this->producto->id, 'cantidad' => $unidades, 'precio_unitario' => $subtotal / $unidades, 'subtotal' => $subtotal]);

        foreach ($pagos as $pago) {
            $venta->pagos()->create($pago + ['descuento' => 0]);
        }

        return $venta;
    }

    private function filtros(array $extra = []): array
    {
        return array_merge(['desde' => '2026-09-10', 'hasta' => '2026-09-10'], $extra);
    }

    public function test_indicadores_del_dia_local_incluyen_la_venta_de_la_noche(): void
    {
        $i = app(ReporteVentasService::class)->indicadores($this->filtros());

        $this->assertSame(4, $i['cantidad']);
        $this->assertSame(5, $i['unidades']);
        $this->assertEquals(8300, $i['bruto']);
        $this->assertEquals(1000, $i['descuentos']);
        $this->assertEquals(200, $i['descuentos_manuales']);
        $this->assertEquals(7300, $i['total']);
        $this->assertEquals(500, $i['devoluciones']);
        $this->assertEquals(6800, $i['neto']);
        $this->assertEquals(1825, $i['ticket_promedio']);
    }

    public function test_por_medio_cierra_contra_el_total_incluidas_las_ventas_sin_detalle(): void
    {
        $medios = collect(app(ReporteVentasService::class)->porMedioDePago($this->filtros()))->keyBy('medio');

        $this->assertEquals(2000, $medios['efectivo']['importe']);
        $this->assertSame(2, $medios['efectivo']['cantidad']);
        $this->assertEquals(3200, $medios['credito']['importe']);
        $this->assertEquals(1800, $medios['debito']['importe']);
        $this->assertEquals(300, $medios['sin_detalle']['importe']);
        $this->assertEquals(7300, $medios->sum('importe'));
    }

    public function test_por_cajero_caja_dia_tarjetas_y_promociones(): void
    {
        $servicio = app(ReporteVentasService::class);

        $cajeros = collect($servicio->porCajero($this->filtros()))->keyBy('cajero');
        $this->assertEquals(5200, $cajeros['Ana']['total']);
        $this->assertEquals(500, $cajeros['Ana']['devoluciones']);
        $this->assertEquals(200, $cajeros['Beto']['descuentos_manuales']);
        $this->assertArrayHasKey('Sin cajero', $cajeros);

        $cajas = collect($servicio->porCaja($this->filtros()))->keyBy('caja');
        $this->assertEquals(1800, $cajas['caja centro']['total']);

        $dias = $servicio->porDia(['desde' => '2026-09-10', 'hasta' => '2026-09-11']);
        $this->assertSame([['dia' => '2026-09-10', 'cantidad' => 4, 'total' => 7300.0], ['dia' => '2026-09-11', 'cantidad' => 1, 'total' => 700.0]], $dias);

        $tarjetas = $servicio->tarjetas($this->filtros());
        $this->assertCount(2, $tarjetas);
        $visa = collect($tarjetas)->firstWhere('tarjeta', 'visa');
        $this->assertSame(['medio' => 'credito', 'tarjeta' => 'visa', 'banco' => 'Galicia', 'cuotas' => 3, 'cantidad' => 1, 'importe' => 3200.0], $visa);

        $this->assertSame([['promocion' => 'Galicia 20%', 'usos' => 1, 'descuento' => 800.0, 'cobrado' => 3200.0]], $servicio->promociones($this->filtros()));
    }

    public function test_filtros_por_sucursal_caja_y_cajero(): void
    {
        $servicio = app(ReporteVentasService::class);

        $this->assertSame(1, $servicio->indicadores($this->filtros(['sucursal_id' => $this->cajaCentro->sucursal_id]))['cantidad']);
        $this->assertSame(3, $servicio->indicadores($this->filtros(['punto_de_venta_id' => $this->cajaVb->id]))['cantidad']);
        $soloAna = $servicio->indicadores($this->filtros(['cajero' => 'Ana']));
        $this->assertSame(2, $soloAna['cantidad']);
        $this->assertEquals(500, $soloAna['devoluciones']);
        $this->assertEquals(0, $servicio->indicadores($this->filtros(['cajero' => 'Beto']))['devoluciones']);
    }

    private function usuario(array $permisos): User
    {
        $rol = Role::findOrCreate('rol-'.uniqid(), 'web');
        $rol->givePermissionTo($permisos);
        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    public function test_pantalla_y_exportacion_csv(): void
    {
        $this->actingAs($this->usuario(['reportes.ver']));

        Livewire::test(PantallaReportes::class)
            ->set('desde', '2026-09-10')->set('hasta', '2026-09-10')
            ->assertSee('$6.800,00')
            ->assertSee('Galicia 20%')
            ->assertSee('Sin detalle')
            ->set('hasta', '2026-09-01') // hasta antes que desde: se invierten, no revienta
            ->assertOk();

        $csv = $this->get(route('reportes.ventas.exportar', ['desde' => '2026-09-10', 'hasta' => '2026-09-10']))
            ->assertOk()
            ->streamedContent();

        $filas = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertCount(5, $filas, 'encabezado + 4 ventas');
        $this->assertStringContainsString('10/09/2026 23:30', $csv, 'fecha en hora local');
        $this->assertStringContainsString('Crédito 3200,00 + Efectivo 1000,00', $csv);
    }

    public function test_sin_permiso_no_hay_reportes(): void
    {
        $this->actingAs($this->usuario(['cajas.ver']));

        $this->get(route('reportes.ventas'))->assertForbidden();
        $this->get(route('reportes.ventas.exportar', ['desde' => '2026-09-10', 'hasta' => '2026-09-10']))->assertForbidden();
    }
}
