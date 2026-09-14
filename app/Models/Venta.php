<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'punto_de_venta_id',
        'sucursal_id',
        'lista_precio_id',
        'turno_uuid',
        'cajero',
        'numero_venta',
        'fecha',
        'subtotal',
        'descuento',
        'descuento_manual',
        'descuento_autorizado_por',
        'total',
        'metodo_pago',
        'cliente_nombre',
        'cliente_documento',
        'sincronizado_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'descuento_manual' => 'decimal:2',
            'total' => 'decimal:2',
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

    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(ListaPrecio::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DetalleVenta::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoVenta::class);
    }

    public function devoluciones(): HasMany
    {
        return $this->hasMany(Devolucion::class, 'venta_uuid', 'uuid');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(TurnoCaja::class, 'turno_uuid', 'uuid');
    }
}
