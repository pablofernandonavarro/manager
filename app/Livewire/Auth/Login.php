<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

class Login extends Component
{
    #[Rule('required|email', message: [
        'required' => 'El correo electrónico es obligatorio.',
        'email' => 'Debes ingresar un correo electrónico válido.',
    ])]
    public string $email = '';

    #[Rule('required|min:6', message: [
        'required' => 'La contraseña es obligatoria.',
        'min' => 'La contraseña debe tener al menos :min caracteres.',
    ])]
    public string $password = '';

    /**
     * Intenta autenticar al usuario con las credenciales proporcionadas.
     */
    public function login(): void
    {
        $this->validate();

        if (Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            request()->session()->regenerate();

            $this->redirect('/dashboard', navigate: true);
        }

        $this->addError('email', 'Las credenciales proporcionadas no son correctas.');
    }

    #[Layout('layouts.guest')]
    public function render(): mixed
    {
        return view('livewire.auth.login');
    }
}
