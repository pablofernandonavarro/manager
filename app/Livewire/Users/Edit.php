<?php

namespace App\Livewire\Users;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class Edit extends Component
{
    public User $user;

    #[Rule('required|string|max:255', message: [
        'required' => 'El nombre es obligatorio.',
        'max' => 'El nombre no puede exceder :max caracteres.',
    ])]
    public string $name = '';

    public string $email = '';

    public string $password = '';

    #[Rule('required', message: [
        'required' => 'Debes seleccionar un rol.',
    ])]
    public string $role = '';

    #[Rule('boolean')]
    public bool $active = true;

    /**
     * Inicializa el componente con los datos del usuario.
     */
    public function mount(int $userId): void
    {
        $this->user = User::with('roles')->findOrFail($userId);
        $this->name = $this->user->name;
        $this->email = $this->user->email;
        $this->active = $this->user->active;
        $this->role = $this->user->roles->first()?->name ?? '';
    }

    /**
     * Actualiza el usuario.
     */
    public function update(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $this->user->id,
            'password' => 'nullable|min:8',
            'role' => 'required',
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede exceder :max caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Debes ingresar un correo electrónico válido.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'role.required' => 'Debes seleccionar un rol.',
        ]);

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'active' => $this->active,
        ];

        // Solo actualizar contraseña si se proporcionó una nueva
        if (!empty($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        $this->user->update($data);

        // Sincronizar rol
        $this->user->syncRoles([$this->role]);

        session()->flash('success', 'Usuario actualizado correctamente.');

        $this->redirect('/usuarios', navigate: true);
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $roles = Role::all();

        return view('livewire.users.edit', [
            'roles' => $roles,
        ]);
    }
}
