<?php

namespace App\Livewire\Users;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class Create extends Component
{
    #[Rule('required|string|max:255', message: [
        'required' => 'El nombre es obligatorio.',
        'max' => 'El nombre no puede exceder :max caracteres.',
    ])]
    public string $name = '';

    #[Rule('required|email|unique:users,email', message: [
        'required' => 'El correo electrónico es obligatorio.',
        'email' => 'Debes ingresar un correo electrónico válido.',
        'unique' => 'Este correo electrónico ya está registrado.',
    ])]
    public string $email = '';

    #[Rule('required|min:8', message: [
        'required' => 'La contraseña es obligatoria.',
        'min' => 'La contraseña debe tener al menos :min caracteres.',
    ])]
    public string $password = '';

    #[Rule('required', message: [
        'required' => 'Debes seleccionar un rol.',
    ])]
    public string $role = '';

    #[Rule('boolean')]
    public bool $active = true;

    /**
     * Crea un nuevo usuario.
     */
    public function save(): void
    {
        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'active' => $this->active,
        ]);

        // Asignar rol
        $user->assignRole($this->role);

        session()->flash('success', 'Usuario creado correctamente.');

        $this->redirect('/usuarios', navigate: true);
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $roles = Role::all();

        return view('livewire.users.create', [
            'roles' => $roles,
        ]);
    }
}
