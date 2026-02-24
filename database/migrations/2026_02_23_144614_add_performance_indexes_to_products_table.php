<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('marca', 'idx_marca');
            $table->index('color', 'idx_color');
            $table->index('n_talle', 'idx_n_talle');
            $table->index('composicion', 'idx_composicion');
            $table->index('created_at', 'idx_created_at');
            $table->fullText('busqueda', 'ft_busqueda');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_marca');
            $table->dropIndex('idx_color');
            $table->dropIndex('idx_n_talle');
            $table->dropIndex('idx_composicion');
            $table->dropIndex('idx_created_at');
            $table->dropFullText('ft_busqueda');
        });
    }
};
