<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * La app de escritorio del POS publicada para descargar, por plataforma. La deja
 * `deploy/recibir-escritorio.sh` (CI) o `pos:publicar-escritorio` en el disco local:
 * `pos-escritorio.json` + zip para Windows y `pos-escritorio-mac.json` + zip para Mac.
 *
 * Las dos plataformas se compilan desde el mismo tag, así que tienen la misma versión: para
 * comparar la versión de una caja alcanza con la de Windows.
 */
final class AppEscritorio
{
    public const PLATAFORMAS = ['windows', 'mac'];

    /**
     * @return array{version: string, formato: string, tamano: int, sha256?: string, generado_at: string, archivo: string}|null
     */
    public static function publicada(string $plataforma = 'windows'): ?array
    {
        if (! in_array($plataforma, self::PLATAFORMAS, true)) {
            return null;
        }

        $base = Storage::disk('local')->path('pos-escritorio/'.($plataforma === 'mac' ? 'pos-escritorio-mac' : 'pos-escritorio'));
        $meta = json_decode(@file_get_contents("{$base}.json") ?: 'null', true);

        if (! is_array($meta) || ! isset($meta['version'], $meta['formato'])) {
            return null;
        }

        $archivo = "{$base}.{$meta['formato']}";

        return file_exists($archivo) ? [...$meta, 'archivo' => $archivo] : null;
    }

    /** Versión contra la que se compara una caja de escritorio (la misma en las dos plataformas). */
    public static function ultimaVersion(): ?string
    {
        return self::publicada()['version'] ?? null;
    }
}
