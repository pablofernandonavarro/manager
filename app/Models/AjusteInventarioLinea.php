<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AjusteInventarioLinea extends Model
{
    use HasFactory;

    protected $table = 'ajuste_inventario_lineas';

    protected $fillable = [
        'ajuste_inventario_id',
        'product_id',
        'cantidad_anterior',
        'cantidad_nueva',
        'delta',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_anterior' => 'integer',
            'cantidad_nueva' => 'integer',
            'delta' => 'integer',
        ];
    }

    public function ajuste(): BelongsTo
    {
        return $this->belongsTo(AjusteInventario::class, 'ajuste_inventario_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
