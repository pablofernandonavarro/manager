<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comandos_pos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('punto_de_venta_id')->constrained('puntos_de_venta')->cascadeOnDelete();
            $table->string('comando', 40);
            $table->string('estado', 20)->default('pendiente');
            $table->text('resultado')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tomado_at')->nullable();
            $table->timestamp('finalizado_at')->nullable();
            $table->timestamps();

            // La caja consulta constantemente "que tengo pendiente"; este es el indice que importa.
            $table->index(['punto_de_venta_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comandos_pos');
    }
};
