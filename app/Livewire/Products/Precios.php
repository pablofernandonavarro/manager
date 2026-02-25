<?php

namespace App\Livewire\Products;

use App\Models\ListaPrecio;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Precios extends Component
{
    use WithPagination;

    public ?int $selectedListaId = null;

    public string $busqueda = '';

    public string $ordenar = 'nombre';

    public bool $soloConStock = false;

    public function updatingSelectedListaId(): void
    {
        $this->resetPage();
        $this->busqueda = '';
    }

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $listas = ListaPrecio::where('activo', true)->orderBy('nombre')->get();

        $lista = $this->selectedListaId
            ? $listas->firstWhere('id', $this->selectedListaId)
            : null;

        $productos = null;

        if ($lista) {
            $query = Product::where('es_vendible', true);

            if ($this->busqueda) {
                $search = strtolower(trim($this->busqueda));
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                        ->orWhere('codigo_interno', 'like', "%{$search}%")
                        ->orWhere('codigo_barras', 'like', "%{$search}%");
                });
            }

            if ($this->soloConStock) {
                $query->where('stock', '>', 0);
            }

            $query->orderBy($this->ordenar);

            $productos = $query->paginate(50)->through(function ($producto) use ($lista) {
                $precioEfectivo = $lista->precioEfectivoParaProducto($producto->id) ?? 0;

                return [
                    'id' => $producto->id,
                    'codigo' => $producto->codigo_interno ?? $producto->codigo_barras,
                    'nombre' => $producto->nombre,
                    'stock' => $producto->stock,
                    'precio_base' => $producto->precio,
                    'precio_lista' => $precioEfectivo,
                    'diferencia' => $precioEfectivo - $producto->precio,
                    'porcentaje' => $producto->precio > 0
                        ? (($precioEfectivo - $producto->precio) / $producto->precio) * 100
                        : 0,
                ];
            });
        }

        return view('livewire.products.precios', [
            'listas' => $listas,
            'lista' => $lista,
            'productos' => $productos,
        ]);
    }
}
