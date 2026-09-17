<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Clona, compila y empaqueta el POS en segundo plano: clonar + composer + npm build
 * tarda uno o dos minutos, más de lo que conviene bloquear un request HTTP.
 *
 * El estado se guarda en cache (no en una tabla) porque solo puede haber una
 * publicación en curso a la vez y no hace falta historial de intentos.
 */
class PublicarUltimaVersionPosJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public const CACHE_KEY = 'pos_publicacion_estado';

    public function handle(): void
    {
        Cache::put(self::CACHE_KEY, ['estado' => 'procesando', 'mensaje' => null], now()->addMinutes(10));

        $codigo = Artisan::call('pos:publicar-ultima-version');
        $salida = Artisan::output();

        Cache::put(self::CACHE_KEY, [
            'estado' => $codigo === 0 ? 'completado' : 'error',
            'mensaje' => $codigo === 0
                ? 'Nueva versión publicada. Las cajas la ven al pedir "Actualizar el POS".'
                : "Falló la publicación:\n{$salida}",
        ], now()->addMinutes(10));
    }

    public function failed(\Throwable $exception): void
    {
        Cache::put(self::CACHE_KEY, [
            'estado' => 'error',
            'mensaje' => 'Falló la publicación: '.$exception->getMessage(),
        ], now()->addMinutes(10));
    }
}
