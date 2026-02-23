<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Models\Product;
use App\Services\ProductConfigurableService;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    protected ProductConfigurableService $productService;

    public function __construct(ProductConfigurableService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "\n🎨 Creando productos estilo Magento 2...\n\n";

        // Colores disponibles
        $colores = ['Negro', 'Blanco', 'Rojo', 'Azul', 'Verde', 'Gris'];

        // Talles disponibles (ID => Nombre)
        $talles = [
            1 => 'XS',
            2 => 'S',
            3 => 'M',
            4 => 'L',
            5 => 'XL',
            6 => 'XXL',
        ];

        // ==================== PRODUCTOS CONFIGURABLES CON VARIANTES ====================

        echo "📦 PRODUCTOS CONFIGURABLES (con variantes - estilo Magento 2):\n";
        echo str_repeat("-", 60) . "\n";
        echo "Enfoque: Primero crear simples, luego configurable, luego asociar\n\n";

        for ($i = 1; $i <= 5; $i++) {
            // Datos del producto configurable (padre)
            $configurableData = [
                'nombre' => fake()->words(3, true),
                'codigo_interno' => 'CONF-' . fake()->unique()->numberBetween(1000, 9999),
                'descripcion_web' => fake()->sentence(),
                'descripcion_tecnica' => fake()->paragraph(),
                'precio' => fake()->randomFloat(3, 100, 1000),
                'costo' => fake()->randomFloat(3, 50, 500),
                'precio_usd' => fake()->randomFloat(2, 10, 100),
                'publico' => fake()->randomFloat(2, 150, 1200),
                'iva' => 21,
                'stock_critico' => 10,
                'linea' => fake()->numberBetween(1, 10),
                'marca' => fake()->numberBetween(1, 10),
                'familia' => fake()->numberBetween(1, 10),
                'grupo' => fake()->numberBetween(1, 10),
                'articulo' => fake()->numberBetween(1000, 9999),
                'estado' => 1,
                'remitible' => true,
                'publicar_ml' => fake()->boolean(50),
            ];

            // Crear variantes (3 colores x 6 talles = 18 variantes)
            $coloresSeleccionados = fake()->randomElements($colores, 3);
            $variants = [];

            foreach ($coloresSeleccionados as $color) {
                foreach ($talles as $talleId => $talleNombre) {
                    $variants[] = [
                        'color' => $color,
                        'talle_id' => $talleId,
                        'talle_nombre' => $talleNombre,
                        'stock' => fake()->numberBetween(0, 30),
                        'primera' => fake()->numberBetween(0, 20),
                        'segunda' => fake()->numberBetween(0, 10),
                    ];
                }
            }

            // Usar el servicio estilo Magento 2
            $configurable = $this->productService->createConfigurableWithVariants($configurableData, $variants);

            echo "\n✅ Producto configurable #{$i}: {$configurable->nombre}\n";
            echo "   Código: {$configurable->codigo_interno}\n";
            echo "   Tipo: {$configurable->product_type->label()}\n";
            echo "   Vendible: " . ($configurable->es_vendible ? 'Sí' : 'No') . "\n";
            echo "   Total variantes creadas: {$configurable->variants->count()}\n\n";

            foreach ($configurable->variants as $variante) {
                echo "   → {$variante->color} - {$variante->n_talle} | Stock: {$variante->stock}\n";
            }

            echo "\n   📊 Stock total variantes: {$configurable->total_variants_stock}\n";
            echo "   🎨 Colores disponibles: " . implode(', ', $configurable->available_colors) . "\n";
            echo "   📏 Talles disponibles: " . implode(', ', $configurable->available_sizes) . "\n";
        }

        echo "\n" . str_repeat("=", 60) . "\n";

        // ==================== PRODUCTOS SIMPLES INDEPENDIENTES ====================

        echo "\n📦 PRODUCTOS SIMPLES (independientes, no son variantes):\n";
        echo str_repeat("-", 60) . "\n";

        $simples = Product::factory(10)->create();

        foreach ($simples as $simple) {
            echo "✅ {$simple->nombre} | Color: {$simple->color} | Talle: {$simple->n_talle} | Stock: {$simple->stock}\n";
        }

        echo "\n" . str_repeat("=", 60) . "\n";

        // ==================== PRODUCTOS CON ESTADOS ESPECÍFICOS ====================

        echo "\n📦 PRODUCTOS CON ESTADOS ESPECÍFICOS:\n";
        echo str_repeat("-", 60) . "\n";

        $criticos = Product::factory(3)->stockCritico()->create();
        echo "⚠️  {$criticos->count()} productos con stock crítico creados\n";

        $sinStock = Product::factory(2)->sinStock()->create();
        echo "❌ {$sinStock->count()} productos sin stock creados\n";

        $inactivos = Product::factory(2)->inactivo()->create();
        echo "⏸️  {$inactivos->count()} productos inactivos creados\n";

        echo "\n" . str_repeat("=", 60) . "\n";

        // ==================== RESUMEN ====================

        echo "\n📊 RESUMEN:\n";
        echo str_repeat("-", 60) . "\n";

        $totalConfigurables = Product::configurable()->count();
        $totalSimples = Product::simple()->count();
        $totalTopLevel = Product::topLevel()->count();
        $totalVariantes = Product::simple()->whereNotNull('parent_id')->count();

        echo "   Productos Configurables: {$totalConfigurables}\n";
        echo "   Productos Simples (total): {$totalSimples}\n";
        echo "   └─ Independientes: " . ($totalSimples - $totalVariantes) . "\n";
        echo "   └─ Variantes: {$totalVariantes}\n";
        echo "   Productos de nivel superior: {$totalTopLevel}\n";
        echo "   TOTAL: " . Product::count() . "\n";

        echo "\n✅ Seeder completado exitosamente.\n\n";
    }
}
