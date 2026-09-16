<?php

namespace App\Jobs;

use App\Models\ImportacionProducto;
use App\Models\ImportacionProductoError;
use App\Services\ImportacionProductos;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Primer paso de una importación: recorre el archivo por tramos, cuenta las filas, marca las
 * repetidas dentro del archivo y encadena los lotes + el cierre.
 *
 * Idempotente: `lotes_total` se graba en la misma transacción que encola la cadena (la cola
 * es la base de datos, así que los jobs entran en esa transacción). Un reintento que la
 * encuentra grabada no vuelve a encolar.
 */
class PrepararImportacionProductos implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $importacionId) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function handle(ImportacionProductos $importacion): void
    {
        $registro = ImportacionProducto::find($this->importacionId);

        if (! $registro || ! $registro->enCurso() || $registro->lotes_total !== null) {
            return;
        }

        $ruta = Storage::disk('local')->path($registro->archivo);

        if (! is_file($ruta)) {
            self::marcarFallida($this->importacionId, 'No se encontró el archivo subido.');

            return;
        }

        $registro->update(['estado' => ImportacionProducto::PROCESANDO, 'iniciado_at' => $registro->iniciado_at ?? now()]);

        $recorrido = $importacion->repetidas($ruta);
        $lotes = [];
        $numero = 0;

        for ($desde = 2; $desde <= $recorrido['ultima_fila']; $desde += ImportacionProductos::FILAS_POR_LOTE) {
            $lotes[] = new ProcesarLoteImportacionProductos(
                $this->importacionId,
                ++$numero,
                $desde,
                min($recorrido['ultima_fila'], $desde + ImportacionProductos::FILAS_POR_LOTE - 1),
                // CSV: el byte donde arranca el lote, así no se relee el archivo desde el principio.
                $recorrido['offsets'][$desde] ?? null,
            );
        }

        DB::transaction(function () use ($recorrido, $lotes): void {
            $registro = ImportacionProducto::lockForUpdate()->find($this->importacionId);

            if (! $registro || $registro->lotes_total !== null) {
                return;
            }

            foreach (array_chunk($recorrido['repetidas'], 200, true) as $tramo) {
                ImportacionProductoError::upsert(
                    collect($tramo)->map(fn ($r, $fila) => [
                        'importacion_id' => $this->importacionId,
                        'fila' => $fila,
                        'codigo' => $r['codigo'],
                        'mensaje' => $r['mensaje'],
                        'datos' => json_encode($r['datos'], JSON_UNESCAPED_UNICODE),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->values()->all(),
                    ['importacion_id', 'fila'],
                    ['codigo', 'mensaje', 'datos', 'updated_at'],
                );
            }

            $registro->update(['total_filas' => $recorrido['total'], 'lotes_total' => count($lotes)]);

            $id = $this->importacionId;
            Bus::chain([...$lotes, new FinalizarImportacionProductos($id)])
                ->catch(function (Throwable $e) use ($id): void {
                    PrepararImportacionProductos::marcarFallida($id, 'Se detuvo por un error inesperado: '.$e->getMessage());
                })
                ->dispatch();
        });
    }

    public function failed(Throwable $e): void
    {
        self::marcarFallida($this->importacionId, 'No se pudo leer el archivo: '.$e->getMessage());
    }

    /** Lo que ya se procesó queda guardado (cada lote es su transacción); el resto no. */
    public static function marcarFallida(int $importacionId, string $mensaje): void
    {
        $registro = ImportacionProducto::find($importacionId);

        if (! $registro || ! $registro->enCurso()) {
            return;
        }

        $registro->update([
            'estado' => ImportacionProducto::FALLIDA,
            'mensaje' => mb_substr($mensaje, 0, 1000),
            'finalizado_at' => now(),
        ]);

        Storage::disk('local')->delete($registro->archivo);
    }
}
