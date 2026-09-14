<?php

namespace Tests\Feature;

use App\Enums\TipoMovimiento;
use App\Livewire\Products\Create;
use App\Livewire\Products\Edit;
use App\Models\MovimientoStock;
use App\Models\Product;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\Ean13;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Alta desde la pantalla de productos: código de barras automático y stock por sucursal.
 */
class AltaDeProductosConStockTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $villaBosh;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->villaBosh = Sucursal::create(['nombre' => 'Villa Bosh']);
    }

    /** @return array{attributes: list<array{slug: string, product_column: string, value: string}>, stock: int} */
    private function variante(string $color, string $talle, int $stock): array
    {
        return [
            'attributes' => [
                ['slug' => 'color', 'product_column' => 'color', 'value' => $color],
                ['slug' => 'talle', 'product_column' => 'n_talle', 'value' => $talle],
            ],
            'stock' => $stock,
        ];
    }

    public function test_configurable_con_variantes_genera_ean_y_carga_stock_en_la_sucursal(): void
    {
        Livewire::test(Create::class)
            ->set('product_type', 'configurable')
            ->set('nombre', 'Remera lisa')
            ->set('codigo_interno', 'REM-100')
            ->set('precio', 10000)
            ->set('variants', [$this->variante('Negro', 'M', 5), $this->variante('Negro', 'L', 0)])
            ->set('sucursalStockId', $this->villaBosh->id)
            ->call('save')
            ->assertHasNoErrors();

        $negroM = Product::where('codigo_interno', 'REM-100-NEG-M')->sole();
        $negroL = Product::where('codigo_interno', 'REM-100-NEG-L')->sole();

        $this->assertTrue(Ean13::valido($negroM->codigo_barras));
        $this->assertSame(Ean13::interno($negroM->id), $negroM->codigo_barras);
        $this->assertStringContainsString($negroM->codigo_barras, $negroM->busqueda, 'La búsqueda incluye el código generado');
        $this->assertEmpty(Product::where('codigo_interno', 'REM-100')->sole()->codigo_barras, 'El modelo no se vende: sin código');

        $this->assertSame(5, (int) StockSucursal::where('product_id', $negroM->id)->where('sucursal_id', $this->villaBosh->id)->value('cantidad'));
        $this->assertSame(5, (int) $negroM->fresh()->stock);
        $this->assertSame(0, StockSucursal::where('product_id', $negroL->id)->count(), 'Sin cantidad no se crea stock');
        $this->assertSame(TipoMovimiento::Entrada, MovimientoStock::where('product_id', $negroM->id)->sole()->tipo);
    }

    public function test_con_stock_hay_que_elegir_sucursal_y_sin_stock_no(): void
    {
        Livewire::test(Create::class)
            ->set('product_type', 'configurable')->set('nombre', 'Buzo')->set('codigo_interno', 'BUZ-1')
            ->set('variants', [$this->variante('Gris', 'S', 3)])
            ->call('save')
            ->assertHasErrors(['sucursalStockId' => 'required']);

        $this->assertSame(0, Product::count());

        Livewire::test(Create::class)
            ->set('product_type', 'configurable')->set('nombre', 'Buzo')->set('codigo_interno', 'BUZ-1')
            ->set('variants', [$this->variante('Gris', 'S', 0)])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, StockSucursal::count());
    }

    public function test_producto_simple_con_stock_y_codigo_de_barras_propio(): void
    {
        Livewire::test(Create::class)
            ->set('product_type', 'simple')->set('nombre', 'Cinturón')->set('codigo_interno', 'CIN-1')
            ->set('codigo_barras', '7790001112223')
            ->set('stock', 4)->set('sucursalStockId', $this->villaBosh->id)
            ->call('save')
            ->assertHasNoErrors();

        $cinturon = Product::where('codigo_interno', 'CIN-1')->sole();
        $this->assertSame('7790001112223', $cinturon->codigo_barras, 'Un código cargado no se pisa');
        $this->assertSame(4, (int) $cinturon->stock);
        $this->assertSame(4, (int) StockSucursal::where('product_id', $cinturon->id)->value('cantidad'));
    }

    public function test_variantes_agregadas_desde_editar_y_sufijos_que_chocan(): void
    {
        Livewire::test(Create::class)
            ->set('product_type', 'configurable')->set('nombre', 'Jean')->set('codigo_interno', 'JEAN-1')
            ->set('variants', [$this->variante('Azul', '40', 0)])
            ->call('save');

        $jean = Product::where('codigo_interno', 'JEAN-1')->sole();

        Livewire::test(Edit::class, ['productId' => $jean->id])
            ->set('variants', [$this->variante('Azul marino', '40', 2)])
            ->set('sucursalStockId', $this->villaBosh->id)
            ->call('saveVariants')
            ->assertHasNoErrors();

        $marino = Product::where('parent_id', $jean->id)->where('color', 'Azul marino')->sole();
        $this->assertSame('JEAN-1-AZU-40-2', $marino->codigo_interno, 'Azul y Azul marino no comparten SKU');
        $this->assertTrue(Ean13::valido($marino->codigo_barras));
        $this->assertSame(2, (int) StockSucursal::where('product_id', $marino->id)->value('cantidad'));
    }
}
