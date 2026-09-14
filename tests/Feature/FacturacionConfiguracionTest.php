<?php

namespace Tests\Feature;

use App\Exceptions\AfipException;
use App\Livewire\Facturacion\Configuracion;
use App\Models\ConfiguracionFiscal;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Afip\Certificados;
use App\Services\Afip\Wsaa;
use App\Support\Cuit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FacturacionConfiguracionTest extends TestCase
{
    use RefreshDatabase;

    private const CUIT = '20123456786';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $rol = Role::findOrCreate('rol-'.uniqid(), 'web');
        $rol->givePermissionTo('facturacion.configurar');
        $user = User::factory()->create();
        $user->assignRole($rol);
        $this->actingAs($user);
    }

    private function configurar(array $extra = []): ConfiguracionFiscal
    {
        $config = ConfiguracionFiscal::actual();
        $config->update(array_merge([
            'razon_social' => 'Zapatería SA', 'cuit' => self::CUIT, 'condicion_iva' => 'responsable_inscripto',
            'inicio_actividades' => '2020-01-01', 'domicilio_comercial' => 'Calle 123', 'entorno' => 'homologacion',
        ], $extra));

        return $config;
    }

    /** Certificado autofirmado con la clave dada, como el .crt que entregaría AFIP. */
    private function certificadoPara(string $clavePem, string $cuit = self::CUIT, int $dias = 365): string
    {
        $cnf = ['config' => Certificados::opensslCnf(), 'digest_alg' => 'sha256'];
        $clave = openssl_pkey_get_private($clavePem);
        $csr = openssl_csr_new(['commonName' => 'manager-pos', 'serialNumber' => "CUIT {$cuit}"], $clave, $cnf);
        openssl_x509_export(openssl_csr_sign($csr, null, $clave, $dias, $cnf), $pem);

        return $pem;
    }

    private function conCertificado(): ConfiguracionFiscal
    {
        $config = $this->configurar();
        $generado = app(Certificados::class)->generarClaveYCsr(self::CUIT, 'Zapatería SA', 'manager-pos');
        $config->update([
            'clave_privada' => $generado['clave'],
            'certificado' => $this->certificadoPara($generado['clave']),
            'certificado_vence' => now()->addYear(),
        ]);

        return $config->fresh();
    }

    private static function respuestaWsaa(string $token = 'TOKEN-1', ?string $expira = null): string
    {
        $expira ??= now()->addHours(12)->toIso8601String();
        $ticket = htmlspecialchars('<?xml version="1.0" encoding="UTF-8"?><loginTicketResponse version="1.0"><header><expirationTime>'.$expira.'</expirationTime></header><credentials><token>'.$token.'</token><sign>SIGN-1</sign></credentials></loginTicketResponse>');

        return '<?xml version="1.0"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><loginCmsResponse xmlns="http://wsaa.view.sua.dvadac.desein.afip.gov"><loginCmsReturn>'.$ticket.'</loginCmsReturn></loginCmsResponse></soapenv:Body></soapenv:Envelope>';
    }

    private static function respuestaWsfe(string $metodo, string $interior): string
    {
        return '<?xml version="1.0"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><'.$metodo.'Response xmlns="http://ar.gov.afip.dif.FEV1/"><'.$metodo.'Result>'.$interior.'</'.$metodo.'Result></'.$metodo.'Response></soap:Body></soap:Envelope>';
    }

    // ---- CUIT --------------------------------------------------------------------

    public function test_validacion_de_cuit(): void
    {
        $this->assertTrue(Cuit::valido('20-12345678-6'));
        $this->assertTrue(Cuit::valido('20123456786'));
        $this->assertFalse(Cuit::valido('20-12345678-7'));
        $this->assertFalse(Cuit::valido('123'));
        $this->assertSame('20-12345678-6', Cuit::formatear('20123456786'));
    }

    // ---- Datos -------------------------------------------------------------------

    public function test_guarda_datos_y_valida(): void
    {
        Livewire::test(Configuracion::class)
            ->set('cuit', '20-12345678-7')->call('guardarDatos')
            ->assertHasErrors(['cuit', 'razonSocial', 'condicionIva', 'inicioActividades', 'domicilioComercial']);

        Livewire::test(Configuracion::class)
            ->set('razonSocial', 'Zapatería SA')->set('cuit', '20-12345678-6')->set('condicionIva', 'monotributo')
            ->set('inicioActividades', '2020-01-01')->set('domicilioComercial', 'Calle 123')
            ->call('guardarDatos')->assertHasNoErrors();

        $config = ConfiguracionFiscal::actual();
        $this->assertSame(self::CUIT, $config->cuit);
        $this->assertSame('C', $config->letraComprobantes());
    }

    // ---- Certificado -------------------------------------------------------------

    public function test_genera_clave_y_csr_con_el_cuit_y_guarda_la_clave_cifrada(): void
    {
        $this->configurar();

        Livewire::test(Configuracion::class)->set('alias', 'caja-central')->call('generarPedido')->assertSet('error', null);

        $config = ConfiguracionFiscal::actual();
        $sujeto = openssl_csr_get_subject($config->csr);
        $this->assertSame('CUIT '.self::CUIT, $sujeto['serialNumber']);
        $this->assertSame('caja-central', $sujeto['CN']);

        $crudo = DB::table('configuracion_fiscal')->value('clave_privada');
        $this->assertStringNotContainsString('PRIVATE KEY', $crudo, 'la clave no puede quedar en texto plano');
        $this->assertStringContainsString('PRIVATE KEY', $config->clave_privada);

        Livewire::test(Configuracion::class)->call('descargarCsr')->assertFileDownloaded('pedido-afip-'.self::CUIT.'.csr');
    }

    public function test_sube_el_certificado_que_corresponde_a_la_clave(): void
    {
        $this->configurar();
        Livewire::test(Configuracion::class)->call('generarPedido');
        $clave = ConfiguracionFiscal::actual()->clave_privada;

        Livewire::test(Configuracion::class)
            ->set('archivoCertificado', UploadedFile::fake()->createWithContent('cert.crt', $this->certificadoPara($clave)))
            ->call('subirCertificado')
            ->assertSet('mensaje', fn ($m) => str_contains((string) $m, 'Certificado cargado'));

        $config = ConfiguracionFiscal::actual();
        $this->assertTrue($config->certificadoVigente());
        $this->assertSame('manager-pos', $config->certificado_alias);
    }

    public function test_rechaza_certificado_de_otra_clave_o_de_otro_cuit(): void
    {
        $this->configurar();
        Livewire::test(Configuracion::class)->call('generarPedido');
        $clave = ConfiguracionFiscal::actual()->clave_privada;

        $otraClave = app(Certificados::class)->generarClaveYCsr(self::CUIT, 'X', 'x')['clave'];

        Livewire::test(Configuracion::class)
            ->set('archivoCertificado', UploadedFile::fake()->createWithContent('otro.crt', $this->certificadoPara($otraClave)))
            ->call('subirCertificado')
            ->assertSet('error', fn ($e) => str_contains((string) $e, 'no corresponde a la clave'));

        Livewire::test(Configuracion::class)
            ->set('archivoCertificado', UploadedFile::fake()->createWithContent('otro-cuit.crt', $this->certificadoPara($clave, '20111111112')))
            ->call('subirCertificado')
            ->assertSet('error', fn ($e) => str_contains((string) $e, 'es del CUIT 20-11111111-2'));

        Livewire::test(Configuracion::class)
            ->set('archivoCertificado', UploadedFile::fake()->createWithContent('basura.crt', 'no soy un certificado'))
            ->call('subirCertificado')
            ->assertSet('error', fn ($e) => str_contains((string) $e, 'no es un certificado válido'));

        $this->assertFalse(ConfiguracionFiscal::actual()->tieneCertificado());
    }

    // ---- WSAA --------------------------------------------------------------------

    public function test_el_ticket_de_acceso_se_pide_firmado_y_se_reusa(): void
    {
        $config = $this->conCertificado();
        Http::fake(['wsaahomo.afip.gov.ar/*' => Http::response(self::respuestaWsaa('TOKEN-ABC'))]);

        $ticket = app(Wsaa::class)->ticket($config);
        $this->assertSame('TOKEN-ABC', $ticket['token']);

        Http::assertSent(function (Request $r) {
            preg_match('/<wsaa:in0>([^<]+)<\/wsaa:in0>/', $r->body(), $m);

            return base64_decode($m[1] ?? '', true) !== false && strlen($m[1] ?? '') > 500;
        });

        // Segunda vez: sale del guardado, no le vuelve a pedir a AFIP
        app(Wsaa::class)->ticket($config->fresh());
        Http::assertSentCount(1);

        $this->assertStringNotContainsString('TOKEN-ABC', DB::table('configuracion_fiscal')->value('ta_token'));
    }

    public function test_errores_de_wsaa_se_traducen(): void
    {
        $config = $this->conCertificado();
        Http::fake(['*' => Http::response('<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><soapenv:Fault><faultcode>ns1:coe.alreadyAuthenticated</faultcode><faultstring>El CEE ya posee un TA valido para el acceso al WSN solicitado</faultstring></soapenv:Fault></soapenv:Body></soapenv:Envelope>', 500)]);

        try {
            app(Wsaa::class)->ticket($config);
            $this->fail('Tenía que fallar');
        } catch (AfipException $e) {
            $this->assertStringContainsString('esperar a que venza', $e->getMessage());
        }
    }

    // ---- Puntos de venta, prueba y activación ------------------------------------

    public function test_puntos_de_venta_sin_repetir_y_se_pueden_intercambiar(): void
    {
        $a = Sucursal::create(['nombre' => 'Centro', 'afip_punto_venta' => 1]);
        $b = Sucursal::create(['nombre' => 'Villa Bosh', 'afip_punto_venta' => 2]);

        Livewire::test(Configuracion::class)
            ->set("puntosVenta.{$a->id}", '3')->set("puntosVenta.{$b->id}", '3')->call('guardarPuntosVenta')
            ->assertSet('error', fn ($e) => str_contains((string) $e, 'mismo punto de venta'));

        Livewire::test(Configuracion::class)
            ->set("puntosVenta.{$a->id}", '2')->set("puntosVenta.{$b->id}", '1')->call('guardarPuntosVenta')
            ->assertSet('error', null);

        $this->assertSame(2, $a->fresh()->afip_punto_venta);
        $this->assertSame(1, $b->fresh()->afip_punto_venta);
    }

    public function test_probar_conexion_y_activar(): void
    {
        $this->conCertificado();
        Sucursal::create(['nombre' => 'Villa Bosh', 'afip_punto_venta' => 5]);

        Http::fake(function (Request $r) {
            if (str_contains($r->url(), 'wsaahomo')) {
                return Http::response(self::respuestaWsaa());
            }

            $accion = $r->header('SOAPAction')[0] ?? '';

            return match (true) {
                str_contains($accion, 'FEDummy') => Http::response(self::respuestaWsfe('FEDummy', '<AppServer>OK</AppServer><DbServer>OK</DbServer><AuthServer>OK</AuthServer>')),
                str_contains($accion, 'FEParamGetPtosVenta') => Http::response(self::respuestaWsfe('FEParamGetPtosVenta', '<Errors><Err><Code>602</Code><Msg>Sin Resultados</Msg></Err></Errors>')),
                str_contains($accion, 'FECompUltimoAutorizado') => Http::response(self::respuestaWsfe('FECompUltimoAutorizado', '<PtoVta>5</PtoVta><CbteTipo>6</CbteTipo><CbteNro>41</CbteNro>')),
                default => Http::response('', 500),
            };
        });

        // Sin prueba OK no se activa
        Livewire::test(Configuracion::class)->call('alternarFacturacion')->assertSet('error', fn ($e) => str_contains((string) $e, 'Probá la conexión'));

        Livewire::test(Configuracion::class)
            ->call('probarConexion')
            ->assertSet('mensaje', 'La conexión con AFIP funciona.')
            ->assertSee('Villa Bosh (PV 5): Nº 41')
            ->call('alternarFacturacion')
            ->assertSet('error', null);

        $this->assertTrue(ConfiguracionFiscal::actual()->facturacion_activa);

        // Factura B (6) para responsable inscripto
        Http::assertSent(fn (Request $r) => str_contains($r->body(), '<ar:CbteTipo>6</ar:CbteTipo>') && str_contains($r->body(), '<ar:Cuit>'.self::CUIT.'</ar:Cuit>'));
    }

    public function test_prueba_que_falla_muestra_el_paso_y_no_sigue(): void
    {
        $this->conCertificado();
        Http::fake(['*' => Http::response(self::respuestaWsfe('FEDummy', '<AppServer>OK</AppServer><DbServer>ERROR</DbServer><AuthServer>OK</AuthServer>'))]);

        Livewire::test(Configuracion::class)->call('probarConexion')->assertSet('error', fn ($e) => str_contains((string) $e, 'falló'));

        $detalle = ConfiguracionFiscal::actual()->ultima_prueba_detalle;
        $this->assertFalse($detalle[0]['ok']);
        $this->assertNull($detalle[1]['ok'], 'no se prueba la autenticación si los servidores fallan');
        Http::assertSentCount(1);
    }

    public function test_cambiar_cuit_invalida_ticket_y_desactiva(): void
    {
        $config = $this->conCertificado();
        $config->update(['ta_token' => 'X', 'ta_sign' => 'Y', 'ta_expira' => now()->addHours(5), 'facturacion_activa' => true]);

        Livewire::test(Configuracion::class)->set('cuit', '20-11111111-2')->call('guardarDatos')->assertHasNoErrors();

        $config->refresh();
        $this->assertNull($config->ta_token);
        $this->assertFalse($config->facturacion_activa);
    }

    public function test_sin_permiso_no_se_configura(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('facturacion.configuracion'))->assertForbidden();
    }
}
