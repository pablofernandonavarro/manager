<?php

namespace App\Livewire\Roles;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Edit extends Component
{
    public Role $role;

    #[Rule('required|string|max:255', message: [
        'required' => 'El nombre del rol es obligatorio.',
        'max' => 'El nombre no puede exceder :max caracteres.',
    ])]
    public string $name = '';

    public array $selectedPermissions = [];

    /**
     * Inicializa el componente con los datos del rol.
     */
    public function mount(int $roleId): void
    {
        $this->role = Role::with('permissions')->findOrFail($roleId);
        $this->name = $this->role->name;
        $this->selectedPermissions = $this->role->permissions->pluck('name')->toArray();
    }

    /**
     * Actualiza el rol.
     */
    public function update(): void
    {
        // Validar nombre único excepto el actual
        $this->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $this->role->id,
        ], [
            'name.required' => 'El nombre del rol es obligatorio.',
            'name.max' => 'El nombre no puede exceder :max caracteres.',
            'name.unique' => 'Ya existe un rol con este nombre.',
        ]);

        $this->role->update(['name' => strtolower($this->name)]);

        // Sincronizar permisos
        $this->role->syncPermissions($this->selectedPermissions);

        session()->flash('success', 'Rol actualizado correctamente.');

        $this->redirect('/roles', navigate: true);
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        // Agrupar permisos por módulo
        $permissions = Permission::all()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        });

        return view('livewire.roles.edit', [
            'permissionsGrouped' => $permissions,
        ]);
    }
}
