<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     * Ejecuta los seeders en orden: primero roles y permisos, luego usuarios.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            ListasPreciosSeeder::class,
            SucursalesSeeder::class,
            // Indumentaria: configurables con variantes color + talle y simples.
            // Precios: lista PUBLICO con factor 1 = el precio del producto. No se llama a
            // DetallePrecioPublicoSeeder: es del esquema anterior (columna `precio`, hoy
            // `precio_override`) y un override igual al precio base no aporta nada.
            ProductSeeder::class,
            // Stock por variante y sucursal (nunca sobre el configurable).
            StockSeeder::class,
        ]);
    }
}
