<?php

namespace App\Jobs;

use App\Models\ImportacionProducto;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Último eslabón de la cadena: todos los lotes terminaron.
 */
class FinalizarImportacionProductos implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $importacionId) {}

    public function handle(): void
    {
        $registro = ImportacionProducto::find($this->importacionId);

        if (! $registro || $registro->estado !== ImportacionProducto::PROCESANDO) {
            return;
        }

        [$estado, $mensaje] = match (true) {
            $registro->total_filas === 0 => [ImportacionProducto::FALLIDA, 'El archivo no tiene filas con datos.'],
            $registro->filas_con_error > 0 => [ImportacionProducto::COMPLETADA_CON_ERRORES, "{$registro->filas_exitosas} filas importadas y {$registro->filas_con_error} con error: corregí esas filas y volvé a subirlas (las que ya entraron se actualizan, no se duplican)."],
            default => [ImportacionProducto::COMPLETADA, "{$registro->filas_exitosas} filas importadas."],
        };

        $registro->update(['estado' => $estado, 'mensaje' => $mensaje, 'finalizado_at' => now()]);

        Storage::disk('local')->delete($registro->archivo);
    }
}
