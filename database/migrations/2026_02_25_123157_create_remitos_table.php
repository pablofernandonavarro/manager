<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remitos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sucursal_origen_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('sucursal_destino_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('estado', ['remitido', 'confirmado', 'cancelado'])->default('remitido');
            $table->text('observaciones')->nullable();
            $table->timestamp('remitido_at');
            $table->timestamp('confirmado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remitos');
    }
};
