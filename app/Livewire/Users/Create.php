<?php

namespace App\Livewire\Users;

use App\Livewire\Users\Concerns\DatosDeUsuario;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesRequests, DatosDeUsuario;

    public function mount(): void
    {
        $this->authorize('usuarios.crear');
    }

    /**
     * Crea un nuevo usuario.
     */
    public function save(): void
    {
        $this->authorize('usuarios.crear');

        $this->guardarUsuario(null);

        session()->flash('success', $this->atiendeCaja()
            ? 'Usuario creado. Las cajas de sus sucursales lo reciben en el próximo minuto.'
            : 'Usuario creado correctamente.');

        $this->redirect('/usuarios', navigate: true);
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.users.create', $this->datosDelFormulario());
    }
}
