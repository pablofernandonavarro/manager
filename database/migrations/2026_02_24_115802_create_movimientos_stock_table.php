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
        Schema::create('movimientos_stock', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('punto_de_venta_id')->constrained('puntos_de_venta')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->enum('tipo', ['venta', 'entrada', 'ajuste', 'devolucion', 'transferencia']);
            $table->integer('cantidad');
            $table->string('referencia', 100)->nullable();
            $table->dateTime('fecha');
            $table->dateTime('sincronizado_at')->nullable();
            $table->timestamps();

            $table->index(['sucursal_id', 'product_id']);
            $table->index('sincronizado_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_stock');
    }
};
