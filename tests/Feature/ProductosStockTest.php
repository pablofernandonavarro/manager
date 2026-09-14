<?php

namespace Tests\Feature;

use App\Livewire\Products\Stock as ProductosStock;
use App\Models\Product;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductosStockTest extends TestCase
{
    use RefreshDatabase;

    private Product $conStock;

    private Product $critico;

    private Product $sinStock;

    private Sucursal $central;

    private Sucursal $norte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->central = Sucursal::create(['nombre' => 'Central', 'is_central' => true]);
        $this->norte = Sucursal::create(['nombre' => 'Norte']);

        // 30 en Central + 12 en Norte = 42, muy por encima del crítico
        $this->conStock = Product::factory()->create([
            'nombre' => 'Zapatillas Running',
            'codigo_interno' => 'ZAP001',
            'stock_critico' => 5,
        ]);
        StockSucursal::create(['sucursal_id' => $this->central->id, 'product_id' => $this->conStock->id, 'cantidad' => 30]);
        StockSucursal::create(['sucursal_id' => $this->norte->id, 'product_id' => $this->conStock->id, 'cantidad' => 12]);

        // 3 unidades con crítico en 10 => está en crítico
        $this->critico = Product::factory()->create([
            'nombre' => 'Remera Basica',
            'codigo_interno' => 'REM045',
            'stock_critico' => 10,
        ]);
        StockSucursal::create(['sucursal_id' => $this->central->id, 'product_id' => $this->critico->id, 'cantidad' => 3]);

        // Sin ninguna fila de stock_sucursal
        $this->sinStock = Product::factory()->create([
            'nombre' => 'Campera Inflable',
            'codigo_interno' => 'CAM900',
            'stock_critico' => 2,
        ]);
    }

    private function fila($productos, string $codigo)
    {
        return collect($productos->items())->firstWhere('codigo_interno', $codigo);
    }

    public function test_el_total_suma_todas_las_sucursales(): void
    {
        $productos = Livewire::test(ProductosStock::class)->viewData('productos');

        $this->assertSame(42, (int) $this->fila($productos, 'ZAP001')->stock_total);
        $this->assertSame(2, (int) $this->fila($productos, 'ZAP001')->sucursales_con_stock);
    }

    public function test_un_producto_sin_filas_de_stock_muestra_cero_y_no_desaparece(): void
    {
        $productos = Livewire::test(ProductosStock::class)->viewData('productos');

        $fila = $this->fila($productos, 'CAM900');

        $this->assertNotNull($fila);
        $this->assertSame(0, (int) $fila->stock_total);
    }

    public function test_el_modal_desglosa_por_sucursal(): void
    {
        $detalle = Livewire::test(ProductosStock::class)
            ->call('abrirDetalle', $this->conStock->id, 'Zapatillas Running', 'ZAP001')
            ->viewData('detalleSucursales');

        $this->assertCount(2, $detalle);
        $this->assertSame(42, $detalle->sum('cantidad'));

        $porSucursal = $detalle->pluck('cantidad', 'sucursal.nombre');
        $this->assertSame(30, $porSucursal['Central']);
        $this->assertSame(12, $porSucursal['Norte']);
    }

    public function test_filtro_solo_con_stock_excluye_los_que_no_tienen(): void
    {
        $productos = Livewire::test(ProductosStock::class)
            ->set('soloConStock', true)
            ->viewData('productos');

        $codigos = collect($productos->items())->pluck('codigo_interno');

        $this->assertContains('ZAP001', $codigos);
        $this->assertContains('REM045', $codigos);
        $this->assertNotContains('CAM900', $codigos);
    }

    public function test_filtro_solo_criticos_usa_el_stock_critico_de_cada_producto(): void
    {
        $productos = Livewire::test(ProductosStock::class)
            ->set('soloCriticos', true)
            ->viewData('productos');

        $codigos = collect($productos->items())->pluck('codigo_interno');

        // 3 unidades con critico=10
        $this->assertContains('REM045', $codigos);
        // 42 unidades con critico=5, no es critico
        $this->assertNotContains('ZAP001', $codigos);
        // sin stock no es "critico", es otra cosa
        $this->assertNotContains('CAM900', $codigos);
    }

    public function test_la_pantalla_carga(): void
    {
        $this->get('/productos/stock')
            ->assertOk()
            ->assertSee('Stock de Productos')
            ->assertSee('ZAP001');
    }

    public function test_la_ruta_stock_no_se_confunde_con_la_ficha_de_producto(): void
    {
        // /productos/stock debe resolver a la pantalla de stock, no a Products\Show con id "stock"
        $this->get('/productos/stock')->assertOk()->assertSee('Stock total');
    }
}
