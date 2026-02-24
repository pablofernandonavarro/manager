<?php

namespace App\Livewire\Configuration;

use App\Models\Grupo;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Grupos extends Component
{
    public string $nombre = '';

    public ?int $editingId = null;

    public string $editingNombre = '';

    public function save(): void
    {
        $this->validate(['nombre' => 'required|string|max:100']);

        Grupo::create(['nombre' => $this->nombre, 'activo' => true]);

        $this->nombre = '';
    }

    public function startEdit(int $id): void
    {
        $grupo = Grupo::findOrFail($id);
        $this->editingId = $id;
        $this->editingNombre = $grupo->nombre;
    }

    public function saveEdit(): void
    {
        $this->validate(['editingNombre' => 'required|string|max:100']);

        Grupo::findOrFail($this->editingId)->update(['nombre' => $this->editingNombre]);

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
        $grupo = Grupo::findOrFail($id);
        $grupo->update(['activo' => ! $grupo->activo]);
    }

    public function delete(int $id): void
    {
        Grupo::findOrFail($id)->delete();
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.configuration.grupos', [
            'grupos' => Grupo::orderBy('nombre')->get(),
        ]);
    }
}
