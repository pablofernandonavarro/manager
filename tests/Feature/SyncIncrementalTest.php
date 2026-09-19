<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Jobs\PrepararImportacionProductos;
use App\Models\DetallePrecio;
use App\Models\ImportacionProducto;
use App\Models\ImportacionProductoLote;
use App\Models\ListaPrecio;
use App\Models\Product;
use App\Models\PuntoDeVenta;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use App\Services\ImportacionProductos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sincronización incremental por páginas con cursor (cajas 1.6.4+) y lo que la sostiene:
 * stock que no mueve el catálogo, bloqueo de stock entre cajas e importación CSV.
 */
class SyncIncrementalTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $villaBosh;

    private PuntoDeVenta $caja;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00', 'UTC'));
        $this->villaBosh = Sucursal::create(['nombre' => 'Villa Bosh']);
        $this->caja = PuntoDeVenta::create(['sucursal_id' => $this->villaBosh->id, 'nombre' => 'Caja 1', 'secret' => Hash::make('x')]);
        $this->token = $this->caja->createToken('pos-sync')->plainTextToken;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Storage::disk('local')->deleteDirectory('importaciones');

        parent::tearDown();
    }

    private function producto(string $codigo, array $extra = []): Product
    {
        return Product::create([
            'product_type' => ProductType::SIMPLE, 'codigo_interno' => $codigo, 'nombre' => "Producto {$codigo}",
            'precio' => 100, 'es_vendible' => true, 'estado' => 1, ...$extra,
        ]);
    }

    /** @return array<string, mixed> */
    private function pedir(string $url): array
    {
        return $this->withToken($this->token)->getJson($url)->assertOk()->json();
    }

    public function test_productos_por_paginas_entregan_cada_producto_una_vez_y_el_cursor_fija_el_tope(): void
    {
        foreach (range(1, 7) as $i) {
            $this->producto("P-{$i}");
            $this->travel(1)->seconds();
        }

        $primera = $this->pedir('/api/v1/sync/productos?limit=3');
        $this->assertCount(3, $primera['data']);
        $this->assertNotNull($primera['next_cursor']);
        $this->assertSame(Carbon::now()->subMinutes(15)->toIso8601String(), $primera['synced_at'], 'Marca con 15 minutos de margen');

        // Modificado mientras la caja pagina: queda para el próximo delta, no alarga esta descarga.
        $this->travel(5)->seconds();
        Product::where('codigo_interno', 'P-1')->first()->update(['precio' => 999]);

        $codigos = array_column($primera['data'], 'codigo_interno');
        $cursor = $primera['next_cursor'];
        while ($cursor) {
            $pagina = $this->pedir('/api/v1/sync/productos?limit=3&cursor='.$cursor);
            $codigos = [...$codigos, ...array_column($pagina['data'], 'codigo_interno')];
            $this->assertSame($primera['synced_at'], $pagina['synced_at'], 'Todas las páginas devuelven la misma marca');
            $cursor = $pagina['next_cursor'];
        }

        $this->assertSame(['P-1', 'P-2', 'P-3', 'P-4', 'P-5', 'P-6', 'P-7'], $codigos);

        // El delta desde la marca trae lo modificado (y lo del margen, que la caja reaplica sin duplicar).
        $delta = $this->pedir('/api/v1/sync/productos?limit=100&updated_since='.urlencode($primera['synced_at']));
        $this->assertContains('P-1', array_column($delta['data'], 'codigo_interno'));
        $this->assertEquals(999, collect($delta['data'])->firstWhere('codigo_interno', 'P-1')['precio']);

        $this->withToken($this->token)->getJson('/api/v1/sync/productos?limit=3&cursor=basura')->assertStatus(422);
    }

    public function test_con_incluir_inactivos_manda_las_bajas_y_nunca_los_modelos(): void
    {
        $this->producto('ACTIVO');
        $this->producto('NO-VENDIBLE', ['es_vendible' => false]);
        $this->producto('BORRADO')->delete();
        Product::create(['product_type' => ProductType::CONFIGURABLE, 'codigo_interno' => 'MODELO', 'nombre' => 'Modelo', 'es_vendible' => false, 'estado' => 1]);

        $sinInactivos = array_column($this->pedir('/api/v1/sync/productos?limit=50')['data'], 'codigo_interno');
        $this->assertSame(['ACTIVO'], $sinInactivos);

        $conInactivos = collect($this->pedir('/api/v1/sync/productos?limit=50&incluir_inactivos=1')['data'])->pluck('activo', 'codigo_interno')->all();
        $this->assertSame(['ACTIVO' => true, 'NO-VENDIBLE' => false, 'BORRADO' => false], $conInactivos);
    }

    public function test_la_descarga_de_stock_por_paginas_queda_registrada_en_la_caja(): void
    {
        foreach (['A', 'B'] as $codigo) {
            StockSucursal::create(['sucursal_id' => $this->villaBosh->id, 'product_id' => $this->producto($codigo)->id, 'cantidad' => 5]);
        }

        // Una sola página (lo de todos los minutos): no escribe nada.
        $this->pedir('/api/v1/sync/stock?limit=100');
        $this->assertNull($this->caja->fresh()->stock_descarga_iniciada_at);

        $primera = $this->pedir('/api/v1/sync/stock?limit=1');
        $caja = $this->caja->fresh();
        $this->assertNotNull($caja->stock_descarga_iniciada_at);
        $this->assertNotNull($caja->stock_descarga_avance_at);
        $this->assertNull($caja->stock_descarga_terminada_at);

        $this->travel(2)->minutes();
        $segunda = $this->pedir('/api/v1/sync/stock?limit=1&cursor='.$primera['next_cursor']);
        $caja = $this->caja->fresh();
        $this->assertTrue($caja->stock_descarga_avance_at->gt($caja->stock_descarga_iniciada_at));
        $this->assertNull($caja->stock_descarga_terminada_at);

        $this->pedir('/api/v1/sync/stock?limit=1&cursor='.$segunda['next_cursor']);
        $this->assertNotNull($this->caja->fresh()->stock_descarga_terminada_at);

        // Una descarga nueva reabre la marca.
        $this->pedir('/api/v1/sync/stock?limit=1');
        $this->assertNull($this->caja->fresh()->stock_descarga_terminada_at);
    }

    public function test_stock_por_paginas_solo_de_la_sucursal_y_con_delta(): void
    {
        $otra = Sucursal::create(['nombre' => 'Centro']);
        $a = $this->producto('A');
        $b = $this->producto('B');
        StockSucursal::create(['sucursal_id' => $this->villaBosh->id, 'product_id' => $a->id, 'cantidad' => 5]);
        StockSucursal::create(['sucursal_id' => $this->villaBosh->id, 'product_id' => $b->id, 'cantidad' => 7]);
        StockSucursal::create(['sucursal_id' => $otra->id, 'product_id' => $a->id, 'cantidad' => 99]);

        $completo = $this->pedir('/api/v1/sync/stock?limit=1');
        $siguiente = $this->pedir('/api/v1/sync/stock?limit=1&cursor='.$completo['next_cursor']);
        $this->assertSame([['product_id' => $a->id, 'codigo_interno' => 'A', 'cantidad' => 5]], $completo['data']);
        $this->assertSame($b->id, $siguiente['data'][0]['product_id']);
        $this->assertNull($this->pedir('/api/v1/sync/stock?limit=1&cursor='.$siguiente['next_cursor'])['next_cursor']);

        // Pasado el margen de 15 minutos, la marca ya no incluye A ni B; después se vende B.
        $this->travel(20)->minutes();
        $marca = $this->pedir('/api/v1/sync/stock?limit=100')['synced_at'];
        $this->travel(1)->minutes();
        StockSucursal::aplicarDelta($this->villaBosh->id, $b->id, -2);
        $delta = $this->pedir('/api/v1/sync/stock?limit=100&updated_since='.urlencode($marca));
        $this->assertSame([['product_id' => $b->id, 'codigo_interno' => 'B', 'cantidad' => 5]], $delta['data']);

        // Sin limit: el contrato de siempre (lo usan las cajas anteriores a la 1.6.4).
        $legado = json_decode($this->withToken($this->token)->getJson('/api/v1/sync/stock')->assertOk()->streamedContent(), true);
        $this->assertCount(2, $legado['data']);
        $this->assertArrayHasKey('synced_at', $legado);
    }

    public function test_precios_por_paginas_por_id(): void
    {
        $lista = ListaPrecio::create(['nombre' => 'PUBLICO', 'factor' => 1, 'activo' => true]);
        $this->villaBosh->listasPrecios()->attach($lista->id, ['es_default' => true]);
        foreach (['X', 'Y', 'Z'] as $codigo) {
            DetallePrecio::create(['lista_precio_id' => $lista->id, 'product_id' => $this->producto($codigo)->id, 'precio_override' => 50]);
        }

        $uno = $this->pedir('/api/v1/sync/precios?limit=2');
        $dos = $this->pedir('/api/v1/sync/precios?limit=2&cursor='.$uno['next_cursor']);

        $this->assertSame(['X', 'Y', 'Z'], [...array_column($uno['precios'], 'codigo_interno'), ...array_column($dos['precios'], 'codigo_interno')]);
        $this->assertNull($dos['next_cursor']);
        $this->assertTrue($uno['listas'][0]['es_default']);

        $legado = json_decode($this->withToken($this->token)->getJson('/api/v1/sync/precios')->assertOk()->streamedContent(), true);
        $this->assertCount(3, $legado['precios']);
        $this->assertCount(1, $legado['listas']);
    }

    public function test_una_venta_mueve_el_stock_pero_no_la_fecha_del_catalogo(): void
    {
        $p = $this->producto('VENDIDO');
        StockSucursal::create(['sucursal_id' => $this->villaBosh->id, 'product_id' => $p->id, 'cantidad' => 10]);
        $catalogoAntes = $p->fresh()->getRawOriginal('updated_at');

        $this->travel(10)->minutes();
        $this->withToken($this->token)->postJson('/api/v1/sync/ventas', ['ventas' => [[
            'uuid' => (string) Str::uuid(), 'numero_venta' => 'PDV1-1', 'fecha' => now()->toIso8601String(),
            'subtotal' => 200, 'total' => 200,
            'items' => [['product_id' => $p->id, 'cantidad' => 2, 'precio_unitario' => 100, 'subtotal' => 200]],
        ]]])->assertOk();

        $this->assertSame(8, (int) StockSucursal::where('product_id', $p->id)->value('cantidad'));
        $this->assertSame(8, (int) $p->fresh()->stock);
        $this->assertSame($catalogoAntes, $p->fresh()->getRawOriginal('updated_at'), 'El catálogo no vuelve a bajar por una venta');
        $this->assertSame(now()->format('Y-m-d H:i:s'), StockSucursal::where('product_id', $p->id)->first()->getRawOriginal('updated_at'));
    }

    public function test_aplicar_delta_crea_la_fila_y_nunca_deja_negativo(): void
    {
        $p = $this->producto('NUEVO');

        StockSucursal::aplicarDelta($this->villaBosh->id, $p->id, 3);
        StockSucursal::aplicarDelta($this->villaBosh->id, $p->id, -10);

        $this->assertSame(1, StockSucursal::where('product_id', $p->id)->count());
        $this->assertSame(0, (int) StockSucursal::where('product_id', $p->id)->value('cantidad'));
    }

    public function test_importacion_csv_por_lotes_arranca_cada_lote_en_su_byte(): void
    {
        $porLote = ImportacionProductos::FILAS_POR_LOTE;
        // Windows-1252 y ; como separador, como guarda Excel en español.
        $lineas = ['codigo;nombre;precio;stock Villa Bosh'];
        for ($i = 1; $i <= $porLote + 2; $i++) {
            $lineas[] = "CSV-{$i};".mb_convert_encoding("Camión {$i}", 'Windows-1252', 'UTF-8').';"1.250,50";3';
        }
        $ruta = 'importaciones/'.Str::uuid().'.csv';
        Storage::disk('local')->put($ruta, implode("\r\n", $lineas)."\r\n");

        $inspeccion = app(ImportacionProductos::class)->inspeccionar(Storage::disk('local')->path($ruta));
        $this->assertSame([], $inspeccion['errores']);
        $this->assertSame($porLote + 3, $inspeccion['ultima_fila']);

        $registro = ImportacionProducto::create(['archivo' => $ruta, 'nombre_original' => 'grande.csv', 'estado' => ImportacionProducto::PENDIENTE]);
        PrepararImportacionProductos::dispatch($registro->id);

        $registro->refresh();
        $this->assertSame(ImportacionProducto::COMPLETADA, $registro->estado);
        $this->assertSame([$porLote + 2, $porLote + 2, 0], [$registro->total_filas, $registro->filas_exitosas, $registro->filas_con_error]);
        $this->assertSame([$porLote, 2], ImportacionProductoLote::orderBy('lote')->pluck('procesadas')->all());
        $ultimo = Product::where('codigo_interno', 'CSV-'.($porLote + 2))->sole();
        $this->assertSame('Camión '.($porLote + 2), $ultimo->nombre);
        $this->assertEquals(1250.5, $ultimo->precio);
        $this->assertSame(3, (int) $ultimo->stock);
    }
}
