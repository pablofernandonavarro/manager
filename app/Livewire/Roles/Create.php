<?php

namespace App\Livewire\Roles;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Create extends Component
{
    #[Rule('required|string|max:255|unique:roles,name', message: [
        'required' => 'El nombre del rol es obligatorio.',
        'max' => 'El nombre no puede exceder :max caracteres.',
        'unique' => 'Ya existe un rol con este nombre.',
    ])]
    public string $name = '';

    public array $selectedPermissions = [];

    /**
     * Crea un nuevo rol.
     */
    public function save(): void
    {
        $this->validate();

        $role = Role::create(['name' => strtolower($this->name)]);

        // Asignar permisos seleccionados
        if (!empty($this->selectedPermissions)) {
            $role->givePermissionTo($this->selectedPermissions);
        }

        session()->flash('success', 'Rol creado correctamente.');

        $this->redirect('/roles', navigate: true);
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        // Agrupar permisos por módulo
        $permissions = Permission::all()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        });

        return view('livewire.roles.create', [
            'permissionsGrouped' => $permissions,
        ]);
    }
}
