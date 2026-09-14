<?php

namespace Database\Seeders;

use App\Models\ListaPrecio;
use App\Models\Sucursal;
use Illuminate\Database\Seeder;

/**
 * Sucursales de ejemplo: el depósito central y un local de venta. Sin ellas no hay dónde
 * cargar stock ni a qué sucursal asignar una caja. Reproducible por nombre.
 */
class SucursalesSeeder extends Seeder
{
    public const CENTRAL = 'Central';

    public const LOCAL = 'Villa Bosh';

    public function run(): void
    {
        $central = Sucursal::firstOrCreate(['nombre' => self::CENTRAL], ['is_central' => true, 'activo' => true]);
        $local = Sucursal::firstOrCreate(['nombre' => self::LOCAL], ['is_central' => false, 'activo' => true]);

        // Lista PUBLICO por defecto en las dos: sin lista la caja no tiene precios.
        if ($publico = ListaPrecio::where('nombre', 'PUBLICO')->first()) {
            foreach ([$central, $local] as $sucursal) {
                $sucursal->listasPrecios()->syncWithoutDetaching([$publico->id => ['es_default' => true]]);
            }
        }
    }
}
