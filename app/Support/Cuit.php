<?php

namespace App\Support;

/**
 * CUIT/CUIL argentino: 11 dígitos con dígito verificador (módulo 11).
 */
final class Cuit
{
    public static function normalizar(?string $cuit): string
    {
        return preg_replace('/\D/', '', (string) $cuit);
    }

    public static function valido(?string $cuit): bool
    {
        $digitos = self::normalizar($cuit);

        if (strlen($digitos) !== 11) {
            return false;
        }

        $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;

        foreach ($pesos as $i => $peso) {
            $suma += (int) $digitos[$i] * $peso;
        }

        $verificador = 11 - ($suma % 11);
        $verificador = match ($verificador) {
            11 => 0,
            10 => 9,
            default => $verificador,
        };

        return $verificador === (int) $digitos[10];
    }

    public static function formatear(?string $cuit): string
    {
        $d = self::normalizar($cuit);

        return strlen($d) === 11 ? substr($d, 0, 2).'-'.substr($d, 2, 8).'-'.substr($d, 10) : (string) $cuit;
    }
}
