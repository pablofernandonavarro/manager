<?php

namespace App\Livewire\Clientes;

use App\Exceptions\CuentaCorrienteException;
use App\Models\Cliente;
use App\Services\CuentaCorrienteService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Estado de cuenta de un cliente: movimientos con saldo acumulado, y pagos o ajustes
 * cargados desde el Manager.
 */
class CuentaCorriente extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Locked]
    public int $clienteId;

    public string $tipo = 'pago';

    public string $importe = '';

    public string $medio = 'efectivo';

    public string $descripcion = '';

    public ?string $mensaje = null;

    public ?string $error = null;

    public function mount(Cliente $cliente): void
    {
        $this->authorize('clientes.gestionar');
        $this->clienteId = $cliente->id;
    }

    public function registrar(CuentaCorrienteService $cuentas): void
    {
        $this->authorize('clientes.cuenta_corriente');
        $this->mensaje = $this->error = null;

        try {
            $cuentas->registrarManual(
                Cliente::findOrFail($this->clienteId),
                $this->tipo,
                (float) str_replace(',', '.', $this->importe),
                $this->medio,
                $this->descripcion,
                auth()->user()
            );
        } catch (CuentaCorrienteException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->mensaje = $this->tipo === 'pago' ? 'Pago registrado.' : 'Ajuste registrado.';
        $this->reset(['importe', 'descripcion']);
        $this->resetPage();
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $cliente = Cliente::findOrFail($this->clienteId);
        $movimientos = $cliente->movimientos()->with(['puntoDeVenta', 'user'])->orderByDesc('fecha')->orderByDesc('id')->paginate(30);

        // Saldo después de cada movimiento: el total menos lo posterior a él.
        $saldo = $cliente->saldo();
        $posteriores = (float) $cliente->movimientos()
            ->where(fn ($q) => $q->where('fecha', '>', $movimientos->first()?->fecha ?? now())
                ->orWhere(fn ($w) => $w->where('fecha', $movimientos->first()?->fecha)->where('id', '>', $movimientos->first()?->id ?? 0)))
            ->sum('importe');
        $acumulado = $saldo - $posteriores;
        $saldos = [];

        foreach ($movimientos as $m) {
            $saldos[$m->id] = round($acumulado, 2);
            $acumulado -= (float) $m->importe;
        }

        return view('livewire.clientes.cuenta-corriente', [
            'cliente' => $cliente,
            'saldo' => $saldo,
            'movimientos' => $movimientos,
            'saldos' => $saldos,
        ]);
    }
}
