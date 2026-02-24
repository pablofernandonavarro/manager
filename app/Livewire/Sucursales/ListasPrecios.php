<?php

namespace App\Livewire\Sucursales;

use App\Models\ListaPrecio;
use App\Models\Sucursal;
use Livewire\Attributes\Layout;
use Livewire\Component;

class ListasPrecios extends Component
{
    public array $sucursales = [];
    public array $listasPrecios = [];
    public array $asignaciones = []; // [sucursal_id => [lista_id => ['asignado' => bool, 'es_default' => bool]]]

    public function mount(): void
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->sucursales = Sucursal::where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->toArray();

        $this->listasPrecios = ListaPrecio::orderBy('nombre')->get()->toArray();

        // Cargar asignaciones actuales
        foreach (Sucursal::with('listasPrecios')->get() as $sucursal) {
            foreach ($sucursal->listasPrecios as $lista) {
                $this->asignaciones[$sucursal->id][$lista->id] = [
                    'asignado' => true,
                    'es_default' => (bool) $lista->pivot->es_default,
                ];
            }
        }
    }

    public function toggleAsignacion(int $sucursalId, int $listaId): void
    {
        $sucursal = Sucursal::findOrFail($sucursalId);

        if ($this->asignaciones[$sucursalId][$listaId]['asignado'] ?? false) {
            // Desasignar
            $sucursal->listasPrecios()->detach($listaId);
            unset($this->asignaciones[$sucursalId][$listaId]);
        } else {
            // Asignar
            $sucursal->listasPrecios()->attach($listaId, ['es_default' => false]);
            $this->asignaciones[$sucursalId][$listaId] = [
                'asignado' => true,
                'es_default' => false,
            ];
        }
    }

    public function setDefault(int $sucursalId, int $listaId): void
    {
        $sucursal = Sucursal::findOrFail($sucursalId);

        // Remover default de todas las listas de esta sucursal
        $sucursal->listasPrecios()->updateExistingPivot(
            $sucursal->listasPrecios->pluck('id')->toArray(),
            ['es_default' => false]
        );

        // Marcar la nueva como default
        $sucursal->listasPrecios()->updateExistingPivot($listaId, ['es_default' => true]);

        // Actualizar estado local
        foreach ($this->asignaciones[$sucursalId] ?? [] as $id => $data) {
            $this->asignaciones[$sucursalId][$id]['es_default'] = ($id === $listaId);
        }
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.sucursales.listas-precios');
    }
}
