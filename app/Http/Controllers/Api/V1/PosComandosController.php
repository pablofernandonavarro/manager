<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ComandoPos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PosComandosController extends Controller
{
    /**
     * Órdenes pendientes para la caja autenticada.
     *
     * Se marcan como tomadas al entregarlas: si la caja se cuelga a mitad, la orden no
     * vuelve a salir sola. Es deliberado — reintentar sin control una orden que quizás
     * ya se ejecutó es peor que dejarla visible como colgada en el Manager.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        $comandos = ComandoPos::where('punto_de_venta_id', $pdv->id)
            ->pendientes()
            ->orderBy('id')
            ->get();

        foreach ($comandos as $comando) {
            $comando->update([
                'estado' => ComandoPos::TOMADO,
                'tomado_at' => now(),
            ]);
        }

        return response()->json([
            'data' => $comandos->map(fn ($c) => [
                'id' => $c->id,
                'comando' => $c->comando->value,
            ])->values(),
        ]);
    }

    /**
     * La caja informa cómo le fue.
     */
    public function resultado(Request $request, int $comandoId): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        $datos = $request->validate([
            'exito' => 'required|boolean',
            'resultado' => 'nullable|string|max:2000',
        ]);

        // Acotado al PDV autenticado: una caja no puede cerrar órdenes de otra.
        $comando = ComandoPos::where('id', $comandoId)
            ->where('punto_de_venta_id', $pdv->id)
            ->first();

        if (! $comando) {
            return response()->json(['message' => 'Orden no encontrada.'], 404);
        }

        $comando->update([
            'estado' => $datos['exito'] ? ComandoPos::COMPLETADO : ComandoPos::FALLIDO,
            'resultado' => $datos['resultado'] ?? null,
            'finalizado_at' => now(),
        ]);

        Log::info('Comando POS finalizado', [
            'punto_de_venta_id' => $pdv->id,
            'comando' => $comando->comando->value,
            'exito' => $datos['exito'],
        ]);

        return response()->json(['message' => 'Resultado registrado.']);
    }
}
