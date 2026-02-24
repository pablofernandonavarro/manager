<?php

namespace App\Livewire\Configuration;

use App\Models\Linea;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Lineas extends Component
{
    public string $nombre = '';

    public ?int $editingId = null;

    public string $editingNombre = '';

    public function save(): void
    {
        $this->validate(['nombre' => 'required|string|max:100']);

        Linea::create(['nombre' => $this->nombre, 'activo' => true]);

        $this->nombre = '';
    }

    public function startEdit(int $id): void
    {
        $linea = Linea::findOrFail($id);
        $this->editingId = $id;
        $this->editingNombre = $linea->nombre;
    }

    public function saveEdit(): void
    {
        $this->validate(['editingNombre' => 'required|string|max:100']);

        Linea::findOrFail($this->editingId)->update(['nombre' => $this->editingNombre]);

        $this->editingId = null;
        $this->editingNombre = '';
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editingNombre = '';
    }

    public function toggleActive(int $id): void
    {
        $linea = Linea::findOrFail($id);
        $linea->update(['activo' => ! $linea->activo]);
    }

    public function delete(int $id): void
    {
        Linea::findOrFail($id)->delete();
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.configuration.lineas', [
            'lineas' => Linea::orderBy('nombre')->get(),
        ]);
    }
}
