<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea el usuario administrador del sistema.
     */
    public function run(): void
    {
        // Crear usuario administrador
        $admin = User::create([
            'name' => 'Administrador',
            'email' => 'admin@sistema.com',
            'password' => Hash::make('Admin1234'),
            'active' => true,
        ]);

        // Asignar rol de admin
        $admin->assignRole('admin');

        $this->command->info('Usuario administrador creado correctamente.');
        $this->command->info('Email: admin@sistema.com');
        $this->command->info('Password: Admin1234');
    }
}
