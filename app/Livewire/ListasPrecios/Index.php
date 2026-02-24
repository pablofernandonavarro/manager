<?php

namespace App\Livewire\ListasPrecios;

use App\Models\ListaPrecio;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Index extends Component
{
    public string $nombre = '';

    public string $descripcion = '';

    public string $factor = '1.0000';

    public ?int $editingId = null;

    public string $editingNombre = '';

    public string $editingDescripcion = '';

    public string $editingFactor = '1.0000';

    public function save(): void
    {
        $this->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:500',
            'factor' => 'required|numeric|min:0.0001|max:99.9999',
        ]);

        ListaPrecio::create([
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion ?: null,
            'factor' => $this->factor,
            'activo' => true,
        ]);

        $this->nombre = '';
        $this->descripcion = '';
        $this->factor = '1.0000';
    }

    public function startEdit(int $id): void
    {
        $lista = ListaPrecio::findOrFail($id);
        $this->editingId = $id;
        $this->editingNombre = $lista->nombre;
        $this->editingDescripcion = $lista->descripcion ?? '';
        $this->editingFactor = (string) $lista->factor;
    }

    public function saveEdit(): void
    {
        $this->validate([
            'editingNombre' => 'required|string|max:100',
            'editingDescripcion' => 'nullable|string|max:500',
            'editingFactor' => 'required|numeric|min:0.0001|max:99.9999',
        ]);

        ListaPrecio::findOrFail($this->editingId)->update([
            'nombre' => $this->editingNombre,
            'descripcion' => $this->editingDescripcion ?: null,
            'factor' => $this->editingFactor,
        ]);

        $this->editingId = null;
        $this->editingNombre = '';
        $this->editingDescripcion = '';
        $this->editingFactor = '1.0000';
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editingNombre = '';
        $this->editingDescripcion = '';
        $this->editingFactor = '1.0000';
    }

    public function toggleActive(int $id): void
    {
        $lista = ListaPrecio::findOrFail($id);
        $lista->update(['activo' => ! $lista->activo]);
    }

    public function delete(int $id): void
    {
        ListaPrecio::findOrFail($id)->delete();
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.listas-precios.index', [
            'listas' => ListaPrecio::withCount('detalles')->orderBy('nombre')->get(),
        ]);
    }
}
