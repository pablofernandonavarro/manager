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
        Schema::create('ventas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('punto_de_venta_id')->constrained('puntos_de_venta')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('lista_precio_id')->nullable()->constrained('listas_precios')->nullOnDelete();
            $table->string('numero_venta', 50)->nullable();
            $table->dateTime('fecha');
            $table->decimal('subtotal', 16, 2);
            $table->decimal('descuento', 16, 2)->default(0);
            $table->decimal('total', 16, 2);
            $table->dateTime('sincronizado_at')->nullable();
            $table->timestamps();

            $table->index(['sucursal_id', 'fecha']);
            $table->index('sincronizado_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
