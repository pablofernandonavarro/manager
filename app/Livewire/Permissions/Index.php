<?php

namespace App\Livewire\Permissions;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;

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

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $permissions = Permission::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->orderBy('name', 'asc')
            ->paginate($this->perPage);

        // Agrupar permisos por módulo
        $permissionsGrouped = $permissions->getCollection()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        });

        return view('livewire.permissions.index', [
            'permissions' => $permissions,
            'permissionsGrouped' => $permissionsGrouped,
        ]);
    }
}
