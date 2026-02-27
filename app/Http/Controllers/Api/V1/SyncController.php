<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoRemito;
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
use App\Models\Remito;
use App\Models\StockSucursal;
use App\Models\Venta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    /**
     * Catálogo de productos con soporte a delta sync.
     * Incluye el stock de la sucursal del POS via subquery (sin N+1).
     */
    public function productos(Request $request): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        $query = Product::query()
            ->where('es_vendible', true)
            ->addSelect([
                'products.*',
                'stock_sucursal' => StockSucursal::select('cantidad')
                    ->whereColumn('product_id', 'products.id')
                    ->where('sucursal_id', $pdv->sucursal_id)
                    ->limit(1),
            ]);

        if ($request->filled('updated_since')) {
            $query->where('updated_at', '>', $request->updated_since);
        }

        $productos = $query->get();

        return response()->json([
            'data' => ProductSyncResource::collection($productos),
            'total' => $productos->count(),
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Listas de precios asignadas a la sucursal del POS autenticado.
     * Soporta delta sync con ?updated_since=ISO8601
     */
    public function precios(Request $request): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        $listasIds = $pdv->sucursal->listasPrecios()->pluck('listas_precios.id');

        $query = DetallePrecio::with('product:id,codigo_interno')
            ->whereIn('lista_precio_id', $listasIds);

        if ($request->filled('updated_since')) {
            $query->where('updated_at', '>', $request->updated_since);
        }

        $listas = $pdv->sucursal->listasPrecios()->get()->map(fn ($lista) => [
            'id' => $lista->id,
            'nombre' => $lista->nombre,
            'factor' => $lista->factor,
            'es_default' => (bool) $lista->pivot->es_default,
        ]);

        return response()->json([
            'listas' => $listas,
            'precios' => PrecioSyncResource::collection($query->get()),
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Stock de la sucursal del POS autenticado.
     * Soporta delta sync con ?updated_since=ISO8601
     */
    public function stock(Request $request): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        $query = StockSucursal::with('product:id,codigo_interno')
            ->where('sucursal_id', $pdv->sucursal_id);

        if ($request->filled('updated_since')) {
            $query->where('updated_at', '>', $request->updated_since);
        }

        return response()->json([
            'data' => StockSyncResource::collection($query->get()),
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Remitos en tránsito hacia la sucursal del POS autenticado.
     */
    public function remitos(Request $request): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        $remitos = Remito::with(['sucursalOrigen', 'detalles.product'])
            ->where('sucursal_destino_id', $pdv->sucursal_id)
            ->where('estado', EstadoRemito::Remitido)
            ->orderByDesc('remitido_at')
            ->get();

        $data = $remitos->map(fn ($remito) => [
            'id' => $remito->id,
            'sucursal_origen' => $remito->sucursalOrigen->nombre,
            'observaciones' => $remito->observaciones,
            'remitido_at' => $remito->remitido_at->toIso8601String(),
            'detalles' => $remito->detalles->map(fn ($d) => [
                'product_id' => $d->product_id,
                'nombre' => $d->product->nombre,
                'codigo_interno' => $d->product->codigo_interno,
                'cantidad' => $d->cantidad,
            ]),
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * Confirma la recepción de un remito desde el POS.
     */
    public function confirmarRemito(Request $request, int $id): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        $remito = Remito::with('detalles.product:id,codigo_interno')->find($id);

        if (! $remito) {
            return response()->json(['message' => 'Remito no encontrado.'], 404);
        }

        if ($remito->sucursal_destino_id !== $pdv->sucursal_id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if ($remito->estado !== EstadoRemito::Remitido) {
            return response()->json(['message' => 'El remito no está en estado remitido.'], 422);
        }

        $stockActualizado = [];

        DB::transaction(function () use ($remito, &$stockActualizado): void {
            foreach ($remito->detalles as $detalle) {
                $stock = StockSucursal::firstOrCreate(
                    ['sucursal_id' => $remito->sucursal_destino_id, 'product_id' => $detalle->product_id],
                    ['cantidad' => 0]
                );
                $stock->increment('cantidad', $detalle->cantidad);
                $stock->refresh();

                $stockActualizado[] = [
                    'product_id' => $detalle->product_id,
                    'codigo_interno' => $detalle->product->codigo_interno,
                    'cantidad' => $stock->cantidad,
                ];

                $totalGlobal = StockSucursal::where('product_id', $detalle->product_id)->sum('cantidad');
                Product::where('id', $detalle->product_id)->update(['stock' => $totalGlobal]);
            }

            $remito->update([
                'estado' => EstadoRemito::Confirmado,
                'confirmado_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Recepción confirmada.',
            'stock_actualizado' => $stockActualizado,
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

                $stockSucursal = StockSucursal::firstOrNew([
                    'sucursal_id' => $pdv->sucursal_id,
                    'product_id' => $mov['product_id'],
                ]);

                $cantidadActual = $stockSucursal->cantidad ?? 0;
                $nuevaCantidad = max(0, $cantidadActual + (int) $mov['cantidad']);

                $stockSucursal->cantidad = $nuevaCantidad;
                $stockSucursal->save();

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
