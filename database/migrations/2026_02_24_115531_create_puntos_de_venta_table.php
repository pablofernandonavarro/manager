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
        Schema::create('puntos_de_venta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('secret', 100);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('puntos_de_venta');
    }
};
