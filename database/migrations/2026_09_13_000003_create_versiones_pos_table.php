<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versiones_pos', function (Blueprint $table): void {
            $table->id();
            $table->string('version', 40)->unique();
            $table->string('archivo');
            // sha256 del zip. La caja lo verifica antes de aplicar nada: sin esto, un
            // paquete corrupto o alterado se instalaria igual en todas las terminales.
            $table->string('hash', 64);
            $table->unsignedBigInteger('tamano');
            $table->text('notas')->nullable();
            $table->boolean('vigente')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versiones_pos');
    }
};
