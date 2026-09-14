<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use Illuminate\Database\Seeder;

/**
 * Stock de ejemplo sobre lo que se vende: cada variante (color + talle) y cada producto
 * simple, en cada sucursal. Nunca sobre el configurable padre, que no se vende.
 *
 * Reproducible: fija las cantidades (updateOrCreate por sucursal y producto) y recalcula
 * `products.stock` como la suma, igual que hace el resto del Manager.
 */
class StockSeeder extends Seeder
{
    /** Cantidades de los locales de venta para CONF-4301. El resto se deriva del SKU. */
    public const REMERA_BASICA = [
        'CONF-4301-NEG-S' => 10,
        'CONF-4301-NEG-M' => 15,
        'CONF-4301-NEG-L' => 8,
        'CONF-4301-BLA-S' => 7,
        'CONF-4301-BLA-M' => 12,
        'CONF-4301-BLA-L' => 5,
    ];

    public function run(): void
    {
        $sucursales = Sucursal::where('activo', true)->get();

        $vendibles = Product::simple()
            ->where('es_vendible', true)
            ->whereIn('codigo_interno', $this->codigosDelCatalogo())
            ->get();

        foreach ($vendibles as $producto) {
            foreach ($sucursales as $sucursal) {
                StockSucursal::updateOrCreate(
                    ['sucursal_id' => $sucursal->id, 'product_id' => $producto->id],
                    ['cantidad' => self::cantidad($producto->codigo_interno, $sucursal->is_central)]
                );
            }

            $producto->update(['stock' => (int) StockSucursal::where('product_id', $producto->id)->sum('cantidad')]);
        }

        // El padre no tiene stock propio: vive en las variantes.
        $padres = Product::configurable()->pluck('id');
        StockSucursal::whereIn('product_id', $padres)->delete();
        Product::whereKey($padres)->update(['stock' => 0]);

        $this->command?->info("Stock cargado para {$vendibles->count()} artículos en {$sucursales->count()} sucursal(es).");
    }

    /** Central guarda el doble de lo que tiene un local. */
    public static function cantidad(string $codigo, bool $central): int
    {
        $local = self::REMERA_BASICA[$codigo] ?? (crc32($codigo) % 14) + 2;

        return $central ? $local * 2 : $local;
    }

    /** @return list<string> */
    private function codigosDelCatalogo(): array
    {
        $codigos = array_column(ProductSeeder::SIMPLES, 'codigo');

        foreach (ProductSeeder::CONFIGURABLES as $articulo) {
            $padre = Product::configurable()->where('codigo_interno', $articulo['codigo'])->first();

            if ($padre) {
                array_push($codigos, ...$padre->variants()->pluck('codigo_interno'));
            }
        }

        return $codigos;
    }
}
