<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SyncMovimientosRequest;
use App\Http\Requests\Api\V1\SyncVentasRequest;
use App\Http\Resources\Api\V1\PrecioSyncResource;
use App\Http\Resources\Api\V1\ProductSyncResource;
use App\Http\Resources\Api\V1\StockSyncResource;
use App\Models\DetallePrecio;
use App\Models\DetalleVenta;
use App\Models\MovimientoStock;
use App\Models\Product;
use App\Models\StockSucursal;
use App\Models\Venta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    /**
     * Catálogo de productos con soporte a delta sync.
     */
    public function productos(Request $request): JsonResponse
    {
        $query = Product::query()->where('es_vendible', true);

        if ($request->filled('updated_since')) {
            $query->where('updated_at', '>', $request->updated_since);
        }

        $productos = $query->get();

        return response()->json([
            'data' => ProductSyncResource::collection($productos),
            'total' => $productos->count(),
        ]);
    }

    /**
     * Listas de precios asignadas a la sucursal del POS autenticado.
     */
    public function precios(Request $request): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        $listasIds = $pdv->sucursal->listasPrecios()->pluck('listas_precios.id');

        $detalles = DetallePrecio::whereIn('lista_precio_id', $listasIds)->get();

        $listas = $pdv->sucursal->listasPrecios()->get()->map(fn ($lista) => [
            'id' => $lista->id,
            'nombre' => $lista->nombre,
            'factor' => $lista->factor,
            'es_default' => (bool) $lista->pivot->es_default,
        ]);

        return response()->json([
            'listas' => $listas,
            'precios' => PrecioSyncResource::collection($detalles),
        ]);
    }

    /**
     * Stock de la sucursal del POS autenticado.
     */
    public function stock(Request $request): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        $stock = StockSucursal::where('sucursal_id', $pdv->sucursal_id)->get();

        return response()->json([
            'data' => StockSyncResource::collection($stock),
        ]);
    }

    /**
     * Sincroniza un batch de ventas desde el POS.
     */
    public function ventas(SyncVentasRequest $request): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();
        $sincronizadoAt = now();
        $ventasCreadas = 0;

        DB::transaction(function () use ($request, $pdv, $sincronizadoAt, &$ventasCreadas): void {
            foreach ($request->ventas as $ventaData) {
                $venta = Venta::create([
                    'punto_de_venta_id' => $pdv->id,
                    'sucursal_id' => $pdv->sucursal_id,
                    'lista_precio_id' => $ventaData['lista_precio_id'] ?? null,
                    'numero_venta' => $ventaData['numero_venta'] ?? null,
                    'fecha' => $ventaData['fecha'],
                    'subtotal' => $ventaData['subtotal'],
                    'descuento' => $ventaData['descuento'] ?? 0,
                    'total' => $ventaData['total'],
                    'sincronizado_at' => $sincronizadoAt,
                ]);

                $productIds = [];

                foreach ($ventaData['items'] as $item) {
                    DetalleVenta::create([
                        'venta_id' => $venta->id,
                        'product_id' => $item['product_id'],
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $item['precio_unitario'],
                        'subtotal' => $item['subtotal'],
                    ]);

                    MovimientoStock::create([
                        'punto_de_venta_id' => $pdv->id,
                        'sucursal_id' => $pdv->sucursal_id,
                        'product_id' => $item['product_id'],
                        'tipo' => 'venta',
                        'cantidad' => -abs($item['cantidad']),
                        'referencia' => $venta->numero_venta,
                        'fecha' => $ventaData['fecha'],
                        'sincronizado_at' => $sincronizadoAt,
                    ]);

                    StockSucursal::updateOrCreate(
                        ['sucursal_id' => $pdv->sucursal_id, 'product_id' => $item['product_id']],
                        ['cantidad' => DB::raw('GREATEST(0, cantidad - '.abs((int) $item['cantidad']).')')],
                    );

                    $productIds[] = $item['product_id'];
                }

                // Recalcular stock global desde suma de stock_sucursal
                foreach (array_unique($productIds) as $productId) {
                    $totalStock = StockSucursal::where('product_id', $productId)->sum('cantidad');
                    Product::where('id', $productId)->update(['stock' => $totalStock]);
                }

                $ventasCreadas++;
            }
        });

        return response()->json([
            'message' => "Se sincronizaron {$ventasCreadas} venta(s) correctamente.",
            'sincronizado_at' => $sincronizadoAt->toIso8601String(),
        ]);
    }

    /**
     * Sincroniza un batch de movimientos de stock desde el POS.
     */
    public function movimientos(SyncMovimientosRequest $request): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();
        $sincronizadoAt = now();
        $creados = 0;

        DB::transaction(function () use ($request, $pdv, $sincronizadoAt, &$creados): void {
            foreach ($request->movimientos as $mov) {
                MovimientoStock::create([
                    'punto_de_venta_id' => $pdv->id,
                    'sucursal_id' => $pdv->sucursal_id,
                    'product_id' => $mov['product_id'],
                    'tipo' => $mov['tipo'],
                    'cantidad' => $mov['cantidad'],
                    'referencia' => $mov['referencia'] ?? null,
                    'fecha' => $mov['fecha'],
                    'sincronizado_at' => $sincronizadoAt,
                ]);

                StockSucursal::updateOrCreate(
                    ['sucursal_id' => $pdv->sucursal_id, 'product_id' => $mov['product_id']],
                    ['cantidad' => DB::raw('GREATEST(0, cantidad + '.((int) $mov['cantidad']).')')],
                );

                $totalStock = StockSucursal::where('product_id', $mov['product_id'])->sum('cantidad');
                Product::where('id', $mov['product_id'])->update(['stock' => $totalStock]);

                $creados++;
            }
        });

        return response()->json([
            'message' => "Se sincronizaron {$creados} movimiento(s) correctamente.",
            'sincronizado_at' => $sincronizadoAt->toIso8601String(),
        ]);
    }

    /**
     * Devuelve el precio efectivo de un producto según la lista default de la sucursal del POS.
     */
    public function precio(Request $request, int $productId): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        $product = Product::find($productId);
        if (! $product) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        $lista = $pdv->sucursal->listasPrecios()
            ->wherePivot('es_default', true)
            ->first();

        if (! $lista) {
            // Sin lista default: devuelve precio base del producto
            return response()->json([
                'product_id' => $productId,
                'nombre' => $product->nombre,
                'lista' => null,
                'precio_efectivo' => (float) $product->precio,
                'es_override' => false,
            ]);
        }

        $precioEfectivo = $lista->precioEfectivoParaProducto($productId);

        return response()->json([
            'product_id' => $productId,
            'nombre' => $product->nombre,
            'lista' => $lista->nombre,
            'lista_id' => $lista->id,
            'precio_efectivo' => $precioEfectivo,
            'es_override' => DetallePrecio::where('lista_precio_id', $lista->id)
                ->where('product_id', $productId)
                ->whereNull('vigencia_desde')->orWhere('vigencia_desde', '<=', now()->toDateString())
                ->exists(),
        ]);
    }
}
