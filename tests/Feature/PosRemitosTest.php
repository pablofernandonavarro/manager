<?php

namespace Tests\Feature;

use App\Enums\EstadoRemito;
use App\Models\ConfiguracionRemitos;
use App\Models\MovimientoStock;
use App\Models\Product;
use App\Models\PuntoDeVenta;
use App\Models\Remito;
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

    private function comoCaja(string $metodo, string $uri, array $data = []): TestResponse
    {
        return $this->withToken($this->token)->json($metodo, $uri, $data);
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

    public function test_enviados_muestra_lo_que_la_sucursal_de_la_caja_mando_con_su_estado(): void
    {
        StockSucursal::updateOrCreate(
            ['sucursal_id' => $this->villaBosh->id, 'product_id' => $this->zapatillas->id],
            ['cantidad' => 5]
        );

        // Villa Bosh manda a Central: se confirma (llegó).
        $confirmado = app(RemitoService::class)->crear($this->villaBosh->id, $this->central->id, [$this->zapatillas->id => 1]);
        app(RemitoService::class)->confirmar($confirmado);

        // Villa Bosh manda a Centro: queda en camino.
        app(RemitoService::class)->crear($this->villaBosh->id, $this->centro->id, [$this->zapatillas->id => 2]);

        // Otra sucursal manda algo: no debe aparecer en el historial de Villa Bosh.
        $this->remitoA($this->centro, [$this->remera->id => 1]);

        $r = $this->comoCaja('GET', '/api/v1/pos/remitos/enviados')->assertOk();

        $this->assertCount(2, $r->json('data'));
        $estados = collect($r->json('data'))->pluck('estado', 'destino');
        $this->assertSame('confirmado', $estados['Central']);
        $this->assertSame('remitido', $estados['Centro']);
        $this->assertNotNull(collect($r->json('data'))->firstWhere('destino', 'Central')['confirmado_at']);
        $this->assertNull(collect($r->json('data'))->firstWhere('destino', 'Centro')['confirmado_at']);
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

    public function test_crear_remito_desde_caja_usa_su_sucursal_como_origen(): void
    {
        StockSucursal::updateOrCreate(
            ['sucursal_id' => $this->villaBosh->id, 'product_id' => $this->zapatillas->id],
            ['cantidad' => 10]
        );

        $r = $this->comoCaja('POST', '/api/v1/pos/remitos', [
            'destino_sucursal_id' => $this->central->id,
            'items' => [$this->zapatillas->id => 3],
            'observaciones' => 'prueba',
        ])->assertCreated();

        $remito = Remito::find($r->json('data.id'));
        $this->assertSame($this->villaBosh->id, $remito->sucursal_origen_id);
        $this->assertSame($this->central->id, $remito->sucursal_destino_id);
        $this->assertSame($this->cajaVillaBosh->id, $remito->creado_por_punto_de_venta_id);
        $this->assertSame(7, (int) StockSucursal::where('sucursal_id', $this->villaBosh->id)->where('product_id', $this->zapatillas->id)->value('cantidad'));
    }

    public function test_crear_remito_desde_caja_respeta_ruta_directa_false(): void
    {
        StockSucursal::updateOrCreate(
            ['sucursal_id' => $this->villaBosh->id, 'product_id' => $this->zapatillas->id],
            ['cantidad' => 10]
        );
        ConfiguracionRemitos::truncate();
        ConfiguracionRemitos::create(['id' => 1, 'ruta_directa' => false, 'destino_rechazados' => 'origen']);

        $r = $this->comoCaja('POST', '/api/v1/pos/remitos', [
            'destino_sucursal_id' => $this->centro->id,
            'items' => [$this->zapatillas->id => 1],
        ])->assertStatus(422);

        $this->assertStringContainsString('Central', $r->json('message'));
    }

    public function test_crear_remito_desde_caja_permite_central_cuando_ruta_directa_false(): void
    {
        StockSucursal::updateOrCreate(
            ['sucursal_id' => $this->villaBosh->id, 'product_id' => $this->zapatillas->id],
            ['cantidad' => 10]
        );
        ConfiguracionRemitos::updateOrCreate(['id' => 1], ['ruta_directa' => false, 'destino_rechazados' => 'origen']);

        $r = $this->comoCaja('POST', '/api/v1/pos/remitos', [
            'destino_sucursal_id' => $this->central->id,
            'items' => [$this->zapatillas->id => 1],
        ])->assertCreated();

        $remito = Remito::find($r->json('data.id'));
        $this->assertSame($this->central->id, $remito->sucursal_destino_id);
    }

    public function test_crear_remito_desde_caja_rechaza_sin_stock_suficiente(): void
    {
        $r = $this->comoCaja('POST', '/api/v1/pos/remitos', [
            'destino_sucursal_id' => $this->central->id,
            'items' => [$this->zapatillas->id => 100],
        ])->assertStatus(422);

        $this->assertStringContainsString('stock suficiente', $r->json('message'));
    }

    public function test_recibir_parcialmente_acredita_solo_lo_recibido_y_crea_hijo(): void
    {
        $remito = $this->remitoA($this->villaBosh, [$this->zapatillas->id => 10, $this->remera->id => 5]);
        ConfiguracionRemitos::updateOrCreate(['id' => 1], ['ruta_directa' => true, 'destino_rechazados' => 'origen']);

        $r = $this->comoCaja('POST', "/api/v1/pos/remitos/{$remito->id}/recibir", [
            'cantidades_recibidas' => [$this->zapatillas->id => 6, $this->remera->id => 5],
        ])->assertOk();

        $this->assertSame('recibido', $r->json('status'));
        $stock = collect($r->json('stock'))->pluck('cantidad', 'product_id');
        $this->assertSame(7, (int) $stock[$this->zapatillas->id]);
        $this->assertSame(5, (int) $stock[$this->remera->id]);

        $remito->refresh();
        $hijo = $remito->hijos()->first();
        $this->assertNotNull($hijo);
        $this->assertSame($this->zapatillas->id, array_keys($hijo->detalles->pluck('cantidad', 'product_id')->toArray())[0]);
        $this->assertSame(4, (int) collect($hijo->detalles)->firstWhere('product_id', $this->zapatillas->id)->cantidad);

        $this->assertNotNull($r->json('remito_hijo'));
        $this->assertSame($this->central->id, $hijo->sucursal_destino_id);
    }

    public function test_recibir_parcialmente_con_config_elegir_sin_destino_falla(): void
    {
        $remito = $this->remitoA($this->villaBosh, [$this->zapatillas->id => 10]);
        ConfiguracionRemitos::truncate();
        ConfiguracionRemitos::create(['id' => 1, 'ruta_directa' => true, 'destino_rechazados' => 'elegir']);

        $r = $this->comoCaja('POST', "/api/v1/pos/remitos/{$remito->id}/recibir", [
            'cantidades_recibidas' => [$this->zapatillas->id => 6],
        ])->assertStatus(422);

        $this->assertStringContainsString('Elegí', $r->json('message'));
        $remito->refresh();
        $this->assertSame(EstadoRemito::Remitido, $remito->estado);
    }

    public function test_configuracion_devuelve_valores_actuales(): void
    {
        ConfiguracionRemitos::truncate();
        ConfiguracionRemitos::create(['id' => 1, 'ruta_directa' => true, 'destino_rechazados' => 'manager']);

        $r = $this->comoCaja('GET', '/api/v1/pos/remitos/configuracion')->assertOk();

        $this->assertTrue($r->json('ruta_directa'));
        $this->assertSame('manager', $r->json('destino_rechazados'));
    }

    public function test_sync_sucursales_devuelve_activas(): void
    {
        Sucursal::create(['nombre' => 'Inactiva', 'activo' => false]);

        $r = $this->comoCaja('GET', '/api/v1/sync/sucursales')->assertOk();

        $ids = collect($r->json('data'))->pluck('id');
        $this->assertContains($this->central->id, $ids);
        $this->assertContains($this->villaBosh->id, $ids);
        $this->assertNotContains(Sucursal::where('nombre', 'Inactiva')->value('id'), $ids);
    }

    public function test_sin_token_no_se_accede(): void
    {
        $this->getJson('/api/v1/pos/remitos')->assertUnauthorized();
        $this->postJson('/api/v1/pos/remitos/1/recibir')->assertUnauthorized();
    }
}
