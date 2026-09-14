<?php

namespace App\Livewire\Ventas;

use App\Models\DetalleVenta;
use App\Models\Sucursal;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class PorArticulo extends Component
{
    use WithPagination;

    public string $busqueda = '';

    public ?int $sucursalSeleccionada = null;

    public string $desde = '';

    public string $hasta = '';

    // Modal de desglose
    public ?int $detalleProductoId = null;

    public ?string $detalleProductoNombre = null;

    public ?string $detalleProductoCodigo = null;

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatingSucursalSeleccionada(): void
    {
        $this->resetPage();
    }

    public function updatingDesde(): void
    {
        $this->resetPage();
    }

    public function updatingHasta(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['busqueda', 'sucursalSeleccionada', 'desde', 'hasta']);
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

    /**
     * Los filtros se aplican sobre ventas, no sobre detalle_ventas, porque la sucursal
     * y la fecha viven en la cabecera de la venta.
     */
    private function aplicarFiltros(Builder $query): Builder
    {
        if ($this->sucursalSeleccionada) {
            $query->where('ventas.sucursal_id', $this->sucursalSeleccionada);
        }

        if ($this->desde) {
            $query->whereDate('ventas.fecha', '>=', $this->desde);
        }

        if ($this->hasta) {
            $query->whereDate('ventas.fecha', '<=', $this->hasta);
        }

        return $query;
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $query = DetalleVenta::query()
            ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('products', 'detalle_ventas.product_id', '=', 'products.id')
            ->select('products.id', 'products.nombre', 'products.codigo_interno')
            ->selectRaw('SUM(detalle_ventas.cantidad) as unidades')
            ->selectRaw('SUM(detalle_ventas.subtotal) as facturado')
            ->selectRaw('COUNT(DISTINCT ventas.id) as cantidad_ventas')
            ->selectRaw('COUNT(DISTINCT ventas.sucursal_id) as cantidad_sucursales')
            ->selectRaw('MAX(ventas.fecha) as ultima_venta')
            ->groupBy('products.id', 'products.nombre', 'products.codigo_interno');

        $this->aplicarFiltros($query);

        if ($this->busqueda) {
            $query->where(function ($q) {
                $q->where('products.nombre', 'like', '%'.$this->busqueda.'%')
                    ->orWhere('products.codigo_interno', 'like', '%'.$this->busqueda.'%')
                    ->orWhere('products.codigo_barras', 'like', '%'.$this->busqueda.'%');
            });
        }

        $articulos = $query->orderByDesc('unidades')->paginate(20);

        // Totales generales (mismos filtros, sin el agrupamiento por producto)
        $totales = $this->aplicarFiltros(
            DetalleVenta::query()->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
        )->selectRaw('COALESCE(SUM(detalle_ventas.cantidad), 0) as unidades')
            ->selectRaw('COALESCE(SUM(detalle_ventas.subtotal), 0) as facturado')
            ->selectRaw('COUNT(DISTINCT ventas.id) as ventas')
            ->first();

        // Desglose del modal: cada línea de venta del artículo, con dónde ocurrió.
        $lineas = collect();

        if ($this->detalleProductoId) {
            $lineasQuery = DetalleVenta::query()
                ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
                ->leftJoin('sucursales', 'ventas.sucursal_id', '=', 'sucursales.id')
                ->leftJoin('puntos_de_venta', 'ventas.punto_de_venta_id', '=', 'puntos_de_venta.id')
                ->where('detalle_ventas.product_id', $this->detalleProductoId)
                ->select(
                    'detalle_ventas.cantidad',
                    'detalle_ventas.precio_unitario',
                    'detalle_ventas.subtotal',
                    'ventas.numero_venta',
                    'ventas.fecha',
                    'ventas.total as venta_total',
                    'sucursales.nombre as sucursal',
                    'puntos_de_venta.nombre as punto_de_venta',
                );

            $lineas = $this->aplicarFiltros($lineasQuery)
                ->orderByDesc('ventas.fecha')
                ->get();
        }

        return view('livewire.ventas.por-articulo', [
            'articulos' => $articulos,
            'totales' => $totales,
            'lineas' => $lineas,
            'sucursales' => Sucursal::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }
}
