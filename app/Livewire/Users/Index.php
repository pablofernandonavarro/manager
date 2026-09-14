<?php

namespace App\Livewire\Users;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';
    public int $perPage = 10;

    public function mount(): void
    {
        $this->authorize('usuarios.ver');
    }

    /**
     * Resetea la paginación cuando se realiza una búsqueda.
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Alterna el estado activo/inactivo de un usuario.
     */
    public function toggleActive(int $userId): void
    {
        $this->authorize('usuarios.editar');

        $user = User::findOrFail($userId);

        // No permitir desactivar al usuario actual
        if ($user->id === auth()->id()) {
            session()->flash('error', 'No puedes desactivar tu propia cuenta.');
            return;
        }

        $user->update(['active' => !$user->active]);

        session()->flash('success', $user->active ? 'Usuario activado correctamente.' : 'Usuario desactivado correctamente.');
    }

    /**
     * Elimina un usuario (soft delete).
     */
    public function delete(int $userId): void
    {
        $this->authorize('usuarios.eliminar');

        $user = User::withTrashed()->findOrFail($userId);

        // No permitir eliminar al usuario actual
        if ($user->id === auth()->id()) {
            session()->flash('error', 'No puedes eliminar tu propia cuenta.');
            return;
        }

        $user->delete();

        session()->flash('success', 'Usuario eliminado correctamente.');
    }

    /**
     * Restaura un usuario eliminado.
     */
    public function restore(int $userId): void
    {
        $this->authorize('usuarios.eliminar');

        $user = User::withTrashed()->findOrFail($userId);
        $user->restore();

        session()->flash('success', 'Usuario restaurado correctamente.');
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $users = User::withTrashed()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->with(['roles', 'sucursales:id,nombre'])
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        return view('livewire.users.index', [
            'users' => $users,
        ]);
    }
}
