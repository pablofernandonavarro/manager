<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Actualizar todos los usuarios sin valor en 'active'
\DB::table('users')
    ->whereNull('active')
    ->update(['active' => true]);

echo "✅ Usuarios actualizados correctamente!\n";

// Mostrar todos los usuarios
$users = \App\Models\User::withTrashed()->get();
echo "\nTotal de usuarios: " . $users->count() . "\n\n";

foreach ($users as $user) {
    echo "ID: {$user->id} | {$user->name} | {$user->email} | Activo: " . ($user->active ? 'Sí' : 'No') . "\n";
}
