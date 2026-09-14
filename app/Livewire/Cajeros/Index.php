<?php

namespace App\Livewire\Cajeros;

use App\Models\Cajero;
use App\Models\Sucursal;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Index extends Component
{
    use AuthorizesRequests;

    public bool $modalAbierto = false;

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $rol = 'cajero';

    public string $pin = '';

    /** @var array<int, int|string> */
    public array $sucursales = [];

    public bool $activo = true;

    public function mount(): void
    {
        $this->authorize('cajeros.gestionar');
    }

    public function crear(): void
    {
        $this->authorize('cajeros.gestionar');

        $this->resetFormulario();
        $this->modalAbierto = true;
    }

    public function editar(int $id): void
    {
        $this->authorize('cajeros.gestionar');

        $cajero = Cajero::findOrFail($id);

        $this->resetFormulario();
        $this->editandoId = $cajero->id;
        $this->nombre = $cajero->nombre;
        $this->rol = $cajero->rol;
        $this->sucursales = $cajero->sucursales ?? [];
        $this->activo = $cajero->activo;
        $this->modalAbierto = true;
    }

    public function guardar(): void
    {
        $this->authorize('cajeros.gestionar');

        $datos = $this->validate([
            'nombre' => 'required|string|max:100',
            'rol' => ['required', Rule::in(array_keys(Cajero::ROLES))],
            // Al editar, vacío = mantener el PIN actual.
            'pin' => [$this->editandoId ? 'nullable' : 'required', 'digits_between:4,6'],
            'sucursales' => 'array',
            'sucursales.*' => 'integer|exists:sucursales,id',
            'activo' => 'boolean',
        ], [
            'pin.required' => 'El PIN es obligatorio.',
            'pin.digits_between' => 'El PIN tiene que tener entre 4 y 6 números.',
        ]);

        $atributos = [
            'nombre' => trim($datos['nombre']),
            'rol' => $datos['rol'],
            'sucursales' => $datos['sucursales'] === [] ? null : array_map('intval', array_values($datos['sucursales'])),
            'activo' => $datos['activo'],
        ];

        if (($datos['pin'] ?? '') !== '') {
            $atributos['pin_hash'] = Hash::make($datos['pin']);
        }

        $this->editandoId
            ? Cajero::findOrFail($this->editandoId)->update($atributos)
            : Cajero::create($atributos);

        $this->modalAbierto = false;
        $this->pin = '';
        session()->flash('success', 'Cajero guardado. Las cajas lo reciben en el próximo minuto.');
    }

    public function alternarActivo(int $id): void
    {
        $this->authorize('cajeros.gestionar');

        $cajero = Cajero::findOrFail($id);
        $cajero->update(['activo' => ! $cajero->activo]);
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->pin = '';
    }

    private function resetFormulario(): void
    {
        $this->reset(['editandoId', 'nombre', 'rol', 'pin', 'sucursales', 'activo']);
        $this->resetErrorBag();
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.cajeros.index', [
            'cajeros' => Cajero::orderByDesc('activo')->orderBy('nombre')->get(),
            'listaSucursales' => Sucursal::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }
}
