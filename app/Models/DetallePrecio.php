<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetallePrecio extends Model
{
    use HasFactory;

    protected $table = 'detalle_lista_precios';

    protected $fillable = [
        'lista_precio_id',
        'product_id',
        'precio_override',
        'vigencia_desde',
        'vigencia_hasta',
    ];

    protected function casts(): array
    {
        return [
            'precio_override' => 'decimal:2',
            'vigencia_desde' => 'date',
            'vigencia_hasta' => 'date',
        ];
    }

    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(ListaPrecio::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function estaVigente(): bool
    {
        $hoy = now()->startOfDay();

        if ($this->vigencia_desde && $this->vigencia_desde->gt($hoy)) {
            return false;
        }

        if ($this->vigencia_hasta && $this->vigencia_hasta->lt($hoy)) {
            return false;
        }

        return true;
    }
}
