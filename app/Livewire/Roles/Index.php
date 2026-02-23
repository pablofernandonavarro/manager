<?php

namespace App\Livewire\Roles;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

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
     * Elimina un rol.
     */
    public function delete(int $roleId): void
    {
        $role = Role::findOrFail($roleId);

        // No permitir eliminar roles del sistema
        if (in_array($role->name, ['admin', 'supervisor', 'cajero'])) {
            session()->flash('error', 'No puedes eliminar roles predeterminados del sistema.');
            return;
        }

        // Verificar si hay usuarios con este rol
        if ($role->users()->count() > 0) {
            session()->flash('error', 'No puedes eliminar un rol que tiene usuarios asignados.');
            return;
        }

        $role->delete();

        session()->flash('success', 'Rol eliminado correctamente.');
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $roles = Role::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->withCount(['users', 'permissions'])
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        return view('livewire.roles.index', [
            'roles' => $roles,
        ]);
    }
}
