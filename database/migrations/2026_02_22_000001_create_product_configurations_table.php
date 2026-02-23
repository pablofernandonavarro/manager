<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_configurations', function (Blueprint $table) {
            $table->id();

            // Configuración de códigos internos
            $table->boolean('auto_generate_code')->default(true);
            $table->string('code_format')->default('sequential'); // sequential, timestamp, sku_based, manual
            $table->string('code_prefix')->nullable(); // Ej: PROD-, ART-, SKU-
            $table->string('code_suffix')->nullable(); // Ej: -2026
            $table->integer('code_length')->default(6); // Longitud del número secuencial
            $table->string('code_separator')->default('-'); // Separador entre partes
            $table->integer('code_next_number')->default(1); // Próximo número a usar

            // Configuración de SKU (para productos simples)
            $table->boolean('auto_generate_sku')->default(false);
            $table->string('sku_format')->nullable(); // {MARCA}-{FAMILIA}-{COLOR}-{TALLE}
            $table->boolean('sku_uppercase')->default(true);

            // Configuración de códigos de barras
            $table->boolean('auto_generate_barcode')->default(false);
            $table->string('barcode_format')->default('ean13'); // ean13, code128, upc
            $table->string('barcode_prefix')->nullable();

            // Validaciones y reglas
            $table->boolean('require_unique_code')->default(true);
            $table->boolean('require_unique_barcode')->default(true);
            $table->boolean('require_images')->default(false);
            $table->integer('min_images')->default(1);
            $table->integer('max_images')->default(10);

            // Stock y precios
            $table->boolean('allow_negative_stock')->default(false);
            $table->boolean('track_stock')->default(true);
            $table->decimal('default_stock_critical', 10, 2)->default(10);
            $table->boolean('require_cost')->default(false);
            $table->boolean('require_price')->default(true);
            $table->boolean('auto_calculate_price')->default(false); // Calcular precio desde costo + markup
            $table->decimal('default_markup', 10, 2)->default(50); // % de markup por defecto

            // Impuestos
            $table->decimal('default_tax_rate', 5, 2)->default(21); // IVA por defecto

            // Valores por defecto
            $table->string('default_marca')->nullable();
            $table->string('default_linea')->nullable();
            $table->string('default_familia')->nullable();
            $table->string('default_temporada')->nullable();
            $table->boolean('default_estado')->default(1);
            $table->boolean('default_es_vendible')->default(true);
            $table->boolean('default_remitible')->default(true);

            // Configuración de variantes
            $table->boolean('require_variants_for_configurable')->default(true);
            $table->integer('min_variants')->default(1);
            $table->json('variant_attributes')->nullable(); // ['color', 'talle', 'material']

            // Auditoría
            $table->timestamps();
        });

        // Insertar configuración por defecto
        DB::table('product_configurations')->insert([
            'auto_generate_code' => true,
            'code_format' => 'sequential',
            'code_prefix' => 'PROD',
            'code_suffix' => null,
            'code_length' => 6,
            'code_separator' => '-',
            'code_next_number' => 1,
            'auto_generate_sku' => false,
            'sku_format' => '{CODIGO}-{COLOR}-{TALLE}',
            'sku_uppercase' => true,
            'auto_generate_barcode' => false,
            'barcode_format' => 'ean13',
            'barcode_prefix' => null,
            'require_unique_code' => true,
            'require_unique_barcode' => true,
            'require_images' => false,
            'min_images' => 1,
            'max_images' => 10,
            'allow_negative_stock' => false,
            'track_stock' => true,
            'default_stock_critical' => 10,
            'require_cost' => false,
            'require_price' => true,
            'auto_calculate_price' => false,
            'default_markup' => 50,
            'default_tax_rate' => 21,
            'default_marca' => null,
            'default_linea' => null,
            'default_familia' => null,
            'default_temporada' => null,
            'default_estado' => 1,
            'default_es_vendible' => true,
            'default_remitible' => true,
            'require_variants_for_configurable' => true,
            'min_variants' => 1,
            'variant_attributes' => json_encode(['color', 'n_talle']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_configurations');
    }
};
