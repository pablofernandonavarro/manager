<?php

namespace App\Livewire\Sucursales;

use App\Models\ListaPrecio;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Edit extends Component
{
    public Sucursal $sucursal;

    public string $nombre = '';

    public string $direccion = '';

    public string $telefono = '';

    public string $tab = 'datos';

    /** @var array<int, bool> */
    public array $listasSeleccionadas = [];

    public ?int $listaDefault = null;

    /** @var array<int, int> */
    public array $stockEditable = [];

    public function mount(int $id): void
    {
        $this->sucursal = Sucursal::findOrFail($id);
        $this->nombre = $this->sucursal->nombre;
        $this->direccion = $this->sucursal->direccion ?? '';
        $this->telefono = $this->sucursal->telefono ?? '';

        $listasAsignadas = $this->sucursal->listasPrecios()->get();
        foreach ($listasAsignadas as $lista) {
            $this->listasSeleccionadas[$lista->id] = true;
            if ($lista->pivot->es_default) {
                $this->listaDefault = $lista->id;
            }
        }

        $stocks = $this->sucursal->stockSucursal()->get();
        foreach ($stocks as $stock) {
            $this->stockEditable[$stock->product_id] = $stock->cantidad;
        }
    }

    public function saveDatos(): void
    {
        $this->validate([
            'nombre' => 'required|string|max:100',
            'direccion' => 'nullable|string|max:200',
            'telefono' => 'nullable|string|max:50',
        ]);

        $this->sucursal->update([
            'nombre' => $this->nombre,
            'direccion' => $this->direccion ?: null,
            'telefono' => $this->telefono ?: null,
        ]);

        $this->dispatch('saved');
    }

    public function saveListas(): void
    {
        $sync = [];
        foreach ($this->listasSeleccionadas as $listaId => $seleccionada) {
            if ($seleccionada) {
                $sync[$listaId] = ['es_default' => $this->listaDefault === $listaId];
            }
        }

        $this->sucursal->listasPrecios()->sync($sync);
        $this->dispatch('saved');
    }

    public function saveStock(): void
    {
        foreach ($this->stockEditable as $productId => $cantidad) {
            StockSucursal::updateOrCreate(
                ['sucursal_id' => $this->sucursal->id, 'product_id' => $productId],
                ['cantidad' => max(0, (int) $cantidad)],
            );
        }

        $this->dispatch('saved');
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.sucursales.edit', [
            'todasLasListas' => ListaPrecio::where('activo', true)->orderBy('nombre')->get(),
            'stockItems' => $this->sucursal->stockSucursal()->with('product')->get(),
        ]);
    }
}
