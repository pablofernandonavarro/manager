<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $centralId = DB::table('sucursales')->where('is_central', true)->value('id');

        if (! $centralId) {
            return;
        }

        // Mover stock de products.stock → stock_sucursal de Central
        // Solo productos con stock > 0 que aún no tengan registro en Central
        $productos = DB::table('products')
            ->where('stock', '>', 0)
            ->whereNotExists(function ($query) use ($centralId) {
                $query->select(DB::raw(1))
                    ->from('stock_sucursal')
                    ->whereColumn('stock_sucursal.product_id', 'products.id')
                    ->where('stock_sucursal.sucursal_id', $centralId);
            })
            ->get(['id', 'stock']);

        $now = now();

        foreach ($productos as $producto) {
            DB::table('stock_sucursal')->insert([
                'sucursal_id' => $centralId,
                'product_id' => $producto->id,
                'cantidad' => $producto->stock,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $centralId = DB::table('sucursales')->where('is_central', true)->value('id');

        if ($centralId) {
            DB::table('stock_sucursal')->where('sucursal_id', $centralId)->delete();
        }
    }
};
