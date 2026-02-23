<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Consultar usuarios
$users = \App\Models\User::withTrashed()->get();

echo "Total de usuarios en la base de datos: " . $users->count() . "\n\n";

foreach ($users as $user) {
    echo "ID: {$user->id}\n";
    echo "Nombre: {$user->name}\n";
    echo "Email: {$user->email}\n";
    echo "Activo: " . ($user->active ? 'Sí' : 'No') . "\n";
    echo "Eliminado: " . ($user->trashed() ? 'Sí' : 'No') . "\n";
    echo "Roles: " . $user->roles->pluck('name')->join(', ') . "\n";
    echo "---\n\n";
}
