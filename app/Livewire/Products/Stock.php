<?php

namespace App\Livewire\Products;

use App\Enums\EstadoRemito;
use App\Models\Product;
use App\Models\RemitoDetalle;
use App\Models\StockSucursal;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Stock extends Component
{
    use WithPagination;

    public string $busqueda = '';

    public bool $soloConStock = false;

    public bool $soloCriticos = false;

    // Modal de desglose
    public ?int $detalleProductoId = null;

    public ?string $detalleProductoNombre = null;

    public ?string $detalleProductoCodigo = null;

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatingSoloConStock(): void
    {
        $this->resetPage();
    }

    public function updatingSoloCriticos(): void
    {
        $this->resetPage();
    }

    public function abrirDetalle(int $productId, string $nombre, ?string $codigo = null): void
    {
        $this->detalleProductoId = $productId;
        $this->detalleProductoNombre = $nombre;
        $this->detalleProductoCodigo = $codigo;
    }

    public function cerrarDetalle(): void
    {
        $this->detalleProductoId = null;
        $this->detalleProductoNombre = null;
        $this->detalleProductoCodigo = null;
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        // El total sale de sumar stock_sucursal, no de products.stock, para que el número
        // de la tabla y el desglose del modal no puedan contradecirse.
        $query = Product::query()
            ->select('products.id', 'products.nombre', 'products.codigo_interno', 'products.stock_critico')
            ->selectSub(
                StockSucursal::query()
                    ->selectRaw('COALESCE(SUM(cantidad), 0)')
                    ->whereColumn('stock_sucursal.product_id', 'products.id'),
                'stock_total'
            )
            ->selectSub(
                StockSucursal::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('stock_sucursal.product_id', 'products.id')
                    ->where('cantidad', '>', 0),
                'sucursales_con_stock'
            );

        if ($this->busqueda) {
            $query->where(function ($q) {
                $q->where('products.nombre', 'like', '%'.$this->busqueda.'%')
                    ->orWhere('products.codigo_interno', 'like', '%'.$this->busqueda.'%')
                    ->orWhere('products.codigo_barras', 'like', '%'.$this->busqueda.'%');
            });
        }

        if ($this->soloConStock) {
            $query->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('stock_sucursal')
                ->whereColumn('stock_sucursal.product_id', 'products.id')
                ->where('cantidad', '>', 0));
        }

        if ($this->soloCriticos) {
            $query->havingRaw('stock_total > 0 AND stock_total <= products.stock_critico');
        }

        $productos = $query->orderByDesc('stock_total')->orderBy('products.nombre')->paginate(20);

        $unidadesTotales = StockSucursal::sum('cantidad');

        $productosConStock = StockSucursal::where('cantidad', '>', 0)
            ->distinct()
            ->count('product_id');

        $detalleSucursales = $this->detalleProductoId
            ? StockSucursal::with('sucursal')
                ->where('product_id', $this->detalleProductoId)
                ->orderByDesc('cantidad')
                ->get()
            : collect();

        $remitosEnTransito = $this->detalleProductoId
            ? RemitoDetalle::with('remito.sucursalDestino')
                ->where('product_id', $this->detalleProductoId)
                ->whereHas('remito', fn ($q) => $q->where('estado', EstadoRemito::Remitido))
                ->get()
                ->groupBy(fn ($d) => $d->remito->sucursal_destino_id)
                ->map(fn ($detalles) => (object) [
                    'sucursal' => $detalles->first()->remito->sucursalDestino,
                    'cantidad' => $detalles->sum('cantidad'),
                ])
                ->values()
            : collect();

        return view('livewire.products.stock', [
            'productos' => $productos,
            'unidadesTotales' => $unidadesTotales,
            'productosConStock' => $productosConStock,
            'detalleSucursales' => $detalleSucursales,
            'remitosEnTransito' => $remitosEnTransito,
        ]);
    }
}
