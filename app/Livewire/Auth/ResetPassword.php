<?php

namespace App\Livewire\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

class ResetPassword extends Component
{
    public string $token = '';

    #[Rule('required|email', message: [
        'required' => 'El correo electrónico es obligatorio.',
        'email' => 'Debes ingresar un correo electrónico válido.',
    ])]
    public string $email = '';

    #[Rule('required|min:8|confirmed', message: [
        'required' => 'La contraseña es obligatoria.',
        'min' => 'La contraseña debe tener al menos :min caracteres.',
        'confirmed' => 'Las contraseñas no coinciden.',
    ])]
    public string $password = '';

    #[Rule('required', message: [
        'required' => 'Debes confirmar tu contraseña.',
    ])]
    public string $password_confirmation = '';

    /**
     * Inicializa el token y el email desde los parámetros de la URL.
     */
    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->query('email', '');
    }

    /**
     * Restablece la contraseña del usuario.
     */
    public function resetPassword(): void
    {
        $this->validate();

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            session()->flash('status', 'Tu contraseña ha sido restablecida correctamente.');
            $this->redirect('/login', navigate: true);
        } else {
            $this->addError('email', 'El enlace de restablecimiento es inválido o ha expirado.');
        }
    }

    #[Layout('layouts.guest')]
    public function render(): mixed
    {
        return view('livewire.auth.reset-password');
    }
}
