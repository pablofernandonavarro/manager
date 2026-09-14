<?php

namespace App\Services\Afip;

use DOMDocument;

/**
 * Lectura mínima de respuestas SOAP de AFIP sin depender de ext-soap (que no está en
 * todos los servidores) ni de WSDL remotos.
 */
final class Soap
{
    /** Texto del primer elemento con ese nombre local, ya des-escapado. */
    public static function valor(string $xml, string $elemento): ?string
    {
        $dom = self::cargar($xml);
        $nodo = $dom?->getElementsByTagNameNS('*', $elemento)->item(0) ?? $dom?->getElementsByTagName($elemento)->item(0);

        return $nodo?->textContent;
    }

    /**
     * "faultcode: faultstring" si la respuesta es un SOAP Fault. AFIP pone el código útil
     * (ej. coe.alreadyAuthenticated) en faultcode y un texto libre en faultstring.
     */
    public static function fault(string $xml): ?string
    {
        if (! str_contains($xml, 'Fault')) {
            return null;
        }

        $codigo = self::valor($xml, 'faultcode');
        $texto = self::valor($xml, 'faultstring');

        return trim(($codigo ? $codigo.': ' : '').($texto ?? '')) ?: 'Error SOAP';
    }

    /** Nodo del resultado como SimpleXML sin namespaces, para navegarlo cómodo. */
    public static function resultado(string $xml, string $elemento): ?\SimpleXMLElement
    {
        $dom = self::cargar($xml);
        $nodo = $dom?->getElementsByTagNameNS('*', $elemento)->item(0) ?? $dom?->getElementsByTagName($elemento)->item(0);

        if (! $nodo) {
            return null;
        }

        $sinNamespaces = preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', $dom->saveXML($nodo));

        return simplexml_load_string($sinNamespaces) ?: null;
    }

    private static function cargar(string $xml): ?DOMDocument
    {
        if (trim($xml) === '') {
            return null;
        }

        $dom = new DOMDocument;
        $previo = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        return $ok ? $dom : null;
    }
}
