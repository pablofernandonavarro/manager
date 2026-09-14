<?php

namespace App\Services\Afip;

use App\Exceptions\AfipException;
use App\Exceptions\AfipSinRespuestaException;
use App\Models\Comprobante;
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

    /**
     * Pide el CAE de un comprobante con el número ya asignado.
     *
     * @return array{resultado: string, cae: ?string, cae_vencimiento: ?string, observaciones: array<int, string>, errores: array<int, string>}
     */
    public function solicitarCae(ConfiguracionFiscal $config, Comprobante $comprobante): array
    {
        $resultado = $this->llamar($config, 'FECAESolicitar', $this->auth($config).$this->detalle($config, $comprobante));

        $detalle = $resultado->FeDetResp->FECAEDetResponse ?? null;
        $errores = [];
        $observaciones = [];

        foreach ($resultado->Errors->Err ?? [] as $err) {
            $errores[] = "{$err->Code}: {$err->Msg}";
        }

        foreach ($detalle?->Observaciones->Obs ?? [] as $obs) {
            $observaciones[] = "{$obs->Code}: {$obs->Msg}";
        }

        $cae = trim((string) ($detalle?->CAE ?? ''));
        $vencimiento = trim((string) ($detalle?->CAEFchVto ?? ''));

        return [
            // Sin detalle (error de cabecera) cuenta como rechazo.
            'resultado' => $detalle ? (string) $detalle->Resultado : 'R',
            'cae' => $cae !== '' ? $cae : null,
            'cae_vencimiento' => $vencimiento !== '' ? substr($vencimiento, 0, 4).'-'.substr($vencimiento, 4, 2).'-'.substr($vencimiento, 6, 2) : null,
            'observaciones' => $observaciones,
            'errores' => $errores,
        ];
    }

    /**
     * Un comprobante ya emitido, o null si AFIP no lo tiene.
     *
     * @return array{importe_total: float, doc_nro: string, cae: string, cae_vencimiento: ?string, fecha: ?string}|null
     */
    public function consultar(ConfiguracionFiscal $config, int $puntoVenta, int $tipo, int $numero): ?array
    {
        $resultado = $this->llamar($config, 'FECompConsultar', $this->auth($config)
            ."<ar:FeCompConsReq><ar:CbteTipo>{$tipo}</ar:CbteTipo><ar:CbteNro>{$numero}</ar:CbteNro><ar:PtoVta>{$puntoVenta}</ar:PtoVta></ar:FeCompConsReq>");

        // 602 = no existe un comprobante con esos datos.
        if ((string) ($resultado->Errors->Err->Code ?? '') === '602') {
            return null;
        }

        self::lanzarErrores($resultado);

        $comp = $resultado->ResultGet;
        $fecha = (string) $comp->CbteFch;
        $vencimiento = (string) $comp->FchVto;

        return [
            'importe_total' => (float) $comp->ImpTotal,
            'doc_nro' => (string) $comp->DocNro,
            'cae' => (string) $comp->CodAutorizacion,
            'cae_vencimiento' => $vencimiento !== '' ? substr($vencimiento, 0, 4).'-'.substr($vencimiento, 4, 2).'-'.substr($vencimiento, 6, 2) : null,
            'fecha' => $fecha !== '' ? substr($fecha, 0, 4).'-'.substr($fecha, 4, 2).'-'.substr($fecha, 6, 2) : null,
        ];
    }

    private function detalle(ConfiguracionFiscal $config, Comprobante $c): string
    {
        $importe = fn ($valor) => number_format((float) $valor, 2, '.', '');
        $esC = $c->letra() === 'C';

        $asociados = '';

        if ($c->comprobante_asociado_id && ($original = $c->asociado)) {
            $asociados = '<ar:CbtesAsoc><ar:CbteAsoc>'
                ."<ar:Tipo>{$original->tipo}</ar:Tipo><ar:PtoVta>{$original->afip_punto_venta}</ar:PtoVta><ar:Nro>{$original->numero}</ar:Nro>"
                .'<ar:Cuit>'.Cuit::normalizar($config->cuit).'</ar:Cuit><ar:CbteFch>'.$original->fecha->format('Ymd').'</ar:CbteFch>'
                .'</ar:CbteAsoc></ar:CbtesAsoc>';
        }

        $iva = '';

        if (! $esC && $c->alicuotas !== []) {
            $iva = '<ar:Iva>';
            foreach ($c->alicuotas as $alicuota) {
                $iva .= "<ar:AlicIva><ar:Id>{$alicuota['id']}</ar:Id><ar:BaseImp>{$importe($alicuota['base'])}</ar:BaseImp><ar:Importe>{$importe($alicuota['importe'])}</ar:Importe></ar:AlicIva>";
            }
            $iva .= '</ar:Iva>';
        }

        return '<ar:FeCAEReq>'
            ."<ar:FeCabReq><ar:CantReg>1</ar:CantReg><ar:PtoVta>{$c->afip_punto_venta}</ar:PtoVta><ar:CbteTipo>{$c->tipo}</ar:CbteTipo></ar:FeCabReq>"
            .'<ar:FeDetReq><ar:FECAEDetRequest>'
            .'<ar:Concepto>1</ar:Concepto>'
            ."<ar:DocTipo>{$c->receptor_doc_tipo}</ar:DocTipo><ar:DocNro>{$c->receptor_doc_nro}</ar:DocNro>"
            ."<ar:CbteDesde>{$c->numero}</ar:CbteDesde><ar:CbteHasta>{$c->numero}</ar:CbteHasta>"
            .'<ar:CbteFch>'.$c->fecha->format('Ymd').'</ar:CbteFch>'
            ."<ar:ImpTotal>{$importe($c->importe_total)}</ar:ImpTotal>"
            .'<ar:ImpTotConc>0.00</ar:ImpTotConc>'
            ."<ar:ImpNeto>{$importe($c->importe_neto)}</ar:ImpNeto>"
            .'<ar:ImpOpEx>0.00</ar:ImpOpEx><ar:ImpTrib>0.00</ar:ImpTrib>'
            ."<ar:ImpIVA>{$importe($esC ? 0 : $c->importe_iva)}</ar:ImpIVA>"
            .'<ar:MonId>PES</ar:MonId><ar:MonCotiz>1</ar:MonCotiz>'
            ."<ar:CondicionIVAReceptorId>{$c->receptor_condicion_iva}</ar:CondicionIVAReceptorId>"
            .$asociados
            .$iva
            .'</ar:FECAEDetRequest></ar:FeDetReq>'
            .'</ar:FeCAEReq>';
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
            // Incluye el corte esperando la respuesta: el pedido pudo haber llegado.
            throw new AfipSinRespuestaException('No hubo respuesta de AFIP (factura electrónica). Revisá la conexión del servidor.');
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
