<?php

namespace App\Jobs;

use App\Models\ImportacionProducto;
use App\Models\ImportacionProductoError;
use App\Models\ImportacionProductoLote;
use App\Services\ImportacionProductos;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Procesa un lote de filas de una importación.
 *
 * Todo el lote es una transacción: productos, stock, errores de fila, el registro del lote y
 * los contadores entran juntos o no entra nada. Un reintento después de un corte a mitad
 * repite el lote limpio; uno después del commit encuentra el lote registrado y no hace nada.
 * Así ningún reintento duplica productos, stock ni contadores.
 */
class ProcesarLoteImportacionProductos implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * 250 filas: ~10 s en la PC de desarrollo, hasta ~85 s en la e2-micro de producción.
     * DB_QUEUE_RETRY_AFTER tiene que ser mayor (900 en producción).
     */
    public int $timeout = 600;

    public function __construct(
        public int $importacionId,
        public int $lote,
        public int $desde,
        public int $hasta,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(ImportacionProductos $importacion): void
    {
        $registro = ImportacionProducto::find($this->importacionId);

        if (! $registro || $registro->estado !== ImportacionProducto::PROCESANDO) {
            return;
        }

        $ruta = Storage::disk('local')->path($registro->archivo);
        $inicio = hrtime(true);
        memory_reset_peak_usage();

        $conteo = DB::transaction(function () use ($importacion, $ruta, $inicio) {
            $registro = ImportacionProducto::lockForUpdate()->find($this->importacionId);

            if (ImportacionProductoLote::where('importacion_id', $this->importacionId)->where('lote', $this->lote)->exists()) {
                return null;
            }

            // Filas repetidas en el archivo: ya tienen su error, registrado al preparar.
            $saltear = ImportacionProductoError::where('importacion_id', $this->importacionId)
                ->whereBetween('fila', [$this->desde, $this->hasta])
                ->pluck('fila')->flip()->all();

            $conteo = $importacion->procesarLote(
                $ruta,
                $this->desde,
                $this->hasta,
                $saltear,
                fn (int $fila, ?string $codigo, string $mensaje, array $datos) => ImportacionProductoError::create([
                    'importacion_id' => $this->importacionId,
                    'fila' => $fila,
                    'codigo' => $codigo,
                    'mensaje' => $mensaje,
                    'datos' => $datos,
                ]),
                $registro->user_id,
                $registro->referencia(),
            );

            ImportacionProductoLote::create([
                'importacion_id' => $this->importacionId,
                'lote' => $this->lote,
                'desde' => $this->desde,
                'hasta' => $this->hasta,
                'procesadas' => $conteo['procesadas'],
                'exitosas' => $conteo['exitosas'],
                'errores' => $conteo['errores'],
                'duracion_ms' => (int) ((hrtime(true) - $inicio) / 1e6),
                'memoria_mb' => (int) ceil(memory_get_peak_usage(true) / 1048576),
            ]);

            $registro->update([
                'filas_procesadas' => $registro->filas_procesadas + $conteo['procesadas'],
                'filas_exitosas' => $registro->filas_exitosas + $conteo['exitosas'],
                'filas_con_error' => $registro->filas_con_error + $conteo['errores'],
                'creados' => $registro->creados + $conteo['creados'],
                'actualizados' => $registro->actualizados + $conteo['actualizados'],
                'modelos_nuevos' => $registro->modelos_nuevos + $conteo['modelos'],
                'cambios_stock' => $registro->cambios_stock + $conteo['stock'],
            ]);

            return $conteo;
        });

        if ($conteo !== null) {
            Log::info('Importación de productos: lote procesado', [
                'importacion' => $this->importacionId,
                'lote' => $this->lote,
                'filas' => "{$this->desde}-{$this->hasta}",
                'exitosas' => $conteo['exitosas'],
                'errores' => $conteo['errores'],
                'segundos' => round((hrtime(true) - $inicio) / 1e9, 1),
                'memoria_mb' => (int) ceil(memory_get_peak_usage(true) / 1048576),
                'intento' => $this->attempts(),
            ]);
        }
    }
}
