<?php

namespace App\Services;

use App\Enums\TipoMovimiento;
use App\Models\MovimientoStock;
use App\Models\Product;
use App\Models\StockSucursal;

/**
 * Stock que entra al dar de alta un producto o una variante.
 *
 * El stock vive por sucursal (`stock_sucursal`); `products.stock` es solo la suma. Antes el
 * formulario de alta guardaba la cantidad en `products.stock` sin sucursal: no llegaba a
 * ninguna caja y las pantallas de stock (que suman `stock_sucursal`) mostraban 0.
 */
class StockInicialService
{
    public function cargar(Product $producto, int $sucursalId, int $cantidad, string $referencia = 'Stock inicial'): void
    {
        if ($cantidad <= 0) {
            return;
        }

        $stock = StockSucursal::firstOrCreate(
            ['sucursal_id' => $sucursalId, 'product_id' => $producto->id],
            ['cantidad' => 0]
        );
        $stock->increment('cantidad', $cantidad);

        MovimientoStock::create([
            'sucursal_id' => $sucursalId,
            'product_id' => $producto->id,
            'tipo' => TipoMovimiento::Entrada,
            'cantidad' => $cantidad,
            'referencia' => $referencia,
            'fecha' => now(),
        ]);

        $producto->update(['stock' => StockSucursal::where('product_id', $producto->id)->sum('cantidad')]);
    }
}
