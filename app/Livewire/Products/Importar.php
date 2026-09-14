<?php

namespace App\Livewire\Products;

use App\Jobs\PrepararImportacionProductos;
use App\Models\ImportacionProducto;
use App\Models\Sucursal;
use App\Services\ImportacionProductos;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Importación de productos desde Excel: subir → vista previa → encolar.
 *
 * El pedido web solo valida el archivo (encabezado y cantidad de filas), lo guarda, crea el
 * registro y encola. Las filas se procesan en la cola por lotes; esta pantalla muestra el
 * progreso con polling y se puede cerrar sin cortar la importación.
 */
class Importar extends Component
{
    use AuthorizesRequests, WithFileUploads;

    private const FILAS_VISIBLES = 300;

    public $archivo = null;

    /** @var list<array<string, mixed>> */
    public array $previa = [];

    public int $totalFilas = 0;

    /** @var list<string> Errores que impiden importar (encabezado, tamaño, permisos). */
    public array $errores = [];

    /** @var list<string> Filas de la vista previa que van a quedar con error. */
    public array $avisos = [];

    /** @var array<string, int> */
    public array $resumen = [];

    public ?int $verErroresDe = null;

    public function mount(): void
    {
        $this->authorize('productos.crear');
    }

    public function updatedArchivo(ImportacionProductos $importacion): void
    {
        $this->authorize('productos.crear');

        $this->reset(['previa', 'totalFilas', 'errores', 'avisos', 'resumen']);
        $this->validate(['archivo' => 'required|file|mimes:xlsx,xls,csv|max:10240'], [
            'archivo.mimes' => 'Subí un archivo Excel (.xlsx o .xls) o CSV.',
            'archivo.max' => 'El archivo no puede pesar más de 10 MB.',
        ]);

        $inspeccion = $importacion->inspeccionar($this->archivo->getRealPath());
        $this->errores = [...$inspeccion['errores'], ...$this->erroresDePermiso($inspeccion['columnas_stock'])];

        if ($this->errores) {
            return;
        }

        $analisis = $importacion->analizar($this->archivo->getRealPath(), self::FILAS_VISIBLES);
        $this->avisos = $analisis['errores'];
        $this->resumen = $analisis['resumen'];
        $this->previa = $analisis['filas'];
        $this->totalFilas = $inspeccion['ultima_fila'] - 1;
    }

    public function aplicar(ImportacionProductos $importacion): void
    {
        $this->authorize('productos.crear');

        if (! $this->archivo) {
            return;
        }

        $inspeccion = $importacion->inspeccionar($this->archivo->getRealPath());
        if ($errores = [...$inspeccion['errores'], ...$this->erroresDePermiso($inspeccion['columnas_stock'])]) {
            $this->errores = $errores;

            return;
        }

        $extension = strtolower($this->archivo->getClientOriginalExtension()) ?: 'xlsx';
        $ruta = $this->archivo->storeAs('importaciones', Str::uuid().'.'.$extension, 'local');

        $registro = ImportacionProducto::create([
            'user_id' => auth()->id(),
            'archivo' => $ruta,
            'nombre_original' => mb_substr($this->archivo->getClientOriginalName(), 0, 255),
            'estado' => ImportacionProducto::PENDIENTE,
            // Provisorio: el paso de preparación cuenta las filas con datos.
            'total_filas' => max(0, $inspeccion['ultima_fila'] - 1),
            'resumen' => $this->resumen ?: null,
        ]);

        PrepararImportacionProductos::dispatch($registro->id);

        $this->reset(['archivo', 'previa', 'totalFilas', 'errores', 'avisos', 'resumen']);
        $this->verErroresDe = null;
    }

    public function descartar(): void
    {
        $this->reset(['archivo', 'previa', 'totalFilas', 'errores', 'avisos', 'resumen']);
    }

    public function alternarErrores(int $importacionId): void
    {
        $this->verErroresDe = $this->verErroresDe === $importacionId ? null : $importacionId;
    }

    /** @return list<string> */
    private function erroresDePermiso(bool $columnasStock): array
    {
        return $columnasStock && ! auth()->user()->can('stock.ajustar')
            ? ['El archivo trae columnas de stock y no tenés permiso para ajustar stock. Borrá esas columnas o pedí el permiso.']
            : [];
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $importaciones = ImportacionProducto::with('user:id,name')->latest('id')->limit(8)->get();

        return view('livewire.products.importar', [
            'sucursales' => Sucursal::where('activo', true)->pluck('nombre', 'id'),
            'importaciones' => $importaciones,
            'hayEnCurso' => $importaciones->contains(fn (ImportacionProducto $i) => $i->enCurso()),
            'erroresDeImportacion' => $this->verErroresDe
                ? ImportacionProducto::find($this->verErroresDe)?->errores()->orderBy('fila')->limit(200)->get() ?? collect()
                : collect(),
        ]);
    }
}
