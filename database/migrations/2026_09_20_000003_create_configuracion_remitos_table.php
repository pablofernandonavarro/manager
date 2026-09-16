<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_remitos', function (Blueprint $table): void {
            $table->id();
            $table->boolean('ruta_directa')->default(true);
            $table->string('destino_rechazados')->default('origen'); // origen|manager|elegir
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_remitos');
    }
};
