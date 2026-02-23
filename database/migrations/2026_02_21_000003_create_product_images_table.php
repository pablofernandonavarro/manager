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
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('path');
            $table->string('label')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_base')->default(false);
            $table->boolean('is_small')->default(false);
            $table->boolean('is_thumbnail')->default(false);
            $table->boolean('is_swatch')->default(false);
            $table->boolean('disabled')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
