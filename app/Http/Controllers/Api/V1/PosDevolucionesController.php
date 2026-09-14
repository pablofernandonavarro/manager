<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Devolucion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Comprobantes de devolución que manda la caja. Idempotente por uuid y sin efecto sobre
 * el stock: la mercadería devuelta ya llegó como movimiento de stock tipo devolucion.
 */
class PosDevolucionesController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        $datos = $request->validate([
            'devoluciones' => 'required|array|min:1|max:100',
            'devoluciones.*.uuid' => 'required|uuid',
            'devoluciones.*.venta_uuid' => 'required|uuid',
            'devoluciones.*.turno_uuid' => 'nullable|uuid',
            'devoluciones.*.numero' => 'required|string|max:30',
            'devoluciones.*.tipo' => 'required|in:anulacion,parcial',
            'devoluciones.*.motivo' => 'required|string|max:200',
            'devoluciones.*.reintegro' => 'required|in:efectivo,medio_original',
            'devoluciones.*.total' => 'required|numeric|min:0',
            'devoluciones.*.autorizado_por' => 'required|string|max:100',
            'devoluciones.*.fecha' => 'required|date',
            'devoluciones.*.items' => 'required|array|min:1',
            'devoluciones.*.items.*.product_id' => 'required|integer|exists:products,id',
            'devoluciones.*.items.*.cantidad' => 'required|integer|min:1',
            'devoluciones.*.items.*.importe' => 'required|numeric|min:0',
        ]);

        $resultados = [];

        DB::transaction(function () use ($datos, $pdv, &$resultados): void {
            foreach ($datos['devoluciones'] as $d) {
                if (Devolucion::where('uuid', $d['uuid'])->exists()) {
                    $resultados[] = ['uuid' => $d['uuid'], 'status' => 'duplicada'];

                    continue;
                }

                $devolucion = Devolucion::create([
                    ...collect($d)->except('items')->all(),
                    'punto_de_venta_id' => $pdv->id,
                    'sucursal_id' => $pdv->sucursal_id,
                ]);

                $devolucion->items()->createMany($d['items']);

                $resultados[] = ['uuid' => $d['uuid'], 'status' => 'creada'];
            }
        });

        return response()->json(['resultados' => $resultados]);
    }
}
