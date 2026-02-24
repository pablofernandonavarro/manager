<?php

namespace App\Livewire\Configuration;

use App\Models\Temporada;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Temporadas extends Component
{
    public string $nombre = '';

    public ?int $anio = null;

    public ?int $editingId = null;

    public string $editingNombre = '';

    public ?int $editingAnio = null;

    public function save(): void
    {
        $this->validate([
            'nombre' => 'required|string|max:100',
            'anio' => 'nullable|integer|min:2000|max:2100',
        ]);

        Temporada::create([
            'nombre' => $this->nombre,
            'anio' => $this->anio,
            'activo' => true,
        ]);

        $this->nombre = '';
        $this->anio = null;
    }

    public function startEdit(int $id): void
    {
        $temporada = Temporada::findOrFail($id);
        $this->editingId = $id;
        $this->editingNombre = $temporada->nombre;
        $this->editingAnio = $temporada->anio;
    }

    public function saveEdit(): void
    {
        $this->validate([
            'editingNombre' => 'required|string|max:100',
            'editingAnio' => 'nullable|integer|min:2000|max:2100',
        ]);

        Temporada::findOrFail($this->editingId)->update([
            'nombre' => $this->editingNombre,
            'anio' => $this->editingAnio,
        ]);

        $this->editingId = null;
        $this->editingNombre = '';
        $this->editingAnio = null;
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editingNombre = '';
        $this->editingAnio = null;
    }

    public function toggleActive(int $id): void
    {
        $temporada = Temporada::findOrFail($id);
        $temporada->update(['activo' => ! $temporada->activo]);
    }

    public function delete(int $id): void
    {
        Temporada::findOrFail($id)->delete();
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.configuration.temporadas', [
            'temporadas' => Temporada::orderByDesc('anio')->orderBy('nombre')->get(),
        ]);
    }
}
