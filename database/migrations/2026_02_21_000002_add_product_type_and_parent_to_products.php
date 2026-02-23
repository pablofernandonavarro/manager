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
        Schema::table('products', function (Blueprint $table) {
            // Tipo de producto (simple o configurable)
            $table->string('product_type', 20)->default('simple')->after('id')->index();

            // ID del producto padre (para productos simples que son variantes)
            $table->foreignId('parent_id')->nullable()->after('product_type')->constrained('products')->nullOnDelete();

            // Índice compuesto para búsqueda de variantes
            $table->index(['parent_id', 'product_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id', 'product_type']);
            $table->dropColumn(['product_type', 'parent_id']);
        });
    }
};
