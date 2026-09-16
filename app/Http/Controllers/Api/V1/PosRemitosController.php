<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoRemito;
use App\Exceptions\RemitoException;
use App\Http\Controllers\Controller;
use App\Models\ConfiguracionRemitos;
use App\Models\Remito;
use App\Models\StockSucursal;
use App\Models\Sucursal;
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

        $data = $request->validate([
            'cantidades_recibidas' => 'nullable|array',
            'cantidades_recibidas.*' => 'integer|min:0',
            'destino_rechazados_id' => 'nullable|integer|exists:sucursales,id',
        ]);

        $status = 'ya_recibido';

        if ($remito->estado === EstadoRemito::Remitido) {
            try {
                $remito = $remitos->confirmar($remito, caja: $pdv, cantidadesRecibidas: $data['cantidades_recibidas'] ?? null, destinoRechazadosId: $data['destino_rechazados_id'] ?? null);
                $status = 'recibido';
            } catch (RemitoException $e) {
                $remito->refresh();
                if ($remito->estado === EstadoRemito::Remitido) {
                    return response()->json(['message' => $e->getMessage()], 422);
                }
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
            'remito_hijo' => ($hijo = $remito->hijos()->latest('id')->first())
                ? ['id' => $hijo->id, 'numero' => str_pad((string) $hijo->id, 6, '0', STR_PAD_LEFT), 'destino' => $hijo->sucursalDestino->nombre]
                : null,
        ]);
    }

    public function store(Request $request, RemitoService $remitos): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        $data = $request->validate([
            'destino_sucursal_id' => 'required|integer|exists:sucursales,id',
            'items' => 'required|array|min:1',
            'items.*' => 'integer|min:1',
            'observaciones' => 'nullable|string|max:1000',
        ]);

        $destinoId = (int) $data['destino_sucursal_id'];
        $config = ConfiguracionRemitos::actual();

        if (! $config->ruta_directa && ! Sucursal::where('id', $destinoId)->where('is_central', true)->exists()) {
            return response()->json(['message' => 'La política de remitos exige enviar la mercadería a la sucursal Central.'], 422);
        }

        try {
            $remito = $remitos->crear($pdv->sucursal_id, $destinoId, $data['items'], observaciones: $data['observaciones'] ?? null, caja: $pdv);
        } catch (RemitoException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => [
            'id' => $remito->id,
            'numero' => str_pad((string) $remito->id, 6, '0', STR_PAD_LEFT),
            'destino' => $remito->sucursalDestino->nombre,
            'items' => $remito->detalles->count(),
        ]], 201);
    }

    public function configuracion(): JsonResponse
    {
        $config = ConfiguracionRemitos::actual();

        return response()->json([
            'ruta_directa' => $config->ruta_directa,
            'destino_rechazados' => $config->destino_rechazados,
        ]);
    }
}
