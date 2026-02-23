<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    /**
     * Crea usuarios de demostración para el sistema.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Carlos Supervisor',
                'email' => 'supervisor@sistema.com',
                'password' => Hash::make('password123'),
                'active' => true,
                'role' => 'supervisor',
            ],
            [
                'name' => 'María Cajera',
                'email' => 'cajera@sistema.com',
                'password' => Hash::make('password123'),
                'active' => true,
                'role' => 'cajero',
            ],
            [
                'name' => 'Juan Pérez',
                'email' => 'juan@sistema.com',
                'password' => Hash::make('password123'),
                'active' => true,
                'role' => 'cajero',
            ],
            [
                'name' => 'Ana García',
                'email' => 'ana@sistema.com',
                'password' => Hash::make('password123'),
                'active' => false,
                'role' => 'cajero',
            ],
            [
                'name' => 'Luis Supervisor 2',
                'email' => 'luis@sistema.com',
                'password' => Hash::make('password123'),
                'active' => true,
                'role' => 'supervisor',
            ],
        ];

        foreach ($users as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            $user = User::create($userData);
            $user->assignRole($role);
        }

        $this->command->info('Usuarios de demostración creados correctamente.');
    }
}
