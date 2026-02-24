<?php

namespace App\Livewire\Sucursales;

use App\Models\Sucursal;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Index extends Component
{
    public string $nombre = '';

    public string $direccion = '';

    public ?int $editingId = null;

    public string $editingNombre = '';

    public string $editingDireccion = '';

    public function save(): void
    {
        $this->validate([
            'nombre' => 'required|string|max:100',
            'direccion' => 'nullable|string|max:200',
        ]);

        Sucursal::create([
            'nombre' => $this->nombre,
            'direccion' => $this->direccion ?: null,
            'activo' => true,
        ]);

        $this->nombre = '';
        $this->direccion = '';
    }

    public function startEdit(int $id): void
    {
        $sucursal = Sucursal::findOrFail($id);
        $this->editingId = $id;
        $this->editingNombre = $sucursal->nombre;
        $this->editingDireccion = $sucursal->direccion ?? '';
    }

    public function saveEdit(): void
    {
        $this->validate([
            'editingNombre' => 'required|string|max:100',
            'editingDireccion' => 'nullable|string|max:200',
        ]);

        Sucursal::findOrFail($this->editingId)->update([
            'nombre' => $this->editingNombre,
            'direccion' => $this->editingDireccion ?: null,
        ]);

        $this->editingId = null;
        $this->editingNombre = '';
        $this->editingDireccion = '';
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editingNombre = '';
        $this->editingDireccion = '';
    }

    public function toggleActive(int $id): void
    {
        $sucursal = Sucursal::findOrFail($id);
        $sucursal->update(['activo' => ! $sucursal->activo]);
    }

    public function delete(int $id): void
    {
        Sucursal::findOrFail($id)->delete();
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.sucursales.index', [
            'sucursales' => Sucursal::withCount('puntosDeVenta')
                ->with('listasPrecios')
                ->orderBy('nombre')
                ->get(),
        ]);
    }
}
