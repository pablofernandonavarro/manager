<?php

namespace Tests\Feature;

use App\Enums\EstadoRemito;
use App\Models\MovimientoStock;
use App\Models\Product;
use App\Models\PuntoDeVenta;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use App\Services\RemitoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PosRemitosTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $central;

    private Sucursal $villaBosh;

    private Sucursal $centro;

    private PuntoDeVenta $cajaVillaBosh;

    private string $token;

    private Product $zapatillas;

    private Product $remera;

    protected function setUp(): void
    {
        parent::setUp();

        $this->central = Sucursal::create(['nombre' => 'Central', 'is_central' => true]);
        $this->villaBosh = Sucursal::create(['nombre' => 'Villa Bosh']);
        $this->centro = Sucursal::create(['nombre' => 'Centro']);

        $this->cajaVillaBosh = PuntoDeVenta::create([
            'sucursal_id' => $this->villaBosh->id, 'nombre' => 'Pos 1 villa bosh', 'secret' => Hash::make('x'),
        ]);
        $this->token = $this->cajaVillaBosh->createToken('pos-sync')->plainTextToken;

        $this->zapatillas = Product::factory()->create(['nombre' => 'Zapatillas', 'codigo_interno' => 'ZAP001']);
        $this->remera = Product::factory()->create(['nombre' => 'Remera', 'codigo_interno' => 'REM001']);

        StockSucursal::create(['sucursal_id' => $this->central->id, 'product_id' => $this->zapatillas->id, 'cantidad' => 20]);
        StockSucursal::create(['sucursal_id' => $this->central->id, 'product_id' => $this->remera->id, 'cantidad' => 10]);
        StockSucursal::create(['sucursal_id' => $this->villaBosh->id, 'product_id' => $this->zapatillas->id, 'cantidad' => 1]);
    }

    private function comoCaja(string $metodo, string $uri): TestResponse
    {
        return $this->withToken($this->token)->json($metodo, $uri);
    }

    private function remitoA(Sucursal $destino, array $items)
    {
        return app(RemitoService::class)->crear($this->central->id, $destino->id, $items);
    }

    public function test_la_caja_ve_solo_los_remitos_en_transito_hacia_su_sucursal(): void
    {
        $paraMi = $this->remitoA($this->villaBosh, [$this->zapatillas->id => 5, $this->remera->id => 2]);
        $this->remitoA($this->centro, [$this->zapatillas->id => 1]);
        $yaRecibido = $this->remitoA($this->villaBosh, [$this->remera->id => 1]);
        app(RemitoService::class)->confirmar($yaRecibido);

        $r = $this->comoCaja('GET', '/api/v1/pos/remitos')->assertOk();

        $this->assertSame([$paraMi->id], array_column($r->json('data'), 'id'));
        $this->assertSame('Central', $r->json('data.0.origen'));
        $this->assertCount(2, $r->json('data.0.items'));
        $this->assertSame('ZAP001', collect($r->json('data.0.items'))->firstWhere('product_id', $this->zapatillas->id)['codigo']);
    }

    public function test_recibir_suma_el_stock_devuelve_el_stock_nuevo_y_registra_la_caja(): void
    {
        $remito = $this->remitoA($this->villaBosh, [$this->zapatillas->id => 5, $this->remera->id => 2]);

        $r = $this->comoCaja('POST', "/api/v1/pos/remitos/{$remito->id}/recibir")->assertOk();

        $this->assertSame('recibido', $r->json('status'));
        $stock = collect($r->json('stock'))->pluck('cantidad', 'product_id');
        $this->assertSame(6, (int) $stock[$this->zapatillas->id]);
        $this->assertSame(2, (int) $stock[$this->remera->id]);

        $remito->refresh();
        $this->assertSame(EstadoRemito::Confirmado, $remito->estado);
        $this->assertSame($this->cajaVillaBosh->id, $remito->confirmado_por_punto_de_venta_id);
        $this->assertSame(2, MovimientoStock::where('punto_de_venta_id', $this->cajaVillaBosh->id)->count());
    }

    public function test_reintentar_la_recepcion_no_suma_dos_veces(): void
    {
        $remito = $this->remitoA($this->villaBosh, [$this->zapatillas->id => 5]);

        $this->comoCaja('POST', "/api/v1/pos/remitos/{$remito->id}/recibir")->assertOk();
        $r = $this->comoCaja('POST', "/api/v1/pos/remitos/{$remito->id}/recibir")->assertOk();

        $this->assertSame('ya_recibido', $r->json('status'));
        $this->assertSame(6, (int) collect($r->json('stock'))->firstWhere('product_id', $this->zapatillas->id)['cantidad']);
        $this->assertSame(6, (int) StockSucursal::where('sucursal_id', $this->villaBosh->id)->where('product_id', $this->zapatillas->id)->value('cantidad'));
    }

    public function test_una_caja_no_puede_recibir_un_remito_de_otra_sucursal(): void
    {
        $ajeno = $this->remitoA($this->centro, [$this->zapatillas->id => 3]);

        $this->comoCaja('POST', "/api/v1/pos/remitos/{$ajeno->id}/recibir")->assertNotFound();

        $this->assertSame(EstadoRemito::Remitido, $ajeno->fresh()->estado);
    }

    public function test_un_remito_cancelado_no_se_puede_recibir(): void
    {
        $remito = $this->remitoA($this->villaBosh, [$this->zapatillas->id => 3]);
        app(RemitoService::class)->cancelar($remito);

        $this->comoCaja('POST', "/api/v1/pos/remitos/{$remito->id}/recibir")->assertStatus(409);

        $this->assertSame(1, (int) StockSucursal::where('sucursal_id', $this->villaBosh->id)->where('product_id', $this->zapatillas->id)->value('cantidad'));
    }

    public function test_la_ruta_sync_confirmar_suma_una_sola_vez_y_registra_el_movimiento(): void
    {
        $remito = $this->remitoA($this->villaBosh, [$this->zapatillas->id => 5]);

        $r = $this->comoCaja('POST', "/api/v1/sync/remitos/{$remito->id}/confirmar")->assertOk();

        $this->assertSame(6, $r->json('stock_actualizado.0.cantidad'));
        $this->assertSame('ZAP001', $r->json('stock_actualizado.0.codigo_interno'));
        $this->assertSame($this->cajaVillaBosh->id, $remito->fresh()->confirmado_por_punto_de_venta_id);
        $this->assertSame(1, MovimientoStock::where('punto_de_venta_id', $this->cajaVillaBosh->id)->count());

        $this->comoCaja('POST', "/api/v1/sync/remitos/{$remito->id}/confirmar")->assertStatus(422);
        $this->assertSame(6, (int) StockSucursal::where('sucursal_id', $this->villaBosh->id)->where('product_id', $this->zapatillas->id)->value('cantidad'));

        $ajeno = $this->remitoA($this->centro, [$this->zapatillas->id => 1]);
        $this->comoCaja('POST', "/api/v1/sync/remitos/{$ajeno->id}/confirmar")->assertForbidden();
        $this->assertSame(EstadoRemito::Remitido, $ajeno->fresh()->estado);
    }

    public function test_sin_token_no_se_accede(): void
    {
        $this->getJson('/api/v1/pos/remitos')->assertUnauthorized();
        $this->postJson('/api/v1/pos/remitos/1/recibir')->assertUnauthorized();
    }
}
