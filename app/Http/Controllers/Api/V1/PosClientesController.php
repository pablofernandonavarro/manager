<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Services\CuentaCorrienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Clientes para las cajas (con saldo) y los cobros de cuenta corriente hechos en ellas.
 */
class PosClientesController extends Controller
{
    /** Clientes activos con su saldo. La caja reemplaza su copia con esta lista. */
    public function index(): JsonResponse
    {
        $clientes = Cliente::where('activo', true)
            ->withSum('movimientos as saldo', 'importe')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'generado_at' => now()->toIso8601String(),
            'data' => $clientes->map(fn (Cliente $c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'doc_tipo' => $c->doc_tipo,
                'documento' => $c->documento,
                'condicion_iva' => $c->condicion_iva,
                'telefono' => $c->telefono,
                'email' => $c->email,
                'cuenta_corriente' => $c->cuenta_corriente,
                'limite_credito' => $c->limite_credito !== null ? (float) $c->limite_credito : null,
                'saldo' => round((float) $c->saldo, 2),
            ])->values(),
        ]);
    }

    public function cobros(Request $request, CuentaCorrienteService $cuentas): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        $datos = $request->validate([
            'cobros' => 'required|array|min:1|max:200',
            'cobros.*.uuid' => 'required|uuid',
            // Sin exists: un cliente inexistente rechaza ese cobro, no toda la tanda.
            'cobros.*.cliente_id' => 'required|integer',
            'cobros.*.importe' => 'required|numeric|min:0.01',
            'cobros.*.medio' => 'required|in:'.implode(',', CuentaCorrienteService::MEDIOS_DE_COBRO),
            'cobros.*.fecha' => 'required|date',
            'cobros.*.cajero' => 'nullable|string|max:100',
            'cobros.*.numero' => 'nullable|string|max:30',
        ]);

        $resultados = [];

        DB::transaction(function () use ($datos, $pdv, $cuentas, &$resultados): void {
            foreach ($datos['cobros'] as $cobro) {
                if (! Cliente::whereKey($cobro['cliente_id'])->exists()) {
                    // Plata cobrada a un cliente que ya no existe: queda en el log para
                    // imputarla a mano; la caja lo sigue reintentando y el panel lo muestra.
                    Log::error('Cobro de cuenta corriente de un cliente inexistente', ['pdv' => $pdv->id, 'cobro' => $cobro]);
                    $resultados[] = ['uuid' => $cobro['uuid'], 'status' => 'cliente_inexistente'];

                    continue;
                }

                $resultados[] = ['uuid' => $cobro['uuid'], 'status' => $cuentas->registrarCobroDeCaja($pdv, $cobro)];
            }
        });

        return response()->json(['resultados' => $resultados]);
    }
}
