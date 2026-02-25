<?php

namespace App\Livewire\Sucursales;

use App\Enums\EstadoRemito;
use App\Models\Remito;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Remitos extends Component
{
    use WithPagination;

    public ?int $sucursalSeleccionada = null;

    public string $filtroEstado = 'remitido';

    public function mount(): void
    {
        // Seleccionar la primera sucursal NO central por defecto
        $this->sucursalSeleccionada = Sucursal::where('activo', true)
            ->where('is_central', false)
            ->orderBy('nombre')
            ->first()?->id;
    }

    public function updatingSucursalSeleccionada(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function confirmarRecepcion(int $remitoId): void
    {
        $remito = Remito::with('detalles')->findOrFail($remitoId);

        if ($remito->sucursal_destino_id !== $this->sucursalSeleccionada) {
            return;
        }

        if ($remito->estado !== EstadoRemito::Remitido) {
            return;
        }

        DB::transaction(function () use ($remito) {
            foreach ($remito->detalles as $detalle) {
                $stock = StockSucursal::firstOrCreate(
                    ['sucursal_id' => $remito->sucursal_destino_id, 'product_id' => $detalle->product_id],
                    ['cantidad' => 0]
                );
                $stock->increment('cantidad', $detalle->cantidad);

                // Sincronizar products.stock (suma total)
                $totalGlobal = StockSucursal::where('product_id', $detalle->product_id)->sum('cantidad');
                \App\Models\Product::where('id', $detalle->product_id)->update(['stock' => $totalGlobal]);
            }

            $remito->update([
                'estado' => EstadoRemito::Confirmado,
                'confirmado_at' => now(),
            ]);
        });

        session()->flash('success', 'Recepción confirmada. El stock fue acreditado a la sucursal.');
    }

    public function cancelarRemito(int $remitoId): void
    {
        $remito = Remito::with('detalles')->findOrFail($remitoId);

        if ($remito->estado !== EstadoRemito::Remitido) {
            return;
        }

        DB::transaction(function () use ($remito) {
            // Devolver el stock a Central
            foreach ($remito->detalles as $detalle) {
                StockSucursal::where('sucursal_id', $remito->sucursal_origen_id)
                    ->where('product_id', $detalle->product_id)
                    ->increment('cantidad', $detalle->cantidad);
            }

            $remito->update([
                'estado' => EstadoRemito::Cancelado,
            ]);
        });

        session()->flash('success', 'Remito cancelado. El stock fue devuelto a Central.');
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $sucursales = Sucursal::where('activo', true)
            ->where('is_central', false)
            ->orderBy('nombre')
            ->get();

        $remitos = Remito::with(['sucursalOrigen', 'detalles.product'])
            ->where('sucursal_destino_id', $this->sucursalSeleccionada)
            ->when($this->filtroEstado, fn ($q) => $q->where('estado', $this->filtroEstado))
            ->orderByDesc('remitido_at')
            ->paginate(20);

        $pendientes = Remito::where('sucursal_destino_id', $this->sucursalSeleccionada)
            ->where('estado', EstadoRemito::Remitido)
            ->count();

        return view('livewire.sucursales.remitos', [
            'sucursales' => $sucursales,
            'remitos' => $remitos,
            'pendientes' => $pendientes,
        ]);
    }
}
