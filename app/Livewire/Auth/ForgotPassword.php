<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

class ForgotPassword extends Component
{
    #[Rule('required|email', message: [
        'required' => 'El correo electrónico es obligatorio.',
        'email' => 'Debes ingresar un correo electrónico válido.',
    ])]
    public string $email = '';

    public string $status = '';

    /**
     * Envía el enlace de restablecimiento de contraseña al correo proporcionado.
     */
    public function send(): void
    {
        $this->validate();

        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status === Password::RESET_LINK_SENT) {
            $this->status = 'Te hemos enviado un enlace para restablecer tu contraseña por correo electrónico.';
            $this->reset('email');
        } else {
            $this->addError('email', 'No hemos podido encontrar un usuario con ese correo electrónico.');
        }
    }

    #[Layout('layouts.guest')]
    public function render(): mixed
    {
        return view('livewire.auth.forgot-password');
    }
}
