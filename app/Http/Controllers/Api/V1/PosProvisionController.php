<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CodigoInstalacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PosProvisionController extends Controller
{
    /**
     * Canjea un código de instalación y devuelve las credenciales del punto de venta.
     *
     * El secret no se guarda en ningún lado: se genera recién acá y se entrega una sola
     * vez. Por eso reinstalar una caja rota la credencial anterior automáticamente, que
     * es justo lo que se quiere si la máquina vieja se perdió o quedó comprometida.
     *
     * No lleva auth: el código ES la credencial. Por eso es de un solo uso, vence, y
     * está limitado por rate limit.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'codigo' => 'required|string|max:20',
        ]);

        $codigo = CodigoInstalacion::normalizar($request->string('codigo')->toString());

        $resultado = DB::transaction(function () use ($codigo, $request) {
            $registro = CodigoInstalacion::where('codigo', $codigo)
                ->lockForUpdate()
                ->first();

            if (! $registro) {
                return ['error' => 'El código no existe. Revisá que esté bien tipeado.', 'status' => 404];
            }

            if ($registro->usado_at !== null) {
                return ['error' => 'Este código ya fue usado. Pedí uno nuevo desde el Manager.', 'status' => 409];
            }

            if ($registro->expira_at->isPast()) {
                return ['error' => 'El código venció. Pedí uno nuevo desde el Manager.', 'status' => 410];
            }

            $pdv = $registro->puntoDeVenta()->with('sucursal')->first();

            if (! $pdv || ! $pdv->activo) {
                return ['error' => 'El punto de venta está inactivo o fue eliminado.', 'status' => 403];
            }

            $secret = Str::random(40);

            $pdv->update(['secret' => Hash::make($secret)]);

            // Los tokens viejos dejan de servir: si esto es una reinstalación, la máquina
            // anterior no puede seguir sincronizando con las credenciales que tenía.
            $pdv->tokens()->delete();

            $registro->update([
                'usado_at' => now(),
                'usado_ip' => $request->ip(),
            ]);

            return [
                'pdv' => $pdv,
                'secret' => $secret,
            ];
        });

        if (isset($resultado['error'])) {
            return response()->json(['message' => $resultado['error']], $resultado['status']);
        }

        $pdv = $resultado['pdv'];

        Log::info('POS aprovisionado', [
            'punto_de_venta_id' => $pdv->id,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'punto_de_venta_id' => $pdv->id,
            'secret' => $resultado['secret'],
            'pdv_nombre' => $pdv->nombre,
            'sucursal_id' => $pdv->sucursal_id,
            'sucursal_nombre' => $pdv->sucursal?->nombre,
        ]);
    }
}
