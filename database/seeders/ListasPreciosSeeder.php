<?php

namespace Database\Seeders;

use App\Models\ListaPrecio;
use Illuminate\Database\Seeder;

class ListasPreciosSeeder extends Seeder
{
    public function run(): void
    {
        $listas = [
            ['nombre' => 'PUBLICO',          'factor' => 1.0000],
            ['nombre' => 'MINORISTA',        'factor' => 1.0000],
            ['nombre' => 'ONLINE MAYORISTA', 'factor' => 1.0000],
            ['nombre' => 'MAYORISTA',        'factor' => 1.0000],
            ['nombre' => 'ORIGINALES',       'factor' => 1.0000],
        ];

        foreach ($listas as $lista) {
            ListaPrecio::firstOrCreate(
                ['nombre' => $lista['nombre']],
                ['factor' => $lista['factor'], 'activo' => true]
            );
        }
    }
}
