<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_lista_precios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lista_precio_id')->constrained('listas_precios')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('precio', 16, 2);
            $table->date('vigencia_desde')->nullable();
            $table->date('vigencia_hasta')->nullable();
            $table->timestamps();

            $table->unique(['lista_precio_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_lista_precios');
    }
};
