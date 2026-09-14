<?php

namespace App\Http\Middleware;

use App\Models\PuntoDeVenta;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anota en el punto de venta qué versión corre, cómo está instalado y cuándo habló por
 * última vez. La caja lo manda en los encabezados X-POS-Version y X-POS-Tipo de cada
 * llamada a la API.
 *
 * Cada caja llama varias veces por minuto: solo se escribe si algo cambió o si pasó más
 * de un minuto desde la última anotación, para no convertir cada GET en un UPDATE.
 */
class RegistrarConexionPos
{
    private const TIPOS = ['escritorio', 'clasica'];

    public function handle(Request $request, Closure $next): Response
    {
        $pdv = $request->user();

        if ($pdv instanceof PuntoDeVenta) {
            $this->registrar($pdv, $request);
        }

        return $next($request);
    }

    private function registrar(PuntoDeVenta $pdv, Request $request): void
    {
        $datos = ['ultima_conexion_at' => now()];

        // Texto que viene de afuera: se acota y se valida antes de guardarlo.
        $version = mb_substr(trim((string) $request->header('X-POS-Version')), 0, 50);
        if ($version !== '' && preg_match('/^[\w.\-]+$/', $version)) {
            $datos['version_pos'] = $version;
        }

        $tipo = strtolower((string) $request->header('X-POS-Tipo'));
        if (in_array($tipo, self::TIPOS, true)) {
            $datos['tipo_instalacion'] = $tipo;
        }

        $cambio = ($datos['version_pos'] ?? $pdv->version_pos) !== $pdv->version_pos
            || ($datos['tipo_instalacion'] ?? $pdv->tipo_instalacion) !== $pdv->tipo_instalacion;

        if (! $cambio && $pdv->ultima_conexion_at?->gt(now()->subMinute())) {
            return;
        }

        $pdv->timestamps = false;
        $pdv->forceFill($datos)->saveQuietly();
        $pdv->timestamps = true;
    }
}
