<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemitoDetalle extends Model
{
    protected $fillable = [
        'remito_id',
        'product_id',
        'cantidad',
        'cantidad_recibida',
        'cantidad_rechazada',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'cantidad_recibida' => 'integer',
            'cantidad_rechazada' => 'integer',
        ];
    }

    public function remito(): BelongsTo
    {
        return $this->belongsTo(Remito::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
