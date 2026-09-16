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

    /**
     * Suma o resta stock de una sucursal bloqueando la fila (tiene que correr dentro de una
     * transacción). Antes era leer → restar → guardar sin bloqueo: dos cajas vendiendo el
     * mismo artículo a la vez leían la misma cantidad y uno de los descuentos se perdía.
     * Nunca queda en negativo, como antes.
     */
    public static function aplicarDelta(int $sucursalId, int $productId, int $delta): self
    {
        // createOrFirst: si otra transacción la crea al mismo tiempo, no revienta el unique.
        static::query()->createOrFirst(['sucursal_id' => $sucursalId, 'product_id' => $productId], ['cantidad' => 0]);

        $fila = static::query()
            ->where('sucursal_id', $sucursalId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->firstOrFail();

        $fila->cantidad = max(0, $fila->cantidad + $delta);
        $fila->save();

        return $fila;
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
