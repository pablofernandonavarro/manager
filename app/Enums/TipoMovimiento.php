<?php

namespace App\Enums;

enum TipoMovimiento: string
{
    case Venta = 'venta';
    case Entrada = 'entrada';
    case Ajuste = 'ajuste';
    case Devolucion = 'devolucion';
    case Transferencia = 'transferencia';

    public function label(): string
    {
        return match ($this) {
            self::Venta => 'Venta',
            self::Entrada => 'Entrada',
            self::Ajuste => 'Ajuste',
            self::Devolucion => 'Devolución',
            self::Transferencia => 'Transferencia',
        };
    }
}
