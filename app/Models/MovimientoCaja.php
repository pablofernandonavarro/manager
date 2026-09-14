<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entrada o salida de efectivo de la caja que no es una venta: un ingreso de cambio,
 * un retiro para depositar o un gasto pagado con la caja.
 */
class MovimientoCaja extends Model
{
    protected $table = 'movimientos_caja';

    public const TIPOS = [
        'ingreso' => 'Ingreso',
        'retiro' => 'Retiro',
        'gasto' => 'Gasto',
    ];

    protected $fillable = ['uuid', 'turno_caja_id', 'tipo', 'monto', 'motivo', 'fecha'];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha' => 'datetime',
        ];
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(TurnoCaja::class, 'turno_caja_id');
    }
}
