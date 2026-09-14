<?php

namespace App\Livewire\Products;

use App\Services\ImportacionProductos;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Alta y actualización masiva de productos desde Excel: subir → previsualizar → aplicar.
 */
class Importar extends Component
{
    use AuthorizesRequests, WithFileUploads;

    private const FILAS_VISIBLES = 300;

    public $archivo = null;

    /** @var list<array<string, mixed>> */
    public array $previa = [];

    public int $totalFilas = 0;

    /** @var list<string> */
    public array $errores = [];

    /** @var array<string, int> */
    public array $resumen = [];

    /** @var array<string, int>|null */
    public ?array $resultado = null;

    public function mount(): void
    {
        $this->authorize('productos.crear');
    }

    public function updatedArchivo(ImportacionProductos $importacion): void
    {
        $this->authorize('productos.crear');

        $this->reset(['previa', 'totalFilas', 'errores', 'resumen', 'resultado']);
        $this->validate(['archivo' => 'required|file|mimes:xlsx,xls,csv|max:10240'], [
            'archivo.mimes' => 'Subí un archivo Excel (.xlsx o .xls) o CSV.',
            'archivo.max' => 'El archivo no puede pesar más de 10 MB.',
        ]);

        $analisis = $importacion->analizar($this->archivo->getRealPath());

        $this->errores = [...$analisis['errores'], ...$this->erroresDePermiso($analisis['resumen'])];
        $this->resumen = $analisis['resumen'];
        $this->totalFilas = count($analisis['filas']);
        $this->previa = array_slice($analisis['filas'], 0, self::FILAS_VISIBLES);
    }

    public function aplicar(ImportacionProductos $importacion): void
    {
        $this->authorize('productos.crear');

        if (! $this->archivo) {
            return;
        }

        // Se vuelve a analizar el archivo: la previsualización es solo para mostrar.
        $analisis = $importacion->analizar($this->archivo->getRealPath());
        if ($analisis['errores'] || $this->erroresDePermiso($analisis['resumen'])) {
            $this->errores = [...$analisis['errores'], ...$this->erroresDePermiso($analisis['resumen'])];

            return;
        }

        try {
            $this->resultado = $importacion->aplicar($this->archivo->getRealPath(), auth()->id(), $this->archivo->getClientOriginalName());
        } catch (\RuntimeException $e) {
            $this->errores = [$e->getMessage()];

            return;
        }

        $this->reset(['archivo', 'previa', 'totalFilas', 'errores', 'resumen']);
    }

    public function descartar(): void
    {
        $this->reset(['archivo', 'previa', 'totalFilas', 'errores', 'resumen', 'resultado']);
    }

    /**
     * @param  array<string, int>  $resumen
     * @return list<string>
     */
    private function erroresDePermiso(array $resumen): array
    {
        return ($resumen['con_stock'] ?? 0) > 0 && ! auth()->user()->can('stock.ajustar')
            ? ['El archivo trae columnas de stock y no tenés permiso para ajustar stock. Borrá esas columnas o pedí el permiso.']
            : [];
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.products.importar', [
            'sucursales' => \App\Models\Sucursal::where('activo', true)->pluck('nombre', 'id'),
        ]);
    }
}
