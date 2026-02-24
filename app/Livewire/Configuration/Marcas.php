<?php

namespace App\Livewire\Configuration;

use App\Models\Marca;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Marcas extends Component
{
    public string $nombre = '';

    public ?int $editingId = null;

    public string $editingNombre = '';

    public function save(): void
    {
        $this->validate(['nombre' => 'required|string|max:100']);

        Marca::create(['nombre' => $this->nombre, 'activo' => true]);

        $this->nombre = '';
    }

    public function startEdit(int $id): void
    {
        $marca = Marca::findOrFail($id);
        $this->editingId = $id;
        $this->editingNombre = $marca->nombre;
    }

    public function saveEdit(): void
    {
        $this->validate(['editingNombre' => 'required|string|max:100']);

        Marca::findOrFail($this->editingId)->update(['nombre' => $this->editingNombre]);

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
        $marca = Marca::findOrFail($id);
        $marca->update(['activo' => ! $marca->activo]);
    }

    public function delete(int $id): void
    {
        Marca::findOrFail($id)->delete();
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.configuration.marcas', [
            'marcas' => Marca::orderBy('nombre')->get(),
        ]);
    }
}
