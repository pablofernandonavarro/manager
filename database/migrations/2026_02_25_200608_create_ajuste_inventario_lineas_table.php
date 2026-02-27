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
        Schema::create('ajuste_inventario_lineas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ajuste_inventario_id')->constrained('ajustes_inventario')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('cantidad_anterior')->default(0);
            $table->integer('cantidad_nueva');
            $table->integer('delta');
            $table->timestamps();

            $table->unique(['ajuste_inventario_id', 'product_id']);
            $table->index('ajuste_inventario_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ajuste_inventario_lineas');
    }
};
