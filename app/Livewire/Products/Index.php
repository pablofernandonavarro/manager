<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;
    public string $filterEstado = 'todos';
    public string $filterStock = 'todos';
    public string $filterTipo = 'todos'; // Nuevo filtro

    /**
     * Resetea la paginación cuando se realiza una búsqueda.
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Resetea la paginación cuando cambian los filtros.
     */
    public function updatingFilterEstado(): void
    {
        $this->resetPage();
    }

    /**
     * Resetea la paginación cuando cambian los filtros.
     */
    public function updatingFilterStock(): void
    {
        $this->resetPage();
    }

    /**
     * Resetea la paginación cuando cambia el filtro de tipo.
     */
    public function updatingFilterTipo(): void
    {
        $this->resetPage();
    }

    /**
     * Alterna el estado de un producto.
     */
    public function toggleEstado(int $productId): void
    {
        $product = Product::findOrFail($productId);
        $product->update(['estado' => $product->estado ? 0 : 1]);

        session()->flash('success', 'Estado del producto actualizado correctamente.');
    }

    /**
     * Elimina un producto.
     */
    public function delete(int $productId): void
    {
        $product = Product::findOrFail($productId);
        $product->delete();

        session()->flash('success', 'Producto eliminado correctamente.');
    }

    /**
     * Restaura un producto eliminado.
     */
    public function restore(int $productId): void
    {
        $product = Product::withTrashed()->findOrFail($productId);
        $product->restore();

        session()->flash('success', 'Producto restaurado correctamente.');
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $products = Product::withTrashed()
            ->when($this->search, function ($query) {
                $query->search($this->search);
            })
            ->when($this->filterEstado !== 'todos', function ($query) {
                $query->where('estado', $this->filterEstado);
            })
            ->when($this->filterStock === 'disponible', function ($query) {
                $query->where('stock', '>', 0);
            })
            ->when($this->filterStock === 'critico', function ($query) {
                $query->whereRaw('stock <= stock_critico');
            })
            ->when($this->filterStock === 'sin_stock', function ($query) {
                $query->where('stock', '<=', 0);
            })
            ->when($this->filterTipo === 'simple', function ($query) {
                $query->simple();
            })
            ->when($this->filterTipo === 'configurable', function ($query) {
                $query->configurable();
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        return view('livewire.products.index', [
            'products' => $products,
        ]);
    }
}
