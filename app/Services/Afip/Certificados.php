<?php

namespace App\Services\Afip;

use App\Exceptions\AfipException;
use App\Support\Cuit;
use Illuminate\Support\Carbon;

/**
 * Clave privada, pedido de certificado (CSR) y lectura del certificado que entrega AFIP.
 *
 * La clave se genera acá y no sale del Manager: a AFIP solo se le sube el CSR.
 */
class Certificados
{
    /**
     * @return array{clave: string, csr: string}
     */
    public function generarClaveYCsr(string $cuit, string $razonSocial, string $alias): array
    {
        if (! Cuit::valido($cuit)) {
            throw new AfipException('Cargá un CUIT válido antes de generar el pedido de certificado.');
        }

        $config = ['config' => self::opensslCnf()];

        $clave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA] + $config);

        if ($clave === false) {
            throw new AfipException('No se pudo generar la clave privada: '.openssl_error_string());
        }

        // AFIP exige serialNumber "CUIT nnnnnnnnnnn" y usa el commonName como alias.
        $csr = openssl_csr_new([
            'countryName' => 'AR',
            'organizationName' => mb_substr(trim($razonSocial) ?: 'Emisor', 0, 60),
            'commonName' => mb_substr(preg_replace('/[^A-Za-z0-9 _.-]/', '', $alias) ?: 'manager-pos', 0, 60),
            'serialNumber' => 'CUIT '.Cuit::normalizar($cuit),
        ], $clave, ['digest_alg' => 'sha256'] + $config);

        if ($csr === false) {
            throw new AfipException('No se pudo generar el pedido de certificado: '.openssl_error_string());
        }

        openssl_csr_export($csr, $csrPem);
        openssl_pkey_export($clave, $clavePem, null, $config);

        return ['clave' => $clavePem, 'csr' => $csrPem];
    }

    /**
     * Lee un certificado (PEM o DER) y verifica que corresponda a la clave y al CUIT.
     *
     * @return array{pem: string, alias: ?string, cuit: ?string, emisor: ?string, vence: Carbon, homologacion: bool}
     */
    public function leer(string $contenido, string $clavePem, string $cuitEsperado): array
    {
        $pem = self::aPem($contenido);
        $datos = @openssl_x509_parse($pem);

        if ($datos === false) {
            throw new AfipException('El archivo no es un certificado válido. Subí el .crt que descargaste de AFIP.');
        }

        if (! openssl_x509_check_private_key($pem, $clavePem)) {
            throw new AfipException('El certificado no corresponde a la clave generada acá. ¿Subiste el CSR de este Manager a AFIP?');
        }

        preg_match('/(\d{11})/', (string) ($datos['subject']['serialNumber'] ?? ''), $m);
        $cuit = $m[1] ?? null;

        if ($cuit !== null && $cuit !== Cuit::normalizar($cuitEsperado)) {
            throw new AfipException('El certificado es del CUIT '.Cuit::formatear($cuit).', no del CUIT configurado ('.Cuit::formatear($cuitEsperado).').');
        }

        $vence = Carbon::createFromTimestamp($datos['validTo_time_t']);

        if ($vence->isPast()) {
            throw new AfipException('El certificado venció el '.$vence->format('d/m/Y').'. Generá uno nuevo en AFIP.');
        }

        $emisor = $datos['issuer']['CN'] ?? $datos['issuer']['O'] ?? null;

        return [
            'pem' => $pem,
            'alias' => $datos['subject']['CN'] ?? null,
            'cuit' => $cuit,
            'emisor' => $emisor,
            'vence' => $vence,
            // Los certificados de homologación los firma "Computadores Test".
            'homologacion' => $emisor !== null && str_contains(mb_strtolower($emisor), 'test'),
        ];
    }

    public function verificarClave(string $clavePem): bool
    {
        return openssl_pkey_get_private($clavePem) !== false;
    }

    private static function aPem(string $contenido): string
    {
        if (str_contains($contenido, '-----BEGIN CERTIFICATE-----')) {
            return trim($contenido)."\n";
        }

        // DER binario
        return "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($contenido), 64, "\n")."-----END CERTIFICATE-----\n";
    }

    public static function opensslCnf(): string
    {
        return resource_path('openssl/openssl.cnf');
    }
}
