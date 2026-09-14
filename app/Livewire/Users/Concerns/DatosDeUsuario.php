<?php

namespace App\Livewire\Users\Concerns;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Formulario de alta y edición de usuarios, incluidos los que atienden cajas.
 *
 * Cajero: una sola sucursal, PIN obligatorio, sin email ni contraseña obligatorios (no
 * entra al Manager). Supervisor: una o más sucursales, PIN, y sí entra al Manager.
 */
trait DatosDeUsuario
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = '';

    public bool $active = true;

    public string $pin = '';

    /** @var array<int, int|string> */
    public array $sucursales = [];

    public function atiendeCaja(): bool
    {
        return in_array($this->role, User::ROLES_CAJA, true);
    }

    /** El cajero atiende en una sola sucursal: el formulario la elige con un radio. */
    public function elegirSucursal(int $sucursalId): void
    {
        $this->sucursales = [$sucursalId];
    }

    /**
     * @return array<string, mixed>
     */
    protected function reglasDeUsuario(?User $user): array
    {
        $esCajero = $this->role === 'cajero';
        $sinPasswordGuardada = ! $user?->getRawOriginal('password');
        $sinPinGuardado = ! $user?->getRawOriginal('pin_hash');

        return [
            'name' => 'required|string|max:255',
            'email' => [$esCajero ? 'nullable' : 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [(! $esCajero && $sinPasswordGuardada) ? 'required' : 'nullable', 'min:8'],
            'role' => ['required', Rule::exists(Role::class, 'name')->where('guard_name', 'web')],
            'active' => 'boolean',
            'pin' => $this->atiendeCaja()
                ? [$sinPinGuardado ? 'required' : 'nullable', 'digits_between:4,6']
                : ['nullable'],
            'sucursales' => $this->atiendeCaja()
                ? ['required', 'array', 'min:1', ...($esCajero ? ['max:1'] : [])]
                : ['array'],
            'sucursales.*' => ['integer', Rule::exists('sucursales', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function mensajesDeUsuario(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede exceder :max caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Debes ingresar un correo electrónico válido.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'role.required' => 'Debes seleccionar un rol.',
            'pin.required' => 'El PIN de caja es obligatorio.',
            'pin.digits_between' => 'El PIN tiene que tener entre 4 y 6 números.',
            'sucursales.required' => 'Elegí al menos una sucursal.',
            'sucursales.min' => 'Elegí al menos una sucursal.',
            'sucursales.max' => 'Un cajero atiende en una sola sucursal.',
        ];
    }

    protected function guardarUsuario(?User $user): User
    {
        $datos = $this->validate($this->reglasDeUsuario($user), $this->mensajesDeUsuario());

        $atributos = [
            'name' => trim($datos['name']),
            'email' => ($datos['email'] ?? '') !== '' ? $datos['email'] : null,
            'active' => $datos['active'],
        ];

        if (($datos['password'] ?? '') !== '') {
            $atributos['password'] = Hash::make($datos['password']);
        }

        if (! $this->atiendeCaja()) {
            $atributos['pin_hash'] = null;
        } elseif (($datos['pin'] ?? '') !== '') {
            $atributos['pin_hash'] = Hash::make($datos['pin']);
        }

        $user ??= new User;
        $user->fill($atributos)->save();
        $user->syncRoles([$datos['role']]);
        $user->sucursales()->sync($this->atiendeCaja() ? array_map('intval', $datos['sucursales']) : []);

        $this->pin = '';
        $this->password = '';

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function datosDelFormulario(): array
    {
        return [
            'roles' => Role::where('guard_name', 'web')->orderBy('name')->get(),
            'listaSucursales' => Sucursal::where('activo', true)->orderBy('nombre')->get(),
        ];
    }
}
