<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('genero', 50)->nullable()->after('atributos_extra')->index();
        });

        // Insertar tipo de atributo Género con columna dedicada
        $typeId = DB::table('attribute_types')->insertGetId([
            'nombre' => 'Género',
            'slug' => 'genero',
            'product_column' => 'genero',
            'activo' => true,
            'orden' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $valores = ['Femenino', 'Masculino', 'Unisex', 'Niña', 'Niño', 'Bebé'];
        foreach ($valores as $index => $valor) {
            DB::table('attribute_values')->insert([
                'attribute_type_id' => $typeId,
                'valor' => $valor,
                'orden' => $index,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('attribute_types')->where('slug', 'genero')->delete();

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['genero']);
            $table->dropColumn('genero');
        });
    }
};
