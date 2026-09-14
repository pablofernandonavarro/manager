<?php

namespace App\Livewire\Reportes;

use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Services\ReporteVentasService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

class Ventas extends Component
{
    use AuthorizesRequests;

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    #[Url]
    public ?int $sucursal = null;

    #[Url]
    public ?int $caja = null;

    #[Url]
    public string $cajero = '';

    public function mount(): void
    {
        $this->authorize('reportes.ver');

        $hoy = now(config('app.display_timezone'));
        $this->desde = $this->desde ?: $hoy->copy()->startOfMonth()->toDateString();
        $this->hasta = $this->hasta ?: $hoy->toDateString();
    }

    public function updatingSucursal(): void
    {
        $this->caja = null;
    }

    public function rapido(string $periodo): void
    {
        $hoy = now(config('app.display_timezone'));

        [$this->desde, $this->hasta] = match ($periodo) {
            'hoy' => [$hoy->toDateString(), $hoy->toDateString()],
            'ayer' => [$hoy->copy()->subDay()->toDateString(), $hoy->copy()->subDay()->toDateString()],
            '7dias' => [$hoy->copy()->subDays(6)->toDateString(), $hoy->toDateString()],
            'mes' => [$hoy->copy()->startOfMonth()->toDateString(), $hoy->toDateString()],
            'mes_anterior' => [$hoy->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $hoy->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            default => [$this->desde, $this->hasta],
        };
    }

    /** @return array<string, mixed> */
    public function filtros(): array
    {
        $desde = $this->fechaValida($this->desde) ?? now(config('app.display_timezone'))->toDateString();
        $hasta = $this->fechaValida($this->hasta) ?? $desde;

        if ($hasta < $desde) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'sucursal_id' => $this->sucursal,
            'punto_de_venta_id' => $this->caja,
            'cajero' => $this->cajero !== '' ? $this->cajero : null,
        ];
    }

    private function fechaValida(string $fecha): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) && strtotime($fecha) ? $fecha : null;
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $reportes = app(ReporteVentasService::class);
        $filtros = $this->filtros();

        return view('livewire.reportes.ventas', [
            'filtros' => $filtros,
            'indicadores' => $reportes->indicadores($filtros),
            'porMedio' => $reportes->porMedioDePago($filtros),
            'porCajero' => $reportes->porCajero($filtros),
            'porCaja' => $reportes->porCaja($filtros),
            'porDia' => $reportes->porDia($filtros),
            'tarjetas' => $reportes->tarjetas($filtros),
            'promociones' => $reportes->promociones($filtros),
            'sucursales' => Sucursal::orderBy('nombre')->get(['id', 'nombre']),
            'cajas' => PuntoDeVenta::when($this->sucursal, fn ($q) => $q->where('sucursal_id', $this->sucursal))->orderBy('nombre')->get(['id', 'nombre']),
            'cajeros' => Venta::whereNotNull('cajero')->distinct()->orderBy('cajero')->pluck('cajero'),
        ]);
    }
}
