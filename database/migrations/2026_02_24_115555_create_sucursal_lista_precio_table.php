<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sucursal_lista_precio', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('lista_precio_id')->constrained('listas_precios')->cascadeOnDelete();
            $table->boolean('es_default')->default(false);
            $table->timestamps();

            $table->unique(['sucursal_id', 'lista_precio_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sucursal_lista_precio');
    }
};
