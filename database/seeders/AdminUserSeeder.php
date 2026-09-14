<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea el usuario administrador del sistema.
     *
     * La contraseña ya no está escrita en el código: quedaba versionada en el repositorio,
     * y cualquiera con acceso al código tenía la del admin de todas las instalaciones.
     * Se toma de ADMIN_PASSWORD o, si no está, se genera una al azar y se muestra una
     * sola vez acá.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@sistema.com');

        if (User::where('email', $email)->exists()) {
            $this->command->warn("Ya existe un usuario con {$email}. No se creó nada.");

            return;
        }

        $password = env('ADMIN_PASSWORD');
        $generada = $password === null;

        if ($generada) {
            $password = Str::password(16);
        }

        $admin = User::create([
            'name' => env('ADMIN_NAME', 'Administrador'),
            'email' => $email,
            'password' => Hash::make($password),
            'active' => true,
        ]);

        $admin->assignRole('admin');

        $this->command->info('Usuario administrador creado.');
        $this->command->info("Email: {$email}");

        if ($generada) {
            $this->command->warn("Contraseña generada: {$password}");
            $this->command->warn('Anotala ahora: no se vuelve a mostrar.');
        } else {
            $this->command->info('Contraseña: la de ADMIN_PASSWORD.');
        }
    }
}
