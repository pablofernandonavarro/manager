<?php

namespace App\Support;

/**
 * Código de barras EAN-13: 12 dígitos + dígito verificador (módulo 10, pesos 1 y 3).
 */
final class Ean13
{
    /** Completa 12 dígitos con su verificador. */
    public static function conVerificador(string $doceDigitos): string
    {
        if (! preg_match('/^\d{12}$/', $doceDigitos)) {
            throw new \InvalidArgumentException("Un EAN-13 se arma con 12 dígitos: {$doceDigitos}");
        }

        $suma = 0;

        foreach (str_split($doceDigitos) as $i => $digito) {
            $suma += (int) $digito * ($i % 2 === 0 ? 1 : 3);
        }

        return $doceDigitos.((10 - $suma % 10) % 10);
    }

    public static function valido(?string $codigo): bool
    {
        return is_string($codigo)
            && preg_match('/^\d{13}$/', $codigo) === 1
            && self::conVerificador(substr($codigo, 0, 12)) === $codigo;
    }
}
