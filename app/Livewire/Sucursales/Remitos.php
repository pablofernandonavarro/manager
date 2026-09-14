<?php

namespace App\Livewire\Sucursales;

use App\Enums\EstadoRemito;
use App\Exceptions\RemitoException;
use App\Models\Remito;
use App\Models\Sucursal;
use App\Services\RemitoService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Remitos extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'sucursal')]
    public ?int $sucursalSeleccionada = null;

    /** 'recibidos' (la sucursal es destino) o 'enviados' (es origen). */
    #[Url]
    public string $direccion = 'recibidos';

    #[Url(as: 'estado')]
    public string $filtroEstado = 'remitido';

    public function mount(): void
    {
        $this->authorize('remitos.ver');

        if (! in_array($this->direccion, ['recibidos', 'enviados'], true)) {
            $this->direccion = 'recibidos';
        }

        $this->sucursalSeleccionada ??= Sucursal::where('activo', true)
            ->orderBy('is_central')
            ->orderBy('nombre')
            ->value('id');
    }

    public function updating(string $propiedad): void
    {
        if (in_array($propiedad, ['sucursalSeleccionada', 'direccion', 'filtroEstado'], true)) {
            $this->resetPage();
        }
    }

    public function confirmarRecepcion(int $remitoId, RemitoService $remitos): void
    {
        $this->authorize('remitos.recibir');

        $this->ejecutar(fn () => $remitos->confirmar(Remito::findOrFail($remitoId), auth()->user()),
            fn (Remito $r) => "Remito #{$r->id} recibido. El stock se acreditó en {$r->sucursalDestino->nombre}.");
    }

    public function cancelarRemito(int $remitoId, RemitoService $remitos): void
    {
        $this->authorize('remitos.cancelar');

        $this->ejecutar(fn () => $remitos->cancelar(Remito::findOrFail($remitoId)),
            fn (Remito $r) => "Remito #{$r->id} cancelado. El stock volvió a {$r->sucursalOrigen->nombre}.");
    }

    private function ejecutar(callable $accion, callable $mensaje): void
    {
        try {
            session()->flash('success', $mensaje($accion()));
        } catch (RemitoException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $sucursales = Sucursal::where('activo', true)
            ->orderByDesc('is_central')
            ->orderBy('nombre')
            ->get();

        $columna = $this->direccion === 'enviados' ? 'sucursal_origen_id' : 'sucursal_destino_id';

        $remitos = Remito::with(['sucursalOrigen', 'sucursalDestino', 'detalles.product', 'user', 'confirmadoPorCaja', 'confirmadoPorUsuario'])
            ->where($columna, $this->sucursalSeleccionada)
            ->when($this->filtroEstado, fn ($q) => $q->where('estado', $this->filtroEstado))
            ->orderByDesc('remitido_at')
            ->paginate(20);

        $pendientesDeRecibir = Remito::where('sucursal_destino_id', $this->sucursalSeleccionada)
            ->where('estado', EstadoRemito::Remitido)
            ->count();

        return view('livewire.sucursales.remitos', [
            'sucursales' => $sucursales,
            'remitos' => $remitos,
            'pendientes' => $pendientesDeRecibir,
        ]);
    }
}
