<?php

namespace App\Livewire\ListasPrecios;

use App\Models\ListaPrecio;
use App\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;

class Buscador extends Component
{
    use WithPagination;

    public ListaPrecio $lista;
    public string $busqueda = '';
    public string $ordenar = 'nombre';
    public bool $soloConStock = false;

    public function mount($id)
    {
        $this->lista = ListaPrecio::findOrFail($id);
    }

    public function updatingBusqueda()
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Product::where('es_vendible', true);

        // Búsqueda
        if ($this->busqueda) {
            $search = strtolower(trim($this->busqueda));
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo_interno', 'like', "%{$search}%")
                    ->orWhere('codigo_barras', 'like', "%{$search}%");
            });
        }

        // Filtro de stock
        if ($this->soloConStock) {
            $query->where('stock', '>', 0);
        }

        // Ordenamiento
        $query->orderBy($this->ordenar);

        $productos = $query->paginate(50)->through(function ($producto) {
            $precioEfectivo = $this->lista->precioEfectivoParaProducto($producto->id);

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

        return view('livewire.listas-precios.buscador', [
            'productos' => $productos,
        ])->layout('layouts.app');
    }
}
