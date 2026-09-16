<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Paginación por cursor de la sincronización incremental con las cajas.
 *
 * Orden estable `(updated_at, id)`: el cursor es la última fila entregada, así una página
 * nunca repite ni saltea filas aunque se modifiquen otras mientras la caja descarga.
 *
 * `hasta` se fija en la primera página y viaja dentro del cursor: lo que se modifica durante
 * la descarga queda para el próximo delta, así una importación en curso no alarga la
 * paginación sin fin.
 *
 * `synced_at` es la marca que la caja manda como próximo `updated_since`. Se le restan
 * MARGEN_MINUTOS: `updated_at` se fija al escribir, pero la fila recién se ve al confirmar la
 * transacción (un lote de importación puede tardar hasta 600 s). Sin margen, una fila
 * guardada al principio de una transacción larga podía quedar afuera para siempre. Lo que se
 * vuelve a enviar por el margen se aplica igual en la caja: no duplica nada.
 */
final class CursorSync
{
    public const MARGEN_MINUTOS = 15;

    public const LIMITE_MAXIMO = 5000;

    /**
     * @return array{limite: int, hasta: Carbon, despues: ?array{u: string, i: int}, desde: ?Carbon}
     */
    public static function desdeRequest(Request $request, int $limitePorDefecto): array
    {
        $limite = max(1, min(self::LIMITE_MAXIMO, (int) ($request->query('limit') ?: $limitePorDefecto)));
        $desde = $request->filled('updated_since') ? Carbon::parse((string) $request->query('updated_since'))->utc() : null;

        if (! $request->filled('cursor')) {
            return ['limite' => $limite, 'hasta' => now()->utc(), 'despues' => null, 'desde' => $desde];
        }

        $datos = json_decode(base64_decode(strtr((string) $request->query('cursor'), '-_', '+/')) ?: '', true);

        if (! is_array($datos) || ! isset($datos['u'], $datos['i'], $datos['h'])) {
            throw new InvalidArgumentException('Cursor inválido.');
        }

        return [
            'limite' => $limite,
            'hasta' => Carbon::parse($datos['h'])->utc(),
            'despues' => ['u' => (string) $datos['u'], 'i' => (int) $datos['i']],
            'desde' => $desde,
        ];
    }

    /** Aplica desde/hasta/cursor y el orden sobre las columnas calificadas de la tabla. */
    public static function aplicar(Builder $query, string $tabla, array $pagina): Builder
    {
        $query->where("{$tabla}.updated_at", '<=', $pagina['hasta']->format('Y-m-d H:i:s'))
            ->when($pagina['desde'], fn ($q, $desde) => $q->where("{$tabla}.updated_at", '>', $desde->format('Y-m-d H:i:s')))
            ->when($pagina['despues'], fn ($q, $despues) => $q->where(fn ($w) => $w
                ->where("{$tabla}.updated_at", '>', $despues['u'])
                ->orWhere(fn ($e) => $e->where("{$tabla}.updated_at", $despues['u'])->where("{$tabla}.id", '>', $despues['i']))))
            ->orderBy("{$tabla}.updated_at")
            ->orderBy("{$tabla}.id");

        return $query;
    }

    /** Cursor de la página siguiente, o null si esta era la última. */
    public static function siguiente(?object $ultima, int $entregadas, array $pagina): ?string
    {
        if (! $ultima || $entregadas < $pagina['limite']) {
            return null;
        }

        $json = json_encode([
            'u' => Carbon::parse($ultima->getRawOriginal('updated_at'))->format('Y-m-d H:i:s'),
            'i' => (int) $ultima->getKey(),
            'h' => $pagina['hasta']->toIso8601String(),
        ]);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    public static function marca(Carbon $hasta): string
    {
        return $hasta->copy()->subMinutes(self::MARGEN_MINUTOS)->toIso8601String();
    }
}
