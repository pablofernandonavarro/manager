<?php

namespace App\Livewire\Cajas;

use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use Carbon\Carbon;
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

    /**
     * Convierte un día local (Y-m-d) al instante UTC con que se guardan las fechas, o null
     * si el texto no es una fecha válida (viene de la URL y no se puede dar por bueno).
     */
    private function diaLocalEnUtc(?string $fecha, bool $finDelDia): ?Carbon
    {
        if (! $fecha || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || ! strtotime($fecha)) {
            return null;
        }

        $dia = Carbon::parse($fecha, config('app.display_timezone'));

        return ($finDelDia ? $dia->endOfDay() : $dia->startOfDay())->utc();
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $desde = $this->diaLocalEnUtc($this->desde, false);
        $hasta = $this->diaLocalEnUtc($this->hasta, true);

        $turnos = TurnoCaja::with(['sucursal', 'puntoDeVenta'])
            ->when($this->sucursal, fn ($q) => $q->where('sucursal_id', $this->sucursal))
            ->when($this->caja, fn ($q) => $q->where('punto_de_venta_id', $this->caja))
            ->when($this->estado, fn ($q) => $q->where('estado', $this->estado))
            ->when($desde, fn ($q) => $q->where('abierto_at', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('abierto_at', '<=', $hasta))
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
