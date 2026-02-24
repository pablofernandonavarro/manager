<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockSucursal extends Model
{
    use HasFactory;

    protected $table = 'stock_sucursal';

    protected $fillable = [
        'sucursal_id',
        'product_id',
        'cantidad',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
        ];
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
