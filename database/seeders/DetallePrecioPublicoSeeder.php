<?php

namespace Database\Seeders;

use App\Models\ListaPrecio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DetallePrecioPublicoSeeder extends Seeder
{
    /**
     * Carga precios en la lista PUBLICO solo para productos padre
     * (configurables y simples top-level). Las variantes heredan
     * el precio del padre via COALESCE en el momento de consulta.
     */
    public function run(): void
    {
        $lista = ListaPrecio::where('nombre', 'PUBLICO')->firstOrFail();

        // Solo productos sin parent_id (configurables y simples top-level)
        $productos = DB::table('products')
            ->where('precio', '>', 0)
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->get(['id', 'precio']);

        $ahora = now();
        $insertados = 0;
        $omitidos = 0;

        foreach ($productos as $producto) {
            $existe = DB::table('detalle_lista_precios')
                ->where('lista_precio_id', $lista->id)
                ->where('product_id', $producto->id)
                ->exists();

            if (! $existe) {
                DB::table('detalle_lista_precios')->insert([
                    'lista_precio_id' => $lista->id,
                    'product_id' => $producto->id,
                    'precio' => $producto->precio,
                    'vigencia_desde' => null,
                    'vigencia_hasta' => null,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
                $insertados++;
            } else {
                $omitidos++;
            }
        }

        $this->command->info("Lista PUBLICO: {$insertados} precios insertados, {$omitidos} ya existían.");
    }
}
