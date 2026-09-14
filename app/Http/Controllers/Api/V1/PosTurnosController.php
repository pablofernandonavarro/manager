<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SyncTurnosRequest;
use App\Models\MovimientoCaja;
use App\Models\TurnoCaja;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Turnos de caja que manda el POS: el abierto se reenvía mientras dura (así el Manager ve
 * la caja en vivo) y el cerrado llega una vez con su cierre Z.
 *
 * Idempotente por uuid, como ventas y movimientos. Un turno cerrado es inmutable: si la
 * caja lo reenvía (se perdió la respuesta) se contesta "duplicado" sin tocarlo.
 */
class PosTurnosController extends Controller
{
    public function sync(SyncTurnosRequest $request): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();
        $resultados = [];

        DB::transaction(function () use ($request, $pdv, &$resultados): void {
            foreach ($request->validated('turnos') as $datos) {
                $turno = TurnoCaja::where('uuid', $datos['uuid'])->lockForUpdate()->first();

                // Un uuid de otra caja no se pisa nunca.
                if ($turno && $turno->punto_de_venta_id !== $pdv->id) {
                    $resultados[] = ['uuid' => $datos['uuid'], 'status' => 'rechazado'];

                    continue;
                }

                if ($turno?->estaCerrado()) {
                    $resultados[] = ['uuid' => $datos['uuid'], 'status' => 'duplicado'];

                    continue;
                }

                $turno ??= new TurnoCaja(['uuid' => $datos['uuid']]);

                $turno->fill([
                    'punto_de_venta_id' => $pdv->id,
                    'sucursal_id' => $pdv->sucursal_id,
                    'numero' => $datos['numero'],
                    'cajero' => $datos['cajero'],
                    'estado' => $datos['estado'],
                    'fondo_inicial' => $datos['fondo_inicial'],
                    'abierto_at' => $datos['abierto_at'],
                    'cerrado_at' => $datos['cerrado_at'] ?? null,
                    'cantidad_ventas' => $datos['cantidad_ventas'],
                    'total_ventas' => $datos['total_ventas'],
                    'efectivo_esperado' => $datos['efectivo_esperado'] ?? null,
                    'efectivo_contado' => $datos['efectivo_contado'] ?? null,
                    'diferencia' => $datos['diferencia'] ?? null,
                    'resumen' => $datos['resumen'] ?? null,
                    'observaciones' => $datos['observaciones'] ?? null,
                    'sincronizado_at' => now(),
                ])->save();

                $existentes = MovimientoCaja::whereIn('uuid', array_column($datos['movimientos'] ?? [], 'uuid'))->pluck('uuid')->all();

                foreach ($datos['movimientos'] ?? [] as $movimiento) {
                    if (in_array($movimiento['uuid'], $existentes, true)) {
                        continue;
                    }

                    $turno->movimientos()->create($movimiento);
                }

                $resultados[] = ['uuid' => $datos['uuid'], 'status' => $turno->estaCerrado() ? 'cerrado' : 'abierto'];
            }
        });

        return response()->json(['resultados' => $resultados]);
    }
}
