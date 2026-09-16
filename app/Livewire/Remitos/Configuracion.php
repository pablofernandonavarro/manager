<?php

namespace App\Livewire\Remitos;

use App\Models\ConfiguracionRemitos;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Configuracion extends Component
{
    use AuthorizesRequests;

    public bool $rutaDirecta = true;

    public string $destinoRechazados = 'origen';

    public ?string $mensaje = null;

    public function mount(): void
    {
        $this->authorize('remitos.configurar');

        $c = ConfiguracionRemitos::actual();
        $this->rutaDirecta = $c->ruta_directa;
        $this->destinoRechazados = $c->destino_rechazados;
    }

    public function guardar(): void
    {
        $this->authorize('remitos.configurar');
        $this->mensaje = null;

        $datos = $this->validate([
            'rutaDirecta' => 'boolean',
            'destinoRechazados' => ['required', Rule::in(array_keys(ConfiguracionRemitos::DESTINOS_RECHAZADOS))],
        ]);

        ConfiguracionRemitos::actual()->update([
            'ruta_directa' => $datos['rutaDirecta'],
            'destino_rechazados' => $datos['destinoRechazados'],
        ]);

        $this->mensaje = 'Comportamiento de remitos guardado.';
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.remitos.configuracion', ['destinos' => ConfiguracionRemitos::DESTINOS_RECHAZADOS]);
    }
}
