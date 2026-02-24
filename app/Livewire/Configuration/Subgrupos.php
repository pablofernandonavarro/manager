<?php

namespace App\Livewire\Configuration;

use App\Models\Subgrupo;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Subgrupos extends Component
{
    public string $nombre = '';

    public ?int $editingId = null;

    public string $editingNombre = '';

    public function save(): void
    {
        $this->validate(['nombre' => 'required|string|max:100']);

        Subgrupo::create(['nombre' => $this->nombre, 'activo' => true]);

        $this->nombre = '';
    }

    public function startEdit(int $id): void
    {
        $subgrupo = Subgrupo::findOrFail($id);
        $this->editingId = $id;
        $this->editingNombre = $subgrupo->nombre;
    }

    public function saveEdit(): void
    {
        $this->validate(['editingNombre' => 'required|string|max:100']);

        Subgrupo::findOrFail($this->editingId)->update(['nombre' => $this->editingNombre]);

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
        $subgrupo = Subgrupo::findOrFail($id);
        $subgrupo->update(['activo' => ! $subgrupo->activo]);
    }

    public function delete(int $id): void
    {
        Subgrupo::findOrFail($id)->delete();
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.configuration.subgrupos', [
            'subgrupos' => Subgrupo::orderBy('nombre')->get(),
        ]);
    }
}
