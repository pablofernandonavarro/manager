<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoRemito;
use App\Exceptions\RemitoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SyncMovimientosRequest;
use App\Http\Requests\Api\V1\SyncVentasRequest;
use App\Http\Resources\Api\V1\PrecioSyncResource;
use App\Http\Resources\Api\V1\ProductSyncResource;
use App\Http\Resources\Api\V1\StockSyncResource;
use App\Jobs\AutorizarComprobante;
use App\Models\Cajero;
use App\Models\DetallePrecio;
use App\Models\MovimientoStock;
use App\Models\Product;
use App\Models\PromocionBancaria;
use App\Models\Remito;
use App\Models\StockSucursal;
use App\Services\RegistroVentasPos;
use App\Services\RemitoService;
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
     *
     * Pasa por RemitoService, igual que `pos/remitos/{id}/recibir`: bloquea el remito y
     * relee el estado dentro de la transacción, así dos confirmaciones simultáneas (doble
     * clic, reintento tras un corte) no acreditan la mercadería dos veces, y deja el
     * movimiento de stock registrado.
     */
    public function confirmarRemito(Request $request, int $id, RemitoService $remitos): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        $remito = Remito::find($id);

        if (! $remito) {
            return response()->json(['message' => 'Remito no encontrado.'], 404);
        }

        if ($remito->sucursal_destino_id !== $pdv->sucursal_id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        try {
            $remito = $remitos->confirmar($remito, caja: $pdv);
        } catch (RemitoException) {
            return response()->json(['message' => 'El remito no está en estado remitido.'], 422);
        }

        $remito->load('detalles.product:id,codigo_interno');

        $stock = StockSucursal::where('sucursal_id', $pdv->sucursal_id)
            ->whereIn('product_id', $remito->detalles->pluck('product_id'))
            ->pluck('cantidad', 'product_id');

        return response()->json([
            'success' => true,
            'message' => 'Recepción confirmada.',
            'stock_actualizado' => $remito->detalles->map(fn ($detalle) => [
                'product_id' => $detalle->product_id,
                'codigo_interno' => $detalle->product?->codigo_interno,
                'cantidad' => (int) ($stock[$detalle->product_id] ?? 0),
            ])->values(),
        ]);
    }

    /**
     * Promociones bancarias que la sucursal del POS puede aplicar. La caja reemplaza su
     * copia local con esta lista.
     */
    public function promociones(Request $request): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        $promociones = PromocionBancaria::paraSucursal($pdv->sucursal_id)->orderBy('nombre')->get();

        return response()->json([
            'data' => $promociones->map(fn (PromocionBancaria $p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'banco' => $p->banco,
                'medios' => $p->medios,
                'tarjetas' => $p->tarjetas,
                'dias_semana' => $p->dias_semana,
                'vigencia_desde' => $p->vigencia_desde?->toDateString(),
                'vigencia_hasta' => $p->vigencia_hasta?->toDateString(),
                'modalidad' => $p->modalidad,
                'porcentaje' => (float) $p->porcentaje,
                'tope' => $p->tope !== null ? (float) $p->tope : null,
                'monto_minimo' => $p->monto_minimo !== null ? (float) $p->monto_minimo : null,
                'cuotas_sin_interes' => $p->cuotas_sin_interes,
            ])->values(),
        ]);
    }

    /**
     * Cajeros habilitados en la sucursal del POS, con el PIN hasheado (bcrypt): la caja
     * verifica el PIN localmente para poder abrir y autorizar sin conexión.
     */
    public function cajeros(Request $request): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        return response()->json([
            'data' => Cajero::paraSucursal($pdv->sucursal_id)->orderBy('nombre')->get()
                ->map(fn (Cajero $c) => [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'rol' => $c->rol,
                    'pin_hash' => $c->pin_hash,
                ])->values(),
        ]);
    }

    /**
     * Sincroniza un batch de ventas desde el POS.
     */
    public function ventas(SyncVentasRequest $request, RegistroVentasPos $registro): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();
        $sincronizadoAt = now();
        $ventasCreadas = 0;
        $resultados = [];
        $aAutorizar = [];

        DB::transaction(function () use ($request, $pdv, $sincronizadoAt, $registro, &$ventasCreadas, &$resultados, &$aAutorizar): void {
            foreach ($request->ventas as $ventaData) {
                $registrada = $registro->registrar($pdv, $ventaData, $sincronizadoAt);

                if ($registrada['status'] === 'creada') {
                    $ventasCreadas++;
                }

                if ($registrada['comprobante']?->estado === 'pendiente') {
                    $aAutorizar[] = $registrada['comprobante'];
                }

                $resultados[] = [
                    'uuid' => $registrada['venta']->uuid,
                    'status' => $registrada['status'],
                    'venta_id' => $registrada['venta']->id,
                ];
            }
        });

        // Después del commit: el worker no puede leer un comprobante que todavía no existe.
        foreach ($aAutorizar as $comprobante) {
            AutorizarComprobante::dispatch($comprobante);
        }

        return response()->json([
            'message' => "Se sincronizaron {$ventasCreadas} venta(s) correctamente.",
            'sincronizado_at' => $sincronizadoAt->toIso8601String(),
            'resultados' => $resultados,
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
        $resultados = [];

        DB::transaction(function () use ($request, $pdv, $sincronizadoAt, &$creados, &$resultados): void {
            foreach ($request->movimientos as $mov) {
                $existente = MovimientoStock::where('uuid', $mov['uuid'])->first();

                if ($existente) {
                    $resultados[] = [
                        'uuid' => $mov['uuid'],
                        'status' => 'duplicado',
                        'movimiento_id' => $existente->id,
                    ];

                    continue;
                }

                $movimiento = MovimientoStock::create([
                    'uuid' => $mov['uuid'],
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

                $resultados[] = [
                    'uuid' => $movimiento->uuid,
                    'status' => 'creado',
                    'movimiento_id' => $movimiento->id,
                ];
            }
        });

        return response()->json([
            'message' => "Se sincronizaron {$creados} movimiento(s) correctamente.",
            'sincronizado_at' => $sincronizadoAt->toIso8601String(),
            'resultados' => $resultados,
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
