<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codigos_instalacion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('punto_de_venta_id')->constrained('puntos_de_venta')->cascadeOnDelete();
            $table->string('codigo', 20)->unique();
            $table->timestamp('expira_at');
            $table->timestamp('usado_at')->nullable();
            $table->string('usado_ip', 45)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['punto_de_venta_id', 'usado_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_instalacion');
    }
};
