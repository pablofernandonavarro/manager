<?php

namespace Tests\Feature;

use App\Livewire\Ventas\PorArticulo;
use App\Models\DetalleVenta;
use App\Models\Product;
use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class VentasPorArticuloTest extends TestCase
{
    use RefreshDatabase;

    private Product $zapatillas;

    private Product $remera;

    private Sucursal $centro;

    private Sucursal $norte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->centro = Sucursal::create(['nombre' => 'Sucursal Centro']);
        $this->norte = Sucursal::create(['nombre' => 'Sucursal Norte']);

        $cajaCentro = PuntoDeVenta::create([
            'sucursal_id' => $this->centro->id,
            'nombre' => 'Caja 1',
            'secret' => Hash::make('x'),
        ]);

        $cajaNorte = PuntoDeVenta::create([
            'sucursal_id' => $this->norte->id,
            'nombre' => 'Caja 2',
            'secret' => Hash::make('x'),
        ]);

        $this->zapatillas = Product::factory()->create([
            'nombre' => 'Zapatillas Running',
            'codigo_interno' => 'ZAP001',
        ]);

        $this->remera = Product::factory()->create([
            'nombre' => 'Remera Basica',
            'codigo_interno' => 'REM045',
        ]);

        // Centro vende 2 zapatillas
        $this->crearVenta($cajaCentro, 'PDV01-000001', [[$this->zapatillas, 2, 45000]]);

        // Norte vende 3 zapatillas y 1 remera en el mismo ticket
        $this->crearVenta($cajaNorte, 'PDV02-000001', [
            [$this->zapatillas, 3, 45000],
            [$this->remera, 1, 15000],
        ]);
    }

    /** @param array<int, array{0: Product, 1: int, 2: int}> $items */
    private function crearVenta(PuntoDeVenta $pdv, string $numero, array $items, ?string $fechaUtc = null): Venta
    {
        $total = collect($items)->sum(fn ($i) => $i[1] * $i[2]);

        $venta = Venta::create([
            'uuid' => (string) Str::uuid(),
            'punto_de_venta_id' => $pdv->id,
            'sucursal_id' => $pdv->sucursal_id,
            'numero_venta' => $numero,
            'fecha' => $fechaUtc ?? now(),
            'subtotal' => $total,
            'descuento' => 0,
            'total' => $total,
        ]);

        foreach ($items as [$producto, $cantidad, $precio]) {
            DetalleVenta::create([
                'venta_id' => $venta->id,
                'product_id' => $producto->id,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'subtotal' => $cantidad * $precio,
            ]);
        }

        return $venta;
    }

    public function test_agrupa_las_unidades_vendidas_por_articulo(): void
    {
        $articulos = Livewire::test(PorArticulo::class)->viewData('articulos');

        $zap = $articulos->firstWhere('codigo_interno', 'ZAP001');

        // 2 en Centro + 3 en Norte
        $this->assertSame('5', (string) $zap->unidades);
        $this->assertSame(2, (int) $zap->cantidad_ventas);
        $this->assertSame(2, (int) $zap->cantidad_sucursales);
        $this->assertEquals(225000, (float) $zap->facturado);
    }

    public function test_el_detalle_muestra_en_que_sucursal_y_caja_se_vendio(): void
    {
        $lineas = Livewire::test(PorArticulo::class)
            ->call('abrirDetalle', $this->zapatillas->id, 'Zapatillas Running', 'ZAP001')
            ->viewData('lineas');

        $this->assertCount(2, $lineas);

        $sucursales = $lineas->pluck('sucursal')->all();
        $cajas = $lineas->pluck('punto_de_venta')->all();

        $this->assertContains('Sucursal Centro', $sucursales);
        $this->assertContains('Sucursal Norte', $sucursales);
        $this->assertContains('Caja 1', $cajas);
        $this->assertContains('Caja 2', $cajas);
    }

    public function test_filtrar_por_sucursal_acota_unidades_y_detalle(): void
    {
        $componente = Livewire::test(PorArticulo::class)
            ->set('sucursalSeleccionada', $this->centro->id);

        $zap = $componente->viewData('articulos')->firstWhere('codigo_interno', 'ZAP001');
        $this->assertSame('2', (string) $zap->unidades);

        $lineas = $componente->call('abrirDetalle', $this->zapatillas->id, 'Zapatillas Running', 'ZAP001')
            ->viewData('lineas');

        $this->assertCount(1, $lineas);
        $this->assertSame('Sucursal Centro', $lineas->first()->sucursal);
    }

    public function test_buscar_por_codigo_filtra_el_listado(): void
    {
        $articulos = Livewire::test(PorArticulo::class)
            ->set('busqueda', 'REM045')
            ->viewData('articulos');

        $this->assertCount(1, $articulos->items());
        $this->assertSame('REM045', $articulos->first()->codigo_interno);
    }

    public function test_una_venta_de_la_noche_cae_en_su_dia_argentino(): void
    {
        $campera = Product::factory()->create(['nombre' => 'Campera Nocturna', 'codigo_interno' => 'CAM999']);

        // 23:00 del 10/03 en Argentina = 02:00 UTC del 11/03.
        $this->crearVenta(PuntoDeVenta::first(), 'PDV01-000099', [[$campera, 1, 90000]], '2025-03-11 02:00:00');

        $codigos = fn (string $dia) => Livewire::test(PorArticulo::class)
            ->set('desde', $dia)
            ->set('hasta', $dia)
            ->viewData('articulos')
            ->pluck('codigo_interno')
            ->all();

        $this->assertSame(['CAM999'], $codigos('2025-03-10'));
        $this->assertSame([], $codigos('2025-03-11'));
    }

    public function test_una_fecha_invalida_en_los_filtros_se_ignora(): void
    {
        $articulos = Livewire::test(PorArticulo::class)
            ->set('desde', 'no-es-una-fecha')
            ->set('hasta', '2025-13-45')
            ->viewData('articulos');

        $this->assertSame(2, $articulos->total());
    }

    public function test_la_pantalla_carga_y_muestra_el_articulo(): void
    {
        $this->get('/ventas')
            ->assertOk()
            ->assertSee('Ventas por Artículo')
            ->assertSee('ZAP001');
    }
}
