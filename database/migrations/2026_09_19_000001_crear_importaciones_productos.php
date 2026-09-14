<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Importaciones de productos por Excel. Aplicar miles de filas tarda minutos (más que el
 * límite de un pedido web), así que corre en la cola y la pantalla sigue su estado acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importaciones_productos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('archivo');
            $table->string('nombre_original');
            $table->string('estado', 20)->default('pendiente')->index();
            $table->unsignedInteger('filas')->default(0);
            $table->json('resumen')->nullable();
            $table->json('resultado')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('iniciado_at')->nullable();
            $table->timestamp('terminado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importaciones_productos');
    }
};
