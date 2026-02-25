<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remito_detalles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('remito_id')->constrained('remitos')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('cantidad');
            $table->timestamps();

            $table->unique(['remito_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remito_detalles');
    }
};
