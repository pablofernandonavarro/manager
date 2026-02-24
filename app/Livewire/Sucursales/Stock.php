<?php

namespace App\Livewire\Sucursales;

use App\Models\Product;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Stock extends Component
{
    use WithPagination;

    public ?int $sucursalSeleccionada = null;
    public string $busqueda = '';

    public function mount(): void
    {
        // Seleccionar la primera sucursal activa por defecto
        $this->sucursalSeleccionada = Sucursal::where('activo', true)->first()?->id;
    }

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatingSucursalSeleccionada(): void
    {
        $this->resetPage();
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $sucursales = Sucursal::where('activo', true)
            ->orderBy('nombre')
            ->get();

        $query = Product::query()
            ->select('products.*')
            ->leftJoin('stock_sucursal', function ($join) {
                $join->on('products.id', '=', 'stock_sucursal.product_id')
                    ->where('stock_sucursal.sucursal_id', '=', $this->sucursalSeleccionada);
            })
            ->selectRaw('COALESCE(stock_sucursal.cantidad, 0) as stock_sucursal');

        if ($this->busqueda) {
            $query->where(function ($q) {
                $q->where('products.nombre', 'like', '%' . $this->busqueda . '%')
                    ->orWhere('products.codigo_interno', 'like', '%' . $this->busqueda . '%')
                    ->orWhere('products.codigo_barras', 'like', '%' . $this->busqueda . '%');
            });
        }

        $productos = $query->orderBy('products.nombre')->paginate(20);

        // Calcular totales
        $stockTotal = $this->sucursalSeleccionada
            ? StockSucursal::where('sucursal_id', $this->sucursalSeleccionada)->sum('cantidad')
            : 0;

        $productosConStock = $this->sucursalSeleccionada
            ? StockSucursal::where('sucursal_id', $this->sucursalSeleccionada)
                ->where('cantidad', '>', 0)
                ->count()
            : 0;

        return view('livewire.sucursales.stock', [
            'sucursales' => $sucursales,
            'productos' => $productos,
            'stockTotal' => $stockTotal,
            'productosConStock' => $productosConStock,
        ]);
    }
}
