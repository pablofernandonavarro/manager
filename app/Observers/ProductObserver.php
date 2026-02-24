<?php

namespace App\Observers;

use App\Models\Product;

class ProductObserver
{
    /**
     * Con el sistema factor + override, products.precio es la fuente
     * de verdad para el precio base. No se necesita sincronización con
     * detalle_lista_precios porque el precio efectivo se calcula como:
     *   COALESCE(precio_override, precio_padre * factor)
     *
     * Este observer queda disponible para lógica futura
     * (ej: notificaciones de cambio de precio, auditoría, etc.)
     */
    public function saving(Product $product): void {}

    public function created(Product $product): void {}
}
