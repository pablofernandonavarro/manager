<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cómo se cobró una parte de una venta. `monto` es lo que cubre de la venta; `importe`,
 * lo que efectivamente se cobró por ese medio después de la promoción bancaria.
 */
class PagoVenta extends Model
{
    protected $table = 'pagos_venta';

    public const MEDIOS = [
        'efectivo' => 'Efectivo',
        'debito' => 'Débito',
        'credito' => 'Crédito',
        'transferencia' => 'Transferencia',
        'qr' => 'QR / billetera',
    ];

    protected $fillable = [
        'venta_id',
        'medio',
        'monto',
        'descuento',
        'importe',
        'tarjeta',
        'banco',
        'cuotas',
        'promocion_bancaria_id',
        'promocion_nombre',
        'referencia',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'descuento' => 'decimal:2',
            'importe' => 'decimal:2',
            'cuotas' => 'integer',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function promocion(): BelongsTo
    {
        return $this->belongsTo(PromocionBancaria::class, 'promocion_bancaria_id');
    }
}
