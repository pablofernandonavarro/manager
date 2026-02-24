<?php

namespace App\Models;

use App\Enums\TipoMovimiento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoStock extends Model
{
    use HasFactory;

    protected $table = 'movimientos_stock';

    protected $fillable = [
        'punto_de_venta_id',
        'sucursal_id',
        'product_id',
        'tipo',
        'cantidad',
        'referencia',
        'fecha',
        'sincronizado_at',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimiento::class,
            'cantidad' => 'integer',
            'fecha' => 'datetime',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
