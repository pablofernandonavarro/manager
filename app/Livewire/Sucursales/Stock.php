<?php

namespace App\Livewire\Sucursales;

use App\Enums\EstadoRemito;
use App\Models\Product;
use App\Models\Remito;
use App\Models\RemitoDetalle;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Stock extends Component
{
    use WithPagination;

    public ?int $sucursalSeleccionada = null;

    public string $busqueda = '';

    // Modal desglose por sucursal
    public ?int $detalleProductoId = null;

    public ?string $detalleProductoNombre = null;

    // Modal remito (envío desde Central)
    public ?int $remitoProductoId = null;

    public ?string $remitoProductoNombre = null;

    public int $remitoStockDisponible = 0;

    /** @var array<int, int> */
    public array $remitoCantidades = [];

    public ?string $remitoError = null;

    public function mount(): void
    {
        $this->sucursalSeleccionada = Sucursal::where('activo', true)
            ->orderByDesc('is_central')
            ->first()?->id;
    }

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatingSucursalSeleccionada(): void
    {
        $this->resetPage();
    }

    public function abrirDetalle(int $productId, string $nombre): void
    {
        $this->detalleProductoId = $productId;
        $this->detalleProductoNombre = $nombre;
    }

    public function cerrarDetalle(): void
    {
        $this->detalleProductoId = null;
        $this->detalleProductoNombre = null;
    }

    public function abrirRemito(int $productId, string $nombre, int $stockDisponible): void
    {
        $this->remitoProductoId = $productId;
        $this->remitoProductoNombre = $nombre;
        $this->remitoStockDisponible = $stockDisponible;
        $this->remitoCantidades = [];
        $this->remitoError = null;
    }

    public function cerrarRemito(): void
    {
        $this->remitoProductoId = null;
        $this->remitoProductoNombre = null;
        $this->remitoStockDisponible = 0;
        $this->remitoCantidades = [];
        $this->remitoError = null;
    }

    public function confirmarRemito(): void
    {
        $this->remitoError = null;

        $cantidades = collect($this->remitoCantidades)
            ->filter(fn ($v) => (int) $v > 0)
            ->map(fn ($v) => (int) $v);

        if ($cantidades->isEmpty()) {
            $this->remitoError = 'Ingresá al menos una cantidad mayor a 0.';

            return;
        }

        $totalAEnviar = $cantidades->sum();

        if ($totalAEnviar > $this->remitoStockDisponible) {
            $this->remitoError = "Total a enviar ({$totalAEnviar}) supera el stock disponible en Central ({$this->remitoStockDisponible}).";

            return;
        }

        $centralId = $this->sucursalSeleccionada;

        DB::transaction(function () use ($cantidades, $totalAEnviar, $centralId) {
            // Descontar de Central
            StockSucursal::where('sucursal_id', $centralId)
                ->where('product_id', $this->remitoProductoId)
                ->decrement('cantidad', $totalAEnviar);

            // Crear un remito por cada sucursal destino con cantidad > 0
            foreach ($cantidades as $sucursalDestinoId => $cantidad) {
                $remito = Remito::create([
                    'sucursal_origen_id' => $centralId,
                    'sucursal_destino_id' => $sucursalDestinoId,
                    'user_id' => auth()->id(),
                    'estado' => EstadoRemito::Remitido,
                    'remitido_at' => now(),
                ]);

                $remito->detalles()->create([
                    'product_id' => $this->remitoProductoId,
                    'cantidad' => $cantidad,
                ]);
            }
        });

        $this->cerrarRemito();
        session()->flash('success', 'Remito(s) creado(s) correctamente. La sucursal debe confirmar la recepción.');
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $sucursales = Sucursal::where('activo', true)
            ->orderByDesc('is_central')
            ->orderBy('nombre')
            ->get();

        $sucursalActual = $sucursales->firstWhere('id', $this->sucursalSeleccionada);
        $esCentral = $sucursalActual?->isCentral() ?? false;

        $sucursalesDestino = $esCentral
            ? $sucursales->where('is_central', false)->values()
            : collect();

        $query = Product::query()
            ->select('products.*')
            ->leftJoin('stock_sucursal', function ($join) {
                $join->on('products.id', '=', 'stock_sucursal.product_id')
                    ->where('stock_sucursal.sucursal_id', '=', $this->sucursalSeleccionada);
            })
            ->selectRaw('COALESCE(stock_sucursal.cantidad, 0) as stock_sucursal');

        if ($this->busqueda) {
            $query->where(function ($q) {
                $q->where('products.nombre', 'like', '%'.$this->busqueda.'%')
                    ->orWhere('products.codigo_interno', 'like', '%'.$this->busqueda.'%')
                    ->orWhere('products.codigo_barras', 'like', '%'.$this->busqueda.'%');
            });
        }

        $productos = $query->orderBy('products.nombre')->paginate(20);

        $stockTotal = $this->sucursalSeleccionada
            ? StockSucursal::where('sucursal_id', $this->sucursalSeleccionada)->sum('cantidad')
            : 0;

        $productosConStock = $this->sucursalSeleccionada
            ? StockSucursal::where('sucursal_id', $this->sucursalSeleccionada)
                ->where('cantidad', '>', 0)
                ->count()
            : 0;

        $detalleSucursales = $this->detalleProductoId
            ? StockSucursal::with('sucursal')
                ->where('product_id', $this->detalleProductoId)
                ->where('cantidad', '>', 0)
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

        // Stock actual por sucursal para el modal de remito
        $stockPorSucursal = $this->remitoProductoId
            ? StockSucursal::where('product_id', $this->remitoProductoId)
                ->pluck('cantidad', 'sucursal_id')
            : collect();

        // Unidades ya en tránsito (remitidas pero no confirmadas) por sucursal destino
        $transitoPorSucursal = $this->remitoProductoId
            ? RemitoDetalle::where('product_id', $this->remitoProductoId)
                ->whereHas('remito', fn ($q) => $q->where('estado', EstadoRemito::Remitido))
                ->join('remitos', 'remito_detalles.remito_id', '=', 'remitos.id')
                ->groupBy('remitos.sucursal_destino_id')
                ->selectRaw('remitos.sucursal_destino_id, SUM(remito_detalles.cantidad) as total')
                ->pluck('total', 'sucursal_destino_id')
            : collect();

        return view('livewire.sucursales.stock', [
            'sucursales' => $sucursales,
            'sucursalesDestino' => $sucursalesDestino,
            'esCentral' => $esCentral,
            'productos' => $productos,
            'stockTotal' => $stockTotal,
            'productosConStock' => $productosConStock,
            'detalleSucursales' => $detalleSucursales,
            'remitosEnTransito' => $remitosEnTransito,
            'stockPorSucursal' => $stockPorSucursal,
            'transitoPorSucursal' => $transitoPorSucursal,
        ]);
    }
}
