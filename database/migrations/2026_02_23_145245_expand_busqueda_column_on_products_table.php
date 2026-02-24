<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Cambiar VARCHAR(160) a TEXT para soportar todos los atributos
            // y eliminar el FULLTEXT anterior que estaba sobre VARCHAR
            $table->dropFullText('ft_busqueda');
            $table->text('busqueda')->nullable()->change();
        });

        // Re-crear el FULLTEXT sobre el nuevo TEXT
        Schema::table('products', function (Blueprint $table) {
            $table->fullText('busqueda', 'ft_busqueda');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropFullText('ft_busqueda');
            $table->string('busqueda', 160)->nullable()->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->fullText('busqueda', 'ft_busqueda');
        });
    }
};
