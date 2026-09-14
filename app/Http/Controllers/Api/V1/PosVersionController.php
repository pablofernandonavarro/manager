<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\VersionPos;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PosVersionController extends Controller
{
    /**
     * Qué versión del POS debería estar corriendo la caja.
     */
    public function actual(): JsonResponse
    {
        $version = VersionPos::vigente();

        if (! $version) {
            return response()->json(['message' => 'No hay ninguna versión publicada.'], 404);
        }

        return response()->json([
            'version' => $version->version,
            'hash' => $version->hash,
            'tamano' => $version->tamano,
            'notas' => $version->notas,
        ]);
    }

    /**
     * Entrega el paquete. Va autenticado como todo el resto: solo una caja registrada
     * puede bajar el código.
     */
    public function descargar(): BinaryFileResponse|JsonResponse
    {
        $version = VersionPos::vigente();

        if (! $version) {
            return response()->json(['message' => 'No hay ninguna versión publicada.'], 404);
        }

        $ruta = Storage::disk('local')->path($version->archivo);

        if (! file_exists($ruta)) {
            return response()->json([
                'message' => "El paquete de la versión {$version->version} no está en el disco del Manager.",
            ], 500);
        }

        return response()->download($ruta, "pos-{$version->version}.zip");
    }
}
