<?php

namespace App\Enums;

enum EstadoAjuste: string
{
    case Borrador = 'borrador';
    case Aplicado = 'aplicado';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Aplicado => 'Aplicado',
        };
    }
}
