<?php

namespace App\Services\Afip;

use App\Exceptions\AfipException;
use App\Models\ConfiguracionFiscal;
use App\Support\Cuit;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * WSFEv1: factura electrónica de AFIP (comprobantes A, B y C con CAE).
 */
class Wsfe
{
    public const URL = [
        'homologacion' => 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx',
        'produccion' => 'https://servicios1.afip.gov.ar/wsfev1/service.asmx',
    ];

    private const NS = 'http://ar.gov.afip.dif.FEV1/';

    public function __construct(
        private readonly Wsaa $wsaa,
    ) {}

    /**
     * Estado de los servidores de AFIP. No requiere autenticación.
     *
     * @return array{app: string, db: string, auth: string}
     */
    public function dummy(ConfiguracionFiscal $config): array
    {
        $resultado = $this->llamar($config, 'FEDummy', '');

        return [
            'app' => (string) $resultado->AppServer,
            'db' => (string) $resultado->DbServer,
            'auth' => (string) $resultado->AuthServer,
        ];
    }

    /** Número del último comprobante autorizado para un punto de venta y tipo. */
    public function ultimoAutorizado(ConfiguracionFiscal $config, int $puntoVenta, int $tipoComprobante): int
    {
        $resultado = $this->llamar($config, 'FECompUltimoAutorizado', $this->auth($config)
            ."<ar:PtoVta>{$puntoVenta}</ar:PtoVta><ar:CbteTipo>{$tipoComprobante}</ar:CbteTipo>");

        self::lanzarErrores($resultado);

        return (int) $resultado->CbteNro;
    }

    /**
     * Puntos de venta que AFIP tiene habilitados para web services con este CUIT.
     *
     * @return array<int, array{numero: int, tipo: string, bloqueado: bool, baja: ?string}>
     */
    public function puntosDeVenta(ConfiguracionFiscal $config): array
    {
        $resultado = $this->llamar($config, 'FEParamGetPtosVenta', $this->auth($config));

        // 602 = "Sin Resultados": el CUIT no tiene puntos de venta para web services.
        if ((string) ($resultado->Errors->Err->Code ?? '') === '602') {
            return [];
        }

        self::lanzarErrores($resultado);

        $puntos = [];

        foreach ($resultado->ResultGet->PtoVenta ?? [] as $pv) {
            $puntos[] = [
                'numero' => (int) $pv->Nro,
                'tipo' => (string) $pv->EmisionTipo,
                'bloqueado' => (string) $pv->Bloqueado === 'S',
                'baja' => ((string) $pv->FchBaja) !== 'NULL' && (string) $pv->FchBaja !== '' ? (string) $pv->FchBaja : null,
            ];
        }

        return $puntos;
    }

    private function auth(ConfiguracionFiscal $config): string
    {
        $ticket = $this->wsaa->ticket($config);

        return '<ar:Auth><ar:Token>'.$ticket['token'].'</ar:Token><ar:Sign>'.$ticket['sign'].'</ar:Sign>'
            .'<ar:Cuit>'.Cuit::normalizar($config->cuit).'</ar:Cuit></ar:Auth>';
    }

    private function llamar(ConfiguracionFiscal $config, string $metodo, string $cuerpo): \SimpleXMLElement
    {
        $envelope = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ar="'.self::NS.'">'
            ."<soapenv:Header/><soapenv:Body><ar:{$metodo}>{$cuerpo}</ar:{$metodo}></soapenv:Body></soapenv:Envelope>";

        try {
            $respuesta = Http::timeout(30)
                ->withHeaders(['SOAPAction' => '"'.self::NS.$metodo.'"'])
                ->withBody($envelope, 'text/xml; charset=utf-8')
                ->post(self::URL[$config->entorno]);
        } catch (ConnectionException) {
            throw new AfipException('No se pudo conectar con AFIP (factura electrónica). Revisá la conexión del servidor.');
        }

        if ($error = Soap::fault($respuesta->body())) {
            throw new AfipException("AFIP devolvió un error en {$metodo}: {$error}");
        }

        $resultado = Soap::resultado($respuesta->body(), "{$metodo}Result");

        if ($resultado === null) {
            throw new AfipException("Respuesta inesperada de AFIP en {$metodo} (HTTP {$respuesta->status()}).");
        }

        return $resultado;
    }

    private static function lanzarErrores(\SimpleXMLElement $resultado): void
    {
        if (! isset($resultado->Errors->Err)) {
            return;
        }

        $mensajes = [];

        foreach ($resultado->Errors->Err as $err) {
            $mensajes[] = "{$err->Code}: {$err->Msg}";
        }

        throw new AfipException('AFIP: '.implode(' | ', $mensajes));
    }
}
