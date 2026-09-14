<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un remito se puede recibir desde el Manager (un usuario) o desde la caja de la
 * sucursal destino. Sin esto no había forma de saber quién dio por recibida la mercadería.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remitos', function (Blueprint $table): void {
            $table->foreignId('confirmado_por_user_id')->nullable()->after('confirmado_at')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('confirmado_por_punto_de_venta_id')->nullable()->after('confirmado_por_user_id')
                ->constrained('puntos_de_venta')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('remitos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('confirmado_por_punto_de_venta_id');
            $table->dropConstrainedForeignId('confirmado_por_user_id');
        });
    }
};
