<?php

namespace App\Livewire\ListasPrecios;

use App\Models\DetallePrecio;
use App\Models\ListaPrecio;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Edit extends Component
{
    public ListaPrecio $lista;

    public string $busquedaProducto = '';

    public ?int $productoSeleccionadoId = null;

    public string $productoSeleccionadoNombre = '';

    public string $nuevoPrecioOverride = '';

    public string $nuevaVigenciaDesde = '';

    public string $nuevaVigenciaHasta = '';

    public function mount(int $id): void
    {
        $this->lista = ListaPrecio::findOrFail($id);
    }

    public function agregarPrecio(): void
    {
        $this->validate([
            'productoSeleccionadoId' => 'required|integer|exists:products,id',
            'nuevoPrecioOverride' => 'required|numeric|min:0',
            'nuevaVigenciaDesde' => 'nullable|date',
            'nuevaVigenciaHasta' => 'nullable|date|after_or_equal:nuevaVigenciaDesde',
        ]);

        DetallePrecio::updateOrCreate(
            [
                'lista_precio_id' => $this->lista->id,
                'product_id' => $this->productoSeleccionadoId,
            ],
            [
                'precio_override' => $this->nuevoPrecioOverride,
                'vigencia_desde' => $this->nuevaVigenciaDesde ?: null,
                'vigencia_hasta' => $this->nuevaVigenciaHasta ?: null,
            ]
        );

        $this->productoSeleccionadoId = null;
        $this->productoSeleccionadoNombre = '';
        $this->nuevoPrecioOverride = '';
        $this->nuevaVigenciaDesde = '';
        $this->nuevaVigenciaHasta = '';
        $this->busquedaProducto = '';
    }

    public function seleccionarProducto(int $id, string $nombre): void
    {
        $this->productoSeleccionadoId = $id;
        $this->productoSeleccionadoNombre = $nombre;
        $this->busquedaProducto = '';
    }

    public function eliminarDetalle(int $id): void
    {
        DetallePrecio::findOrFail($id)->delete();
    }

    public function getProductosSugeridosProperty(): mixed
    {
        if (strlen($this->busquedaProducto) < 2) {
            return collect();
        }

        return Product::query()
            ->where(function ($q): void {
                $q->where('nombre', 'like', '%'.$this->busquedaProducto.'%')
                    ->orWhere('codigo_interno', 'like', '%'.$this->busquedaProducto.'%');
            })
            ->whereNull('parent_id')
            ->limit(10)
            ->get(['id', 'nombre', 'codigo_interno', 'precio']);
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.listas-precios.edit', [
            'detalles' => $this->lista->detalles()->with('product')->orderBy('id')->get(),
            'productosSugeridos' => $this->productosSugeridos,
        ]);
    }
}
