<?php

namespace App\Enums;

enum EstadoRemito: string
{
    case Remitido = 'remitido';
    case Confirmado = 'confirmado';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Remitido => 'Remitido',
            self::Confirmado => 'Confirmado',
            self::Cancelado => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Remitido => 'yellow',
            self::Confirmado => 'green',
            self::Cancelado => 'red',
        };
    }
}
