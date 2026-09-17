<?php

namespace App\Livewire\Sucursales;

use App\Enums\EstadoRemito;
use App\Exceptions\RemitoException;
use App\Models\ConfiguracionRemitos;
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

    public ?int $remitoRecibiendo = null;

    /** @var array<int, int> */
    public array $cantidadesRecibidas = [];

    public ?int $destinoRechazadosElegido = null;

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

    public function abrirModalRecepcion(int $remitoId): void
    {
        $this->authorize('remitos.recibir');

        $remito = Remito::with('detalles')->findOrFail($remitoId);
        $this->remitoRecibiendo = $remitoId;
        $this->cantidadesRecibidas = $remito->detalles->mapWithKeys(
            fn ($d) => [$d->product_id => $d->cantidad]
        )->toArray();
        $this->destinoRechazadosElegido = null;
    }

    public function cerrarModal(): void
    {
        $this->remitoRecibiendo = null;
        $this->cantidadesRecibidas = [];
        $this->destinoRechazadosElegido = null;
    }

    public function confirmarRecepcionParcial(RemitoService $remitos): void
    {
        $this->authorize('remitos.recibir');

        if (! $this->remitoRecibiendo) {
            return;
        }

        $this->ejecutar(
            function () use ($remitos) {
                $remito = Remito::findOrFail($this->remitoRecibiendo);

                return $remitos->confirmar(
                    $remito,
                    auth()->user(),
                    null,
                    $this->cantidadesRecibidas,
                    $this->destinoRechazadosElegido,
                );
            },
            function (Remito $r) {
                $msg = "Remito #{$r->id} recibido. El stock se acreditó en {$r->sucursalDestino->nombre}.";
                if ($hijo = $r->hijos()->latest('id')->first()) {
                    $msg .= " Remito hijo #{$hijo->id} hacia {$hijo->sucursalDestino->nombre} por mercadería rechazada.";
                }

                return $msg;
            }
        );

        $this->cerrarModal();
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

        $remitos = Remito::with(['sucursalOrigen', 'sucursalDestino', 'detalles.product', 'user', 'confirmadoPorCaja', 'confirmadoPorUsuario', 'creadoPorCaja'])
            ->where($columna, $this->sucursalSeleccionada)
            ->when($this->filtroEstado, fn ($q) => $q->where('estado', $this->filtroEstado))
            ->orderByDesc('remitido_at')
            ->paginate(20);

        $pendientesDeRecibir = Remito::where('sucursal_destino_id', $this->sucursalSeleccionada)
            ->where('estado', EstadoRemito::Remitido)
            ->count();

        $remitoModalActual = $this->remitoRecibiendo
            ? Remito::with(['detalles.product', 'sucursalOrigen', 'sucursalDestino'])->find($this->remitoRecibiendo)
            : null;

        $config = ConfiguracionRemitos::actual();

        return view('livewire.sucursales.remitos', [
            'sucursales' => $sucursales,
            'remitos' => $remitos,
            'pendientes' => $pendientesDeRecibir,
            'remitoModal' => $remitoModalActual,
            'config' => $config,
        ]);
    }
}
