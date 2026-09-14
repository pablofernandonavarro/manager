<?php

namespace App\Livewire\Facturacion;

use App\Jobs\AutorizarComprobante;
use App\Models\Comprobante;
use App\Models\Sucursal;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Facturas y notas de crédito emitidas por las cajas, con su estado ante AFIP.
 */
class Comprobantes extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $estado = '';

    #[Url]
    public ?int $sucursal = null;

    #[Url]
    public ?string $desde = null;

    #[Url]
    public ?string $hasta = null;

    public ?string $mensaje = null;

    public function mount(): void
    {
        $this->authorize('facturacion.ver');

        $this->desde ??= now(config('app.display_timezone'))->subDays(30)->toDateString();
    }

    public function updating(string $propiedad): void
    {
        if (in_array($propiedad, ['estado', 'sucursal', 'desde', 'hasta'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Vuelve a pedir el CAE. Un rechazo se reintenta solo desde acá (por ejemplo, después
     * de corregir la configuración del emisor o el punto de venta en AFIP).
     */
    public function reintentar(int $id): void
    {
        $this->authorize('facturacion.configurar');

        $comprobante = Comprobante::findOrFail($id);

        if ($comprobante->estaAutorizado()) {
            return;
        }

        $comprobante->update(['estado' => 'pendiente', 'error' => null]);
        AutorizarComprobante::dispatch($comprobante);

        $this->mensaje = "{$comprobante->nombreTipo()} enviado a autorizar.";
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $comprobantes = Comprobante::with(['sucursal', 'puntoDeVenta', 'venta', 'devolucion', 'asociado'])
            ->when($this->estado, fn ($q) => $q->where('estado', $this->estado))
            ->when($this->sucursal, fn ($q) => $q->where('sucursal_id', $this->sucursal))
            ->when($this->desde, fn ($q) => $q->whereDate('fecha', '>=', $this->desde))
            ->when($this->hasta, fn ($q) => $q->whereDate('fecha', '<=', $this->hasta))
            ->orderByDesc('id')
            ->paginate(25);

        return view('livewire.facturacion.comprobantes', [
            'comprobantes' => $comprobantes,
            'sucursales' => Sucursal::orderBy('nombre')->get(),
            'pendientes' => Comprobante::where('estado', 'pendiente')->count(),
            'rechazados' => Comprobante::where('estado', 'rechazado')->count(),
        ]);
    }
}
