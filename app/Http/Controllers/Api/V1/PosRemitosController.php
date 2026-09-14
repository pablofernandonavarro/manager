<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoRemito;
use App\Exceptions\RemitoException;
use App\Http\Controllers\Controller;
use App\Models\Remito;
use App\Models\StockSucursal;
use App\Services\RemitoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Remitos vistos desde la caja: lo que viene en camino a su sucursal y la recepción.
 * Todo acotado a la sucursal del punto de venta autenticado.
 */
class PosRemitosController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        $remitos = Remito::with(['sucursalOrigen', 'detalles.product'])
            ->where('sucursal_destino_id', $pdv->sucursal_id)
            ->where('estado', EstadoRemito::Remitido)
            ->orderBy('remitido_at')
            ->get();

        return response()->json([
            'data' => $remitos->map(fn (Remito $r) => [
                'id' => $r->id,
                'numero' => str_pad((string) $r->id, 6, '0', STR_PAD_LEFT),
                'origen' => $r->sucursalOrigen->nombre,
                'remitido_at' => $r->remitido_at->toIso8601String(),
                'observaciones' => $r->observaciones,
                'items' => $r->detalles->map(fn ($d) => [
                    'product_id' => $d->product_id,
                    'codigo' => $d->product?->codigo_interno ?: $d->product?->codigo_barras,
                    'nombre' => $d->product?->nombre,
                    'cantidad' => $d->cantidad,
                ])->values(),
            ])->values(),
        ]);
    }

    /**
     * Idempotente: si la caja reintenta (se cortó la red después de confirmar) y el remito
     * ya está recibido, responde OK con el stock actual en vez de un error. Así la caja
     * puede reintentar sin miedo y nunca se acredita dos veces.
     */
    public function recibir(Request $request, int $remitoId, RemitoService $remitos): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        // Una caja solo puede recibir lo que va a su propia sucursal.
        $remito = Remito::where('id', $remitoId)
            ->where('sucursal_destino_id', $pdv->sucursal_id)
            ->first();

        if (! $remito) {
            return response()->json(['message' => 'El remito no existe o no es para esta sucursal.'], 404);
        }

        $status = 'ya_recibido';

        if ($remito->estado === EstadoRemito::Remitido) {
            try {
                $remito = $remitos->confirmar($remito, caja: $pdv);
                $status = 'recibido';
            } catch (RemitoException) {
                // Lo cerró otro (el Manager o un reintento) entre la lectura y el bloqueo.
                $remito->refresh();
            }
        }

        if ($remito->estado === EstadoRemito::Cancelado) {
            return response()->json(['message' => "El remito #{$remito->id} fue cancelado en el Manager. No se sumó stock."], 409);
        }

        $productIds = $remito->detalles()->pluck('product_id');

        return response()->json([
            'status' => $status,
            'numero' => str_pad((string) $remito->id, 6, '0', STR_PAD_LEFT),
            'stock' => StockSucursal::where('sucursal_id', $pdv->sucursal_id)
                ->whereIn('product_id', $productIds)
                ->get(['product_id', 'cantidad']),
        ]);
    }
}
