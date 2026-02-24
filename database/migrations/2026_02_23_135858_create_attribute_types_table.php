<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_types', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('slug', 100)->unique();
            $table->string('product_column', 100)->nullable();
            $table->boolean('activo')->default(true);
            $table->integer('orden')->default(0);
            $table->timestamps();
        });

        DB::table('attribute_types')->insert([
            [
                'nombre' => 'Color',
                'slug' => 'color',
                'product_column' => 'color',
                'activo' => true,
                'orden' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'Talle',
                'slug' => 'talle',
                'product_column' => 'n_talle',
                'activo' => true,
                'orden' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_types');
    }
};
