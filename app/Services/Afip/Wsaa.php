<?php

namespace App\Services\Afip;

use App\Exceptions\AfipException;
use App\Models\ConfiguracionFiscal;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * WSAA: autenticación contra AFIP. Firma un pedido (TRA) con el certificado y recibe un
 * ticket de acceso (token + sign) que dura ~12 horas.
 *
 * El ticket se guarda y se reusa: AFIP rechaza pedir otro mientras hay uno vigente
 * ("El CEE ya posee un TA valido"), así que perderlo deja la facturación parada hasta
 * que venza.
 */
class Wsaa
{
    public const URL = [
        'homologacion' => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms',
        'produccion' => 'https://wsaa.afip.gov.ar/ws/services/LoginCms',
    ];

    /**
     * @return array{token: string, sign: string, expira: Carbon}
     */
    public function ticket(ConfiguracionFiscal $config, bool $forzar = false): array
    {
        if (! $forzar && $config->ta_token && $config->ta_expira?->gt(now()->addMinutes(10))) {
            return ['token' => $config->ta_token, 'sign' => $config->ta_sign, 'expira' => $config->ta_expira];
        }

        if (! $config->tieneCertificado()) {
            throw new AfipException('Falta cargar el certificado digital de AFIP.');
        }

        $cms = $this->firmar($this->tra(), $config->certificado, $config->clave_privada);

        try {
            $respuesta = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'text/xml; charset=utf-8', 'SOAPAction' => '""'])
                ->withBody($this->envelope($cms), 'text/xml; charset=utf-8')
                ->post(self::URL[$config->entorno]);
        } catch (ConnectionException $e) {
            throw new AfipException('No se pudo conectar con AFIP (WSAA). Revisá la conexión a internet del servidor.');
        }

        $xml = $respuesta->body();

        if ($error = Soap::fault($xml)) {
            throw new AfipException(self::traducirError($error));
        }

        $ticketXml = Soap::valor($xml, 'loginCmsReturn');

        if ($ticketXml === null) {
            throw new AfipException('Respuesta inesperada de AFIP (WSAA), HTTP '.$respuesta->status().'.');
        }

        $ticket = simplexml_load_string($ticketXml);

        if ($ticket === false || ! isset($ticket->credentials->token)) {
            throw new AfipException('AFIP devolvió un ticket de acceso ilegible.');
        }

        $datos = [
            'token' => (string) $ticket->credentials->token,
            'sign' => (string) $ticket->credentials->sign,
            'expira' => Carbon::parse((string) $ticket->header->expirationTime)->utc(),
        ];

        $config->update(['ta_token' => $datos['token'], 'ta_sign' => $datos['sign'], 'ta_expira' => $datos['expira']]);

        return $datos;
    }

    private function tra(): string
    {
        $ahora = now();

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<loginTicketRequest version="1.0"><header>'
            .'<uniqueId>'.$ahora->timestamp.'</uniqueId>'
            .'<generationTime>'.$ahora->copy()->subMinutes(10)->toIso8601String().'</generationTime>'
            .'<expirationTime>'.$ahora->copy()->addMinutes(10)->toIso8601String().'</expirationTime>'
            .'</header><service>wsfe</service></loginTicketRequest>';
    }

    /** Firma CMS (PKCS#7) en DER, codificada en base64, que es lo que espera loginCms. */
    private function firmar(string $tra, string $certificado, string $clave): string
    {
        $entrada = tempnam(sys_get_temp_dir(), 'tra');
        $salida = tempnam(sys_get_temp_dir(), 'cms');

        try {
            file_put_contents($entrada, $tra);

            if (! openssl_cms_sign($entrada, $salida, $certificado, $clave, [], PKCS7_BINARY, OPENSSL_ENCODING_DER)) {
                throw new AfipException('No se pudo firmar el pedido de acceso con el certificado: '.openssl_error_string());
            }

            return base64_encode(file_get_contents($salida));
        } finally {
            @unlink($entrada);
            @unlink($salida);
        }
    }

    private function envelope(string $cms): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:wsaa="http://wsaa.view.sua.dvadac.desein.afip.gov">'
            .'<soapenv:Header/><soapenv:Body><wsaa:loginCms><wsaa:in0>'.$cms.'</wsaa:in0></wsaa:loginCms></soapenv:Body></soapenv:Envelope>';
    }

    private static function traducirError(string $error): string
    {
        return match (true) {
            str_contains($error, 'alreadyAuthenticated') => 'AFIP ya entregó un ticket de acceso que sigue vigente y este Manager no lo tiene guardado. Hay que esperar a que venza (hasta 12 horas) o usar otro certificado.',
            str_contains($error, 'cms.cert.untrusted') || str_contains($error, 'cms.bad') => 'AFIP no reconoce el certificado. Revisá que sea del entorno elegido (homologación o producción).',
            str_contains($error, 'cms.cert.expired') => 'El certificado está vencido.',
            str_contains($error, 'notAuthorized') || str_contains($error, 'no autorizado') => 'El certificado no está autorizado para Facturación Electrónica (wsfe). Asocialo al servicio en el Administrador de Relaciones de AFIP.',
            str_contains($error, 'xml.generationTime') || str_contains($error, 'xml.expirationTime') => 'AFIP rechazó la hora del pedido: revisá que el reloj del servidor esté bien.',
            default => 'AFIP rechazó la autenticación: '.$error,
        };
    }
}
