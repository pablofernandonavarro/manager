<?php

namespace App\Livewire\Users;

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;

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
            ->with('roles')
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        // Debug: log el total de usuarios
        \Log::info('Total usuarios en consulta: ' . User::withTrashed()->count());
        \Log::info('Usuarios en página actual: ' . $users->count());
        \Log::info('Total de resultados: ' . $users->total());

        return view('livewire.users.index', [
            'users' => $users,
        ]);
    }
}
