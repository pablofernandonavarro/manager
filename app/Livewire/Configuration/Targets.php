<?php

namespace App\Livewire\Configuration;

use App\Models\Target;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Targets extends Component
{
    public string $nombre = '';

    public ?int $editingId = null;

    public string $editingNombre = '';

    public function save(): void
    {
        $this->validate(['nombre' => 'required|string|max:100']);

        Target::create(['nombre' => $this->nombre, 'activo' => true]);

        $this->nombre = '';
    }

    public function startEdit(int $id): void
    {
        $target = Target::findOrFail($id);
        $this->editingId = $id;
        $this->editingNombre = $target->nombre;
    }

    public function saveEdit(): void
    {
        $this->validate(['editingNombre' => 'required|string|max:100']);

        Target::findOrFail($this->editingId)->update(['nombre' => $this->editingNombre]);

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
        $target = Target::findOrFail($id);
        $target->update(['activo' => ! $target->activo]);
    }

    public function delete(int $id): void
    {
        Target::findOrFail($id)->delete();
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.configuration.targets', [
            'targets' => Target::orderBy('nombre')->get(),
        ]);
    }
}
