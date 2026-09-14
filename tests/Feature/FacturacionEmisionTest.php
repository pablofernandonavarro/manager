<?php

namespace Tests\Feature;

use App\Exceptions\AfipException;
use App\Jobs\AutorizarComprobante;
use App\Livewire\Facturacion\Comprobantes;
use App\Models\Comprobante;
use App\Models\ConfiguracionFiscal;
use App\Models\Product;
use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\Facturacion\EmisionComprobantes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FacturacionEmisionTest extends TestCase
{
    use RefreshDatabase;

    private const CUIT = '20123456786';

    private const CUIT_CLIENTE = '30712345671';

    private Sucursal $sucursal;

    private PuntoDeVenta $caja;

    private Product $remera;

    private Product $libro;

    /** @var array<string, int> Último número autorizado en el AFIP simulado, por "pv-tipo". */
    private array $afipUltimo = [];

    /** @var array<string, array{importe: float, doc: string, cae: string}> Emitidos en el AFIP simulado. */
    private array $afipEmitidos = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        ConfiguracionFiscal::actual()->update([
            'razon_social' => 'Zapatería SA', 'cuit' => self::CUIT, 'condicion_iva' => 'responsable_inscripto',
            'inicio_actividades' => '2020-01-01', 'domicilio_comercial' => 'Calle 123', 'entorno' => 'homologacion',
            'facturacion_activa' => true,
            // Ticket de acceso vigente: los tests no pasan por WSAA.
            'ta_token' => 'TOKEN', 'ta_sign' => 'SIGN', 'ta_expira' => now()->addHours(10),
        ]);

        $this->sucursal = Sucursal::create(['nombre' => 'Villa Bosh', 'afip_punto_venta' => 5]);
        $this->caja = PuntoDeVenta::create(['sucursal_id' => $this->sucursal->id, 'nombre' => 'caja 2', 'secret' => 'x']);
        $this->remera = Product::factory()->create(['iva' => 21]);
        $this->libro = Product::factory()->create(['iva' => 10.5]);
    }

    private function como(PuntoDeVenta $pdv, string $metodo, string $uri, array $datos = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($pdv->createToken('pos-sync')->plainTextToken)->json($metodo, $uri, $datos);
    }

    private function venta(array $extra = []): array
    {
        return array_merge([
            'uuid' => (string) Str::uuid(),
            'numero_venta' => 'PDV04-000010',
            'fecha' => now()->toIso8601String(),
            'subtotal' => 1210,
            'descuento' => 0,
            'total' => 1210,
            'items' => [['product_id' => $this->remera->id, 'cantidad' => 1, 'precio_unitario' => 1210, 'subtotal' => 1210]],
            'factura' => ['condicion_iva' => 5, 'doc_tipo' => 99, 'doc_nro' => null, 'nombre' => null],
        ], $extra);
    }

    private static function respuesta(string $metodo, string $interior): string
    {
        return '<?xml version="1.0"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><'.$metodo.'Response xmlns="http://ar.gov.afip.dif.FEV1/"><'.$metodo.'Result>'.$interior.'</'.$metodo.'Result></'.$metodo.'Response></soap:Body></soap:Envelope>';
    }

    /**
     * AFIP simulado con estado: numera, emite y responde consultas.
     *
     * @param  array{solicitar?: string, rechazar?: string}  $fallas  'sin_respuesta' para cortar la conexión.
     */
    private function fingirAfip(array $fallas = []): void
    {
        Http::fake(function (Request $r) use ($fallas) {
            $accion = $r->header('SOAPAction')[0] ?? '';
            $body = $r->body();
            $tag = fn (string $nombre) => preg_match("/<ar:{$nombre}>([^<]*)<\/ar:{$nombre}>/", $body, $m) ? $m[1] : null;

            if (str_contains($accion, 'FECompUltimoAutorizado')) {
                $clave = $tag('PtoVta').'-'.$tag('CbteTipo');

                return Http::response(self::respuesta('FECompUltimoAutorizado', '<CbteNro>'.($this->afipUltimo[$clave] ?? 0).'</CbteNro>'));
            }

            if (str_contains($accion, 'FECompConsultar')) {
                $clave = $tag('PtoVta').'-'.$tag('CbteTipo').'-'.$tag('CbteNro');
                $emitido = $this->afipEmitidos[$clave] ?? null;

                return Http::response(self::respuesta('FECompConsultar', $emitido
                    ? '<ResultGet><CbteFch>'.now()->format('Ymd').'</CbteFch><ImpTotal>'.$emitido['importe'].'</ImpTotal><DocNro>'.$emitido['doc'].'</DocNro><CodAutorizacion>'.$emitido['cae'].'</CodAutorizacion><FchVto>'.now()->addDays(10)->format('Ymd').'</FchVto><Resultado>A</Resultado></ResultGet>'
                    : '<Errors><Err><Code>602</Code><Msg>No existen datos</Msg></Err></Errors>'));
            }

            if (str_contains($accion, 'FECAESolicitar')) {
                $pv = $tag('PtoVta');
                $tipo = $tag('CbteTipo');
                $numero = (int) $tag('CbteDesde');

                if (($fallas['rechazar'] ?? null) !== null) {
                    return Http::response(self::respuesta('FECAESolicitar', '<FeCabResp><Resultado>R</Resultado></FeCabResp><FeDetResp><FECAEDetResponse><Resultado>R</Resultado><Observaciones><Obs><Code>10015</Code><Msg>'.$fallas['rechazar'].'</Msg></Obs></Observaciones><CAE></CAE><CAEFchVto></CAEFchVto></FECAEDetResponse></FeDetResp>'));
                }

                // AFIP numera de a uno: un número salteado se rechaza.
                if ($numero !== ($this->afipUltimo["{$pv}-{$tipo}"] ?? 0) + 1) {
                    return Http::response(self::respuesta('FECAESolicitar', '<FeCabResp><Resultado>R</Resultado></FeCabResp><FeDetResp><FECAEDetResponse><Resultado>R</Resultado><Observaciones><Obs><Code>10016</Code><Msg>Número no correlativo</Msg></Obs></Observaciones></FECAEDetResponse></FeDetResp>'));
                }

                $cae = '7'.str_pad((string) $numero, 13, '0', STR_PAD_LEFT);
                $this->afipUltimo["{$pv}-{$tipo}"] = $numero;
                $this->afipEmitidos["{$pv}-{$tipo}-{$numero}"] = ['importe' => (float) $tag('ImpTotal'), 'doc' => $tag('DocNro'), 'cae' => $cae];

                if (($fallas['solicitar'] ?? null) === 'sin_respuesta') {
                    // AFIP lo emitió, pero la respuesta no llegó.
                    return Http::failedConnection();
                }

                return Http::response(self::respuesta('FECAESolicitar', '<FeCabResp><Resultado>A</Resultado></FeCabResp><FeDetResp><FECAEDetResponse><Resultado>A</Resultado><CAE>'.$cae.'</CAE><CAEFchVto>'.now()->addDays(10)->format('Ymd').'</CAEFchVto></FECAEDetResponse></FeDetResp>'));
            }

            return Http::response('', 500);
        });
    }

    // ---- Reglas --------------------------------------------------------------------

    public function test_tipo_de_factura_segun_emisor_y_receptor(): void
    {
        $this->assertSame(6, EmisionComprobantes::tipoFactura('responsable_inscripto', 5));
        $this->assertSame(6, EmisionComprobantes::tipoFactura('responsable_inscripto', 4));
        $this->assertSame(1, EmisionComprobantes::tipoFactura('responsable_inscripto', 1));
        $this->assertSame(1, EmisionComprobantes::tipoFactura('responsable_inscripto', 6));
        $this->assertSame(11, EmisionComprobantes::tipoFactura('monotributo', 1));
        $this->assertSame(11, EmisionComprobantes::tipoFactura('exento', 5));
    }

    public function test_importes_prorratean_descuentos_y_cierran_exacto_por_alicuota(): void
    {
        // $1000 al 21% y $500 al 10,5% con $150 de descuento en la venta.
        $r = EmisionComprobantes::importes([['centavos' => 100000, 'iva' => 21], ['centavos' => 50000, 'iva' => 10.5]], 135000, 6);

        $this->assertSame(1350.0, $r['importe_total']);
        $this->assertEqualsWithDelta($r['importe_total'], $r['importe_neto'] + $r['importe_iva'], 0.001);
        $this->assertEqualsCanonicalizing([5, 4], array_column($r['alicuotas'], 'id'));

        $al21 = collect($r['alicuotas'])->firstWhere('id', 5);
        $this->assertEqualsWithDelta(900.0, $al21['base'] + $al21['importe'], 0.001, 'el 21% se lleva 2/3 del total');
        $this->assertEqualsWithDelta($al21['base'] * 0.21, $al21['importe'], 0.011);

        $c = EmisionComprobantes::importes([['centavos' => 100000, 'iva' => 21]], 100000, 11);
        $this->assertSame([], $c['alicuotas']);
        $this->assertSame(1000.0, $c['importe_neto']);
    }

    // ---- Sync y emisión en el momento ------------------------------------------------

    public function test_la_venta_sincronizada_con_factura_queda_pendiente_y_se_encola_una_sola_vez(): void
    {
        Queue::fake();
        $venta = $this->venta();

        $this->como($this->caja, 'POST', '/api/v1/sync/ventas', ['ventas' => [$venta]])->assertOk();
        $this->como($this->caja, 'POST', '/api/v1/sync/ventas', ['ventas' => [$venta]])->assertOk();

        $comprobante = Comprobante::sole();
        $this->assertSame('pendiente', $comprobante->estado);
        $this->assertSame(6, $comprobante->tipo);
        $this->assertSame(5, $comprobante->afip_punto_venta);
        $this->assertSame('1000.00', $comprobante->importe_neto);
        $this->assertSame('210.00', $comprobante->importe_iva);
        Queue::assertPushed(AutorizarComprobante::class, 1);

        // Sin el bloque factura (cajas viejas) no se factura.
        $this->como($this->caja, 'POST', '/api/v1/sync/ventas', ['ventas' => [collect($this->venta())->except('factura')->all()]])->assertOk();
        $this->assertSame(1, Comprobante::count());
    }

    public function test_sin_facturacion_activa_o_sin_punto_de_venta_no_se_factura(): void
    {
        Queue::fake();
        ConfiguracionFiscal::actual()->update(['facturacion_activa' => false]);
        $this->como($this->caja, 'POST', '/api/v1/sync/ventas', ['ventas' => [$this->venta()]])->assertOk();

        ConfiguracionFiscal::actual()->update(['facturacion_activa' => true]);
        $this->sucursal->update(['afip_punto_venta' => null]);
        $this->como($this->caja, 'POST', '/api/v1/sync/ventas', ['ventas' => [$this->venta()]])->assertOk();

        $this->assertSame(2, Venta::count());
        $this->assertSame(0, Comprobante::count());
        $this->como($this->caja, 'GET', '/api/v1/pos/facturacion')->assertOk()->assertJsonPath('activa', false);
    }

    public function test_facturar_en_el_momento_numera_pide_cae_y_devuelve_lo_que_imprime_la_caja(): void
    {
        $this->afipUltimo['5-6'] = 41;
        $this->fingirAfip();

        $r = $this->como($this->caja, 'POST', '/api/v1/pos/facturas', $this->venta())->assertOk();

        $r->assertJsonPath('venta.status', 'creada')
            ->assertJsonPath('comprobante.estado', 'autorizado')
            ->assertJsonPath('comprobante.letra', 'B')
            ->assertJsonPath('comprobante.codigo', '006')
            ->assertJsonPath('comprobante.numero', '00005-00000042')
            ->assertJsonPath('comprobante.cae', '70000000000042');
        $this->assertStringContainsString('<svg', $r->json('comprobante.qr_svg'));

        Http::assertSent(fn (Request $req) => str_contains($req->body(), '<ar:ImpTotal>1210.00</ar:ImpTotal>')
            && str_contains($req->body(), '<ar:ImpNeto>1000.00</ar:ImpNeto>')
            && str_contains($req->body(), '<ar:ImpIVA>210.00</ar:ImpIVA>')
            && str_contains($req->body(), '<ar:AlicIva><ar:Id>5</ar:Id><ar:BaseImp>1000.00</ar:BaseImp><ar:Importe>210.00</ar:Importe></ar:AlicIva>')
            && str_contains($req->body(), '<ar:CondicionIVAReceptorId>5</ar:CondicionIVAReceptorId>')
            && str_contains($req->body(), '<ar:DocTipo>99</ar:DocTipo><ar:DocNro>0</ar:DocNro>'));

        $qr = Comprobante::sole()->urlQr(self::CUIT);
        $datos = json_decode(base64_decode(Str::after($qr, '?p=')), true);
        $this->assertSame(['cuit' => (int) self::CUIT, 'ptoVta' => 5, 'tipoCmp' => 6, 'nroCmp' => 42, 'codAut' => 70000000000042],
            array_intersect_key($datos, array_flip(['cuit', 'ptoVta', 'tipoCmp', 'nroCmp', 'codAut'])));
    }

    public function test_factura_a_a_un_responsable_inscripto_y_rechazo_local_sin_cuit(): void
    {
        $this->fingirAfip();

        $this->como($this->caja, 'POST', '/api/v1/pos/facturas', $this->venta([
            'factura' => ['condicion_iva' => 1, 'doc_tipo' => 80, 'doc_nro' => '30-71234567-1', 'nombre' => 'Cliente SRL'],
        ]))->assertOk()->assertJsonPath('comprobante.letra', 'A')->assertJsonPath('comprobante.estado', 'autorizado')
            ->assertJsonPath('comprobante.receptor.doc_nro', self::CUIT_CLIENTE);

        $r = $this->como($this->caja, 'POST', '/api/v1/pos/facturas', $this->venta([
            'factura' => ['condicion_iva' => 1, 'doc_tipo' => 96, 'doc_nro' => '12345678'],
        ]))->assertOk();

        $r->assertJsonPath('comprobante.estado', 'rechazado');
        $this->assertStringContainsString('CUIT', $r->json('comprobante.error'));
        Http::assertSentCount(2, 'el rechazo local no llega a AFIP');
    }

    public function test_si_se_corta_la_respuesta_no_se_emite_dos_veces(): void
    {
        Queue::fake();
        $this->afipUltimo['5-6'] = 9;
        $this->fingirAfip(['solicitar' => 'sin_respuesta']);

        $r = $this->como($this->caja, 'POST', '/api/v1/pos/facturas', $this->venta())->assertOk();
        $r->assertJsonPath('comprobante.estado', 'pendiente')->assertJsonPath('venta.status', 'creada');

        $comprobante = Comprobante::sole();
        $this->assertSame(10, $comprobante->numero, 'el número reservado se guarda para consultarlo');
        Queue::assertPushed(AutorizarComprobante::class);

        // Reintento: AFIP sí lo había emitido. Se recupera consultando, sin pedir otro CAE.
        $this->fingirAfip();
        app(EmisionComprobantes::class)->autorizar($comprobante);

        $comprobante->refresh();
        $this->assertSame('autorizado', $comprobante->estado);
        $this->assertSame(10, $comprobante->numero);
        $this->assertSame('70000000000010', $comprobante->cae);
        $this->assertSame(10, $this->afipUltimo['5-6']);
        Http::assertNotSent(fn (Request $req) => str_contains($req->header('SOAPAction')[0] ?? '', 'FECAESolicitar') && str_contains($req->body(), '<ar:CbteDesde>11</ar:CbteDesde>'));
    }

    public function test_reserva_que_nunca_llego_a_afip_se_libera_y_se_numera_de_nuevo(): void
    {
        $this->fingirAfip();
        $this->como($this->caja, 'POST', '/api/v1/sync/ventas', ['ventas' => [$this->venta()]]);

        // Simula un intento anterior cortado antes de llegar a AFIP.
        $comprobante = Comprobante::sole();
        $comprobante->update(['estado' => 'pendiente', 'numero' => 1, 'cae' => null]);
        $this->afipUltimo = [];
        $this->afipEmitidos = [];

        app(EmisionComprobantes::class)->autorizar($comprobante);

        $this->assertSame('autorizado', $comprobante->fresh()->estado);
        $this->assertSame(1, $comprobante->fresh()->numero);
    }

    public function test_un_rechazo_de_afip_queda_con_el_motivo_y_sin_numero(): void
    {
        $this->fingirAfip(['rechazar' => 'El campo DocNro es invalido']);

        $r = $this->como($this->caja, 'POST', '/api/v1/pos/facturas', $this->venta())->assertOk();

        $r->assertJsonPath('comprobante.estado', 'rechazado');
        $this->assertStringContainsString('DocNro es invalido', $r->json('comprobante.error'));
        $this->assertNull(Comprobante::sole()->numero);
    }

    public function test_cambio_de_entorno_no_autoriza_lo_generado_en_homologacion(): void
    {
        Queue::fake();
        $this->como($this->caja, 'POST', '/api/v1/sync/ventas', ['ventas' => [$this->venta()]]);
        ConfiguracionFiscal::actual()->update(['entorno' => 'produccion']);

        app(EmisionComprobantes::class)->autorizar(Comprobante::sole());

        $this->assertSame('rechazado', Comprobante::sole()->estado);
        Http::assertNothingSent();
    }

    // ---- Notas de crédito ------------------------------------------------------------

    private function devolucion(string $ventaUuid, float $total, array $items): array
    {
        return [
            'uuid' => (string) Str::uuid(), 'venta_uuid' => $ventaUuid, 'numero' => 'DEV-1', 'tipo' => 'parcial',
            'motivo' => 'Talle', 'reintegro' => 'efectivo', 'total' => $total, 'autorizado_por' => 'Supervisor',
            'fecha' => now()->toIso8601String(), 'items' => $items,
        ];
    }

    public function test_la_devolucion_de_una_venta_facturada_emite_nota_de_credito_asociada(): void
    {
        $this->fingirAfip();
        $venta = $this->venta([
            'subtotal' => 1710, 'total' => 1710,
            'items' => [
                ['product_id' => $this->remera->id, 'cantidad' => 1, 'precio_unitario' => 1210, 'subtotal' => 1210],
                ['product_id' => $this->libro->id, 'cantidad' => 1, 'precio_unitario' => 500, 'subtotal' => 500],
            ],
        ]);
        $this->como($this->caja, 'POST', '/api/v1/pos/facturas', $venta)->assertJsonPath('comprobante.estado', 'autorizado');

        $dev = $this->devolucion($venta['uuid'], 500, [['product_id' => $this->libro->id, 'cantidad' => 1, 'importe' => 500]]);
        $this->como($this->caja, 'POST', '/api/v1/sync/devoluciones', ['devoluciones' => [$dev]])->assertOk();

        $nota = Comprobante::whereNotNull('devolucion_id')->sole();
        $this->assertSame(8, $nota->tipo);
        $this->assertSame('autorizado', $nota->estado, 'con la cola sync el job ya la autorizó');
        $this->assertSame([4], array_column($nota->alicuotas, 'id'), 'la NC lleva la alícuota del libro');

        Http::assertSent(fn (Request $req) => str_contains($req->body(), '<ar:CbteTipo>8</ar:CbteTipo>')
            && str_contains($req->body(), '<ar:CbtesAsoc><ar:CbteAsoc><ar:Tipo>6</ar:Tipo><ar:PtoVta>5</ar:PtoVta><ar:Nro>1</ar:Nro>'));

        $this->como($this->caja, 'POST', '/api/v1/pos/comprobantes/estado', ['devoluciones' => [$dev['uuid']]])
            ->assertOk()
            ->assertJsonPath("devoluciones.{$dev['uuid']}.nombre_tipo", 'Nota de crédito B')
            ->assertJsonPath("devoluciones.{$dev['uuid']}.asociado.numero", '00005-00000001');
    }

    public function test_la_nota_de_credito_espera_a_que_se_autorice_la_factura(): void
    {
        Queue::fake();
        $venta = $this->venta();
        $this->como($this->caja, 'POST', '/api/v1/sync/ventas', ['ventas' => [$venta]]);
        $this->como($this->caja, 'POST', '/api/v1/sync/devoluciones', ['devoluciones' => [
            $this->devolucion($venta['uuid'], 1210, [['product_id' => $this->remera->id, 'cantidad' => 1, 'importe' => 1210]]),
        ]]);

        $nota = Comprobante::whereNotNull('devolucion_id')->sole();

        try {
            app(EmisionComprobantes::class)->autorizar($nota);
            $this->fail('Tenía que esperar a la factura');
        } catch (AfipException $e) {
            $this->assertStringContainsString('factura original', $e->getMessage());
        }

        $this->assertSame('pendiente', $nota->fresh()->estado);
        Http::assertNothingSent();
    }

    // ---- Consulta desde la caja, pantalla y permisos --------------------------------

    public function test_una_caja_no_ve_los_comprobantes_de_otra(): void
    {
        $this->fingirAfip();
        $venta = $this->venta();
        $this->como($this->caja, 'POST', '/api/v1/pos/facturas', $venta)->assertOk();

        $otra = PuntoDeVenta::create(['sucursal_id' => $this->sucursal->id, 'nombre' => 'caja 3', 'secret' => 'x']);

        $this->como($otra, 'POST', '/api/v1/pos/comprobantes/estado', ['ventas' => [$venta['uuid']]])
            ->assertOk()->assertExactJson(['ventas' => [], 'devoluciones' => []]);

        // Tampoco puede reusar el uuid para facturarla.
        $this->como($otra, 'POST', '/api/v1/pos/facturas', $venta)->assertOk()->assertJsonPath('comprobante', null);
        $this->assertSame(1, Comprobante::count());

        $this->como($this->caja, 'POST', '/api/v1/pos/comprobantes/estado', ['ventas' => [$venta['uuid']]])
            ->assertJsonPath("ventas.{$venta['uuid']}.estado", 'autorizado');
    }

    public function test_datos_del_emisor_para_la_caja(): void
    {
        $this->como($this->caja, 'GET', '/api/v1/pos/facturacion')->assertOk()
            ->assertJsonPath('activa', true)
            ->assertJsonPath('cuit', '20-12345678-6')
            ->assertJsonPath('condicion_iva_nombre', 'Responsable inscripto')
            ->assertJsonPath('punto_venta', 5);
    }

    public function test_pantalla_de_comprobantes_y_reintento_solo_con_permiso_de_configurar(): void
    {
        Queue::fake();
        $this->fingirAfip(['rechazar' => 'Punto de venta no habilitado']);
        $this->como($this->caja, 'POST', '/api/v1/pos/facturas', $this->venta());
        $comprobante = Comprobante::sole();

        $supervisor = User::factory()->create();
        $supervisor->assignRole(tap(Role::findOrCreate('sup-'.uniqid(), 'web'))->givePermissionTo('facturacion.ver'));
        $this->actingAs($supervisor);

        Livewire::test(Comprobantes::class)
            ->assertSee('Factura B')->assertSee('Punto de venta no habilitado')->assertDontSee('Reintentar')
            ->call('reintentar', $comprobante->id)->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole(tap(Role::findOrCreate('adm-'.uniqid(), 'web'))->givePermissionTo(['facturacion.ver', 'facturacion.configurar']));
        $this->actingAs($admin);

        Livewire::test(Comprobantes::class)->call('reintentar', $comprobante->id)->assertSet('mensaje', 'Factura B enviado a autorizar.');
        $this->assertSame('pendiente', $comprobante->fresh()->estado);
        Queue::assertPushed(AutorizarComprobante::class);

        $this->actingAs(User::factory()->create());
        $this->get(route('facturacion.comprobantes'))->assertForbidden();
    }
}
