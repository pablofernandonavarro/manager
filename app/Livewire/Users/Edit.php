<?php

namespace App\Livewire\Users;

use App\Livewire\Users\Concerns\DatosDeUsuario;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Edit extends Component
{
    use AuthorizesRequests, DatosDeUsuario;

    #[Locked]
    public User $user;

    /**
     * Inicializa el componente con los datos del usuario.
     */
    public function mount(int $userId): void
    {
        $this->authorize('usuarios.editar');

        $this->user = User::with(['roles', 'sucursales'])->findOrFail($userId);
        $this->name = $this->user->name;
        $this->email = $this->user->email ?? '';
        $this->active = $this->user->active;
        $this->role = $this->user->roles->first()?->name ?? '';
        $this->sucursales = $this->user->sucursales->pluck('id')->all();
        $this->fotoActual = $this->user->fotoUrl();
    }

    /**
     * Actualiza el usuario.
     */
    public function update(): void
    {
        $this->authorize('usuarios.editar');

        $this->guardarUsuario($this->user);

        session()->flash('success', 'Usuario actualizado correctamente.');

        $this->redirect('/usuarios', navigate: true);
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.users.edit', [
            ...$this->datosDelFormulario(),
            'tienePin' => (bool) $this->user->getRawOriginal('pin_hash'),
        ]);
    }
}
