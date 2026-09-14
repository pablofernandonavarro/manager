<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento de la cuenta corriente de un cliente. `importe` positivo suma deuda (venta),
 * negativo la baja (pago, devolución). Los ajustes pueden ir en cualquier sentido.
 */
class MovimientoCuentaCorriente extends Model
{
    protected $table = 'movimientos_cuenta_corriente';

    public const TIPOS = [
        'venta' => 'Venta a cuenta',
        'pago' => 'Pago',
        'devolucion' => 'Devolución',
        'ajuste' => 'Ajuste',
    ];

    protected $fillable = [
        'uuid', 'cliente_id', 'tipo', 'importe', 'venta_id', 'devolucion_id', 'punto_de_venta_id',
        'user_id', 'medio', 'descripcion', 'fecha',
    ];

    protected function casts(): array
    {
        return [
            'importe' => 'decimal:2',
            'fecha' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function puntoDeVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoDeVenta::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
