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
        Schema::table('product_configurations', function (Blueprint $table) {
            $table->boolean('child_inherits_parent_images')->default(true)->after('max_images');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_configurations', function (Blueprint $table) {
            $table->dropColumn('child_inherits_parent_images');
        });
    }
};
