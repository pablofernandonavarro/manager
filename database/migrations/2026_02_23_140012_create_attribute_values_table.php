<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_type_id')->constrained()->cascadeOnDelete();
            $table->string('valor', 100);
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $colorId = DB::table('attribute_types')->where('slug', 'color')->value('id');
        $talleId = DB::table('attribute_types')->where('slug', 'talle')->value('id');

        $colorValues = ['Negro', 'Blanco', 'Rojo', 'Azul', 'Verde', 'Gris', 'Rosa', 'Amarillo', 'Naranja', 'Violeta'];
        foreach ($colorValues as $index => $valor) {
            DB::table('attribute_values')->insert([
                'attribute_type_id' => $colorId,
                'valor' => $valor,
                'orden' => $index,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $talleValues = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
        foreach ($talleValues as $index => $valor) {
            DB::table('attribute_values')->insert([
                'attribute_type_id' => $talleId,
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
        Schema::dropIfExists('attribute_values');
    }
};
