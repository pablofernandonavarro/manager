<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevolucionItem extends Model
{
    protected $table = 'devolucion_items';

    protected $fillable = ['devolucion_id', 'product_id', 'cantidad', 'importe'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'importe' => 'decimal:2',
        ];
    }

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
