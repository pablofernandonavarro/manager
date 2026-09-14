<?php

namespace App\Livewire\Cajas;

use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Turnos de caja de todas las sucursales: los abiertos en vivo y los cerrados con su Z.
 * Solo lectura: el turno lo arma y lo cierra la caja.
 */
class Cierres extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public ?int $sucursal = null;

    #[Url]
    public ?int $caja = null;

    #[Url]
    public string $estado = '';

    #[Url]
    public ?string $desde = null;

    #[Url]
    public ?string $hasta = null;

    public ?int $detalleId = null;

    public function mount(): void
    {
        $this->authorize('cajas.ver');

        $this->desde ??= now()->subDays(30)->toDateString();
    }

    public function updating(string $propiedad): void
    {
        if (in_array($propiedad, ['sucursal', 'caja', 'estado', 'desde', 'hasta'], true)) {
            $this->resetPage();
        }

        if ($propiedad === 'sucursal') {
            $this->caja = null;
        }
    }

    public function verDetalle(int $id): void
    {
        $this->authorize('cajas.ver');

        $this->detalleId = $id;
    }

    public function cerrarDetalle(): void
    {
        $this->detalleId = null;
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $turnos = TurnoCaja::with(['sucursal', 'puntoDeVenta'])
            ->when($this->sucursal, fn ($q) => $q->where('sucursal_id', $this->sucursal))
            ->when($this->caja, fn ($q) => $q->where('punto_de_venta_id', $this->caja))
            ->when($this->estado, fn ($q) => $q->where('estado', $this->estado))
            ->when($this->desde, fn ($q) => $q->whereDate('abierto_at', '>=', $this->desde))
            ->when($this->hasta, fn ($q) => $q->whereDate('abierto_at', '<=', $this->hasta))
            ->orderByDesc('abierto_at')
            ->paginate(25);

        return view('livewire.cajas.cierres', [
            'turnos' => $turnos,
            'sucursales' => Sucursal::orderBy('nombre')->get(),
            'cajas' => PuntoDeVenta::when($this->sucursal, fn ($q) => $q->where('sucursal_id', $this->sucursal))->orderBy('nombre')->get(),
            'detalle' => $this->detalleId
                ? TurnoCaja::with(['sucursal', 'puntoDeVenta', 'movimientos'])->find($this->detalleId)
                : null,
        ]);
    }
}
