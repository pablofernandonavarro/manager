<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reporte de estado que manda cada caja cada minuto (junto con la consulta de órdenes).
 * Se guarda tal cual; el diagnóstico lo arma SaludCaja al mostrarlo.
 */
class PosEstadoController extends Controller
{
    public function reportar(Request $request): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        $estado = $request->validate([
            'generado_at' => 'required|date',
            'ultima_sincronizacion_stock' => 'nullable|date',
            'ultima_sincronizacion_productos' => 'nullable|date',
            'catalogo_pendiente' => 'boolean',
            'ventas_pendientes' => 'integer|min:0',
            'venta_pendiente_mas_vieja' => 'nullable|date',
            'movimientos_pendientes' => 'integer|min:0',
            'devoluciones_pendientes' => 'integer|min:0',
            'facturas_pendientes' => 'integer|min:0',
            'factura_pendiente_mas_vieja' => 'nullable|date',
            'facturas_rechazadas' => 'integer|min:0',
            'jobs_en_cola' => 'integer|min:0',
            'jobs_fallidos' => 'integer|min:0',
            'turno_abierto' => 'nullable|array',
            'turno_abierto.numero' => 'nullable|integer',
            'turno_abierto.cajero' => 'nullable|string|max:100',
            'turno_abierto.abierto_at' => 'nullable|date',
        ]);

        $pdv->forceFill(['estado_caja' => $estado, 'estado_reportado_at' => now()])->save();

        return response()->json(['ok' => true]);
    }
}
