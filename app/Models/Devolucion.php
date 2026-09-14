<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Devolución o anulación registrada en una caja. Llega por sync/devoluciones; no toca
 * stock (eso llega aparte como movimiento de stock tipo devolucion).
 */
class Devolucion extends Model
{
    protected $table = 'devoluciones';

    protected $fillable = [
        'uuid', 'punto_de_venta_id', 'sucursal_id', 'venta_uuid', 'turno_uuid', 'numero', 'tipo',
        'motivo', 'reintegro', 'total', 'autorizado_por', 'fecha',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'fecha' => 'datetime',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_uuid', 'uuid');
    }

    public function puntoDeVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoDeVenta::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DevolucionItem::class);
    }
}
