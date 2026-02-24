<?php

namespace App\Livewire\Configuration;

use App\Models\Procedencia;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Procedencias extends Component
{
    public string $nombre = '';

    public ?int $editingId = null;

    public string $editingNombre = '';

    public function save(): void
    {
        $this->validate(['nombre' => 'required|string|max:100']);

        Procedencia::create(['nombre' => $this->nombre, 'activo' => true]);

        $this->nombre = '';
    }

    public function startEdit(int $id): void
    {
        $procedencia = Procedencia::findOrFail($id);
        $this->editingId = $id;
        $this->editingNombre = $procedencia->nombre;
    }

    public function saveEdit(): void
    {
        $this->validate(['editingNombre' => 'required|string|max:100']);

        Procedencia::findOrFail($this->editingId)->update(['nombre' => $this->editingNombre]);

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
        $procedencia = Procedencia::findOrFail($id);
        $procedencia->update(['activo' => ! $procedencia->activo]);
    }

    public function delete(int $id): void
    {
        Procedencia::findOrFail($id)->delete();
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.configuration.procedencias', [
            'procedencias' => Procedencia::orderBy('nombre')->get(),
        ]);
    }
}
