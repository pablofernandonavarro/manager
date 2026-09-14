<?php

namespace App\Livewire\Clientes;

use App\Models\Cliente;
use App\Models\Comprobante;
use App\Support\Cuit;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Clientes: datos para facturar y cuenta corriente. Bajan a las cajas con el saldo.
 */
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $buscar = '';

    #[Url]
    public bool $soloConDeuda = false;

    public bool $modalAbierto = false;

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $condicionIva = '5';

    public string $documento = '';

    public string $email = '';

    public string $telefono = '';

    public string $domicilio = '';

    public bool $cuentaCorriente = false;

    public string $limiteCredito = '';

    public bool $activo = true;

    public string $observaciones = '';

    public function mount(): void
    {
        $this->authorize('clientes.gestionar');
    }

    public function updating(string $propiedad): void
    {
        if (in_array($propiedad, ['buscar', 'soloConDeuda'], true)) {
            $this->resetPage();
        }
    }

    public function crear(): void
    {
        $this->authorize('clientes.gestionar');
        $this->resetFormulario();
        $this->modalAbierto = true;
    }

    public function editar(int $id): void
    {
        $this->authorize('clientes.gestionar');

        $c = Cliente::findOrFail($id);
        $this->resetFormulario();
        $this->editandoId = $c->id;
        $this->nombre = $c->nombre;
        $this->condicionIva = (string) $c->condicion_iva;
        $this->documento = $c->documentoFormateado() ?? '';
        $this->email = (string) $c->email;
        $this->telefono = (string) $c->telefono;
        $this->domicilio = (string) $c->domicilio;
        $this->cuentaCorriente = $c->cuenta_corriente;
        $this->limiteCredito = $c->limite_credito !== null ? (string) (float) $c->limite_credito : '';
        $this->activo = $c->activo;
        $this->observaciones = (string) $c->observaciones;
        $this->modalAbierto = true;
    }

    public function guardar(): void
    {
        $this->authorize('clientes.gestionar');

        $documento = Cuit::normalizar($this->documento);
        $docTipo = match (true) {
            $documento === '' => 99,
            strlen($documento) === 11 => 80,
            default => 96,
        };

        $this->validate([
            'nombre' => 'required|string|max:150',
            'condicionIva' => ['required', Rule::in(array_map('strval', array_keys(Comprobante::CONDICIONES_IVA)))],
            'documento' => [
                'nullable', 'string', 'max:20',
                function ($atributo, $valor, $fallar) use ($documento, $docTipo) {
                    if ($docTipo === 80 && ! Cuit::valido($documento)) {
                        $fallar('El CUIT no es válido.');
                    } elseif ($docTipo === 96 && ! preg_match('/^\d{6,8}$/', $documento)) {
                        $fallar('Un DNI tiene 7 u 8 números; un CUIT, 11.');
                    } elseif ($this->condicionIva !== '5' && $docTipo !== 80) {
                        $fallar('Un cliente que no es consumidor final necesita CUIT.');
                    } elseif ($docTipo !== 99 && Cliente::where('doc_tipo', $docTipo)->where('documento', $documento)->whereKeyNot($this->editandoId)->exists()) {
                        $fallar('Ya hay un cliente con ese documento.');
                    }
                },
            ],
            'email' => 'nullable|email|max:150',
            'telefono' => 'nullable|string|max:50',
            'domicilio' => 'nullable|string|max:200',
            'cuentaCorriente' => 'boolean',
            'limiteCredito' => 'nullable|numeric|min:0|max:999999999',
            'activo' => 'boolean',
            'observaciones' => 'nullable|string|max:1000',
        ], [], ['condicionIva' => 'condición frente al IVA', 'limiteCredito' => 'límite de crédito']);

        $atributos = [
            'nombre' => trim($this->nombre),
            'condicion_iva' => (int) $this->condicionIva,
            'doc_tipo' => $docTipo,
            'documento' => $docTipo === 99 ? null : $documento,
            'email' => trim($this->email) ?: null,
            'telefono' => trim($this->telefono) ?: null,
            'domicilio' => trim($this->domicilio) ?: null,
            'cuenta_corriente' => $this->cuentaCorriente,
            'limite_credito' => $this->cuentaCorriente && $this->limiteCredito !== '' ? $this->limiteCredito : null,
            'activo' => $this->activo,
            'observaciones' => trim($this->observaciones) ?: null,
        ];

        $this->editandoId
            ? Cliente::findOrFail($this->editandoId)->update($atributos)
            : Cliente::create($atributos);

        $this->modalAbierto = false;
        session()->flash('success', 'Cliente guardado. Las cajas lo reciben en el próximo minuto.');
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
    }

    private function resetFormulario(): void
    {
        $this->reset(['editandoId', 'nombre', 'condicionIva', 'documento', 'email', 'telefono', 'domicilio', 'cuentaCorriente', 'limiteCredito', 'activo', 'observaciones']);
        $this->resetErrorBag();
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $clientes = Cliente::query()
            ->withSum('movimientos as saldo', 'importe')
            ->when(trim($this->buscar) !== '', fn ($q) => $q->buscar($this->buscar))
            ->when($this->soloConDeuda, fn ($q) => $q->whereHas('movimientos')->having('saldo', '>', 0))
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->paginate(25);

        return view('livewire.clientes.index', [
            'clientes' => $clientes,
            'deudaTotal' => (float) \App\Models\MovimientoCuentaCorriente::sum('importe'),
        ]);
    }
}
