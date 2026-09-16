<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Comportamiento global de los remitos internos entre sucursales.
 * Una única fila (id=1) con dos settings: ruta directa (boolean) y destino de rechazados (enum string).
 */
class ConfiguracionRemitos extends Model
{
    protected $table = 'configuracion_remitos';

    public const DESTINOS_RECHAZADOS = [
        'origen' => 'Vuelve automático a quien lo mandó',
        'manager' => 'Va automático a la sucursal Central',
        'elegir' => 'Quien recibe elige el destino en el momento',
    ];

    protected $fillable = ['ruta_directa', 'destino_rechazados'];

    protected function casts(): array
    {
        return ['ruta_directa' => 'boolean'];
    }

    public static function actual(): self
    {
        if ($config = self::find(1)) {
            return $config;
        }

        $config = (new self)->forceFill(['id' => 1]);
        $config->save();

        return $config->fresh();
    }
}
