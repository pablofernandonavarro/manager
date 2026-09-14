<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turno de caja de un punto de venta: desde la apertura con fondo inicial hasta el
 * cierre Z con arqueo. Lo arma la caja y llega por sync/turnos; acá no se edita.
 */
class TurnoCaja extends Model
{
    protected $table = 'turnos_caja';

    protected $fillable = [
        'uuid',
        'punto_de_venta_id',
        'sucursal_id',
        'numero',
        'cajero',
        'estado',
        'fondo_inicial',
        'abierto_at',
        'cerrado_at',
        'cantidad_ventas',
        'total_ventas',
        'efectivo_esperado',
        'efectivo_contado',
        'diferencia',
        'resumen',
        'observaciones',
        'sincronizado_at',
    ];

    protected function casts(): array
    {
        return [
            'fondo_inicial' => 'decimal:2',
            'total_ventas' => 'decimal:2',
            'efectivo_esperado' => 'decimal:2',
            'efectivo_contado' => 'decimal:2',
            'diferencia' => 'decimal:2',
            'resumen' => 'array',
            'abierto_at' => 'datetime',
            'cerrado_at' => 'datetime',
            'sincronizado_at' => 'datetime',
        ];
    }

    public function puntoDeVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoDeVenta::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCaja::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'turno_uuid', 'uuid');
    }

    public function estaCerrado(): bool
    {
        return $this->estado === 'cerrado';
    }
}
