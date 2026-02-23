<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Agregando columna 'active' a la tabla users...\n";

try {
    // Agregar columna active
    if (!\Schema::hasColumn('users', 'active')) {
        \DB::statement('ALTER TABLE users ADD COLUMN active TINYINT(1) DEFAULT 1 AFTER email');
        echo "✅ Columna 'active' agregada correctamente\n";
    } else {
        echo "ℹ️  La columna 'active' ya existe\n";
    }

    // Agregar columna deleted_at
    if (!\Schema::hasColumn('users', 'deleted_at')) {
        \DB::statement('ALTER TABLE users ADD COLUMN deleted_at TIMESTAMP NULL AFTER updated_at');
        echo "✅ Columna 'deleted_at' agregada correctamente\n";
    } else {
        echo "ℹ️  La columna 'deleted_at' ya existe\n";
    }

    // Actualizar usuarios existentes
    echo "\nActualizando usuarios existentes...\n";
    \DB::table('users')->whereNull('active')->update(['active' => 1]);

    echo "✅ ¡Migraciones completadas exitosamente!\n\n";

    // Mostrar usuarios
    $users = \App\Models\User::withTrashed()->get();
    echo "Total de usuarios: " . $users->count() . "\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
