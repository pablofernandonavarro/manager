<?php

namespace App\Enums;

enum ProductType: string
{
    case SIMPLE = 'simple';
    case CONFIGURABLE = 'configurable';

    public function label(): string
    {
        return match($this) {
            self::SIMPLE => 'Simple',
            self::CONFIGURABLE => 'Configurable',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::SIMPLE => 'Producto vendible individual (variante con color/talle específico)',
            self::CONFIGURABLE => 'Producto padre que agrupa variantes (no se vende directamente)',
        };
    }
}
