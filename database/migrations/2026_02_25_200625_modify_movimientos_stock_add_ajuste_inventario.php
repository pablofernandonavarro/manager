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
        Schema::table('movimientos_stock', function (Blueprint $table): void {
            // Hacer nullable punto_de_venta_id (ajustes del manager no tienen PDV)
            $table->foreignId('punto_de_venta_id')->nullable()->change();

            // Referenciar el ajuste de inventario que originó el movimiento
            $table->foreignId('ajuste_inventario_id')
                ->nullable()
                ->after('punto_de_venta_id')
                ->constrained('ajustes_inventario')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_stock', function (Blueprint $table): void {
            $table->dropForeign(['ajuste_inventario_id']);
            $table->dropColumn('ajuste_inventario_id');
            $table->foreignId('punto_de_venta_id')->nullable(false)->change();
        });
    }
};
