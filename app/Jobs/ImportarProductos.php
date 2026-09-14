<?php

namespace App\Jobs;

use App\Models\ImportacionProducto;
use App\Services\ImportacionProductos;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Aplica una importación de productos en la cola.
 *
 * Una sola vez (`tries = 1`): si falla a mitad, la transacción de ImportacionProductos
 * deshace todo y reintentar solo repetiría el error. `timeout` largo porque 5.000 filas
 * tardan minutos; DB_QUEUE_RETRY_AFTER tiene que ser mayor, o la cola la volvería a dar
 * por perdida y la correría dos veces.
 */
class ImportarProductos implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $importacionId) {}

    public function handle(ImportacionProductos $importacion): void
    {
        $registro = ImportacionProducto::find($this->importacionId);

        if (! $registro || $registro->estado !== ImportacionProducto::PENDIENTE) {
            return;
        }

        $registro->update(['estado' => ImportacionProducto::PROCESANDO, 'iniciado_at' => now()]);

        try {
            $resultado = $importacion->aplicar(Storage::disk('local')->path($registro->archivo), $registro->user_id, $registro->nombre_original);

            $registro->update(['estado' => ImportacionProducto::TERMINADA, 'resultado' => $resultado, 'terminado_at' => now()]);
        } catch (Throwable $e) {
            $this->marcarFallida($registro, $e);
        } finally {
            Storage::disk('local')->delete($registro->archivo);
        }
    }

    /** Timeout o error fuera de handle(): que la pantalla no quede en "procesando" para siempre. */
    public function failed(Throwable $e): void
    {
        if ($registro = ImportacionProducto::find($this->importacionId)) {
            $this->marcarFallida($registro, $e);
            Storage::disk('local')->delete($registro->archivo);
        }
    }

    private function marcarFallida(ImportacionProducto $registro, Throwable $e): void
    {
        if (! $e instanceof \RuntimeException) {
            Log::error('Importación de productos fallida', ['importacion' => $registro->id, 'error' => $e->getMessage()]);
        }

        $registro->update([
            'estado' => ImportacionProducto::FALLIDA,
            // Los RuntimeException del importador son mensajes para el usuario; lo demás no.
            'error' => $e instanceof \RuntimeException ? $e->getMessage() : 'Error inesperado al importar. No se guardó nada; revisá el log del servidor.',
            'terminado_at' => now(),
        ]);
    }
}
