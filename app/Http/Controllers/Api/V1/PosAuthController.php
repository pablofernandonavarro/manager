<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PuntoDeVenta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PosAuthController extends Controller
{
    /**
     * Autentica un Punto de Venta y devuelve un token Sanctum.
     */
    public function token(Request $request): JsonResponse
    {
        $request->validate([
            'punto_de_venta_id' => 'required|integer|exists:puntos_de_venta,id',
            'secret' => 'required|string',
        ]);

        $pdv = PuntoDeVenta::with('sucursal')->findOrFail($request->punto_de_venta_id);

        if (! $pdv->activo) {
            return response()->json(['message' => 'El punto de venta está inactivo.'], 401);
        }

        if (! Hash::check($request->secret, $pdv->secret)) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }

        if (! $pdv->sucursal) {
            return response()->json(['message' => 'El punto de venta no tiene una sucursal válida asignada.'], 500);
        }

        $pdv->tokens()->delete();
        $token = $pdv->createToken('pos-sync')->plainTextToken;

        return response()->json([
            'token' => $token,
            'sucursal_id' => $pdv->sucursal_id,
            'sucursal_nombre' => $pdv->sucursal->nombre,
            'pdv_nombre' => $pdv->nombre,
        ]);
    }
}
