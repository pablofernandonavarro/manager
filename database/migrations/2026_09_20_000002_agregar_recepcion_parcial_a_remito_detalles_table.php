<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remito_detalles', function (Blueprint $table): void {
            $table->integer('cantidad_recibida')->nullable()->after('cantidad');
            $table->integer('cantidad_rechazada')->nullable()->after('cantidad_recibida');
        });
    }

    public function down(): void
    {
        Schema::table('remito_detalles', function (Blueprint $table): void {
            $table->dropColumn(['cantidad_rechazada', 'cantidad_recibida']);
        });
    }
};
