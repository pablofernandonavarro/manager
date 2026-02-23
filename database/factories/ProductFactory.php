<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state (producto simple por defecto).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->words(3, true);
        $codigoInterno = 'ART-' . fake()->unique()->numberBetween(1000, 9999);

        return [
            // Tipo de producto
            'product_type' => ProductType::SIMPLE,
            'parent_id' => null,

            // Información básica
            'nombre' => ucfirst($nombre),
            'codigo_interno' => $codigoInterno,
            'busqueda' => strtolower($nombre . ' ' . $codigoInterno),
            'codigo_barras' => fake()->ean13(),
            'descripcion_web' => fake()->sentence(),
            'descripcion_tecnica' => fake()->paragraph(),
            'color' => fake()->randomElement(['Negro', 'Blanco', 'Rojo', 'Azul', 'Verde', 'Gris']),
            'n_color' => fake()->randomElement(['Negro', 'Blanco', 'Rojo', 'Azul', 'Verde', 'Gris']),
            'metadata_detalle2' => fake()->numberBetween(1, 10), // Talle ID
            'n_talle' => fake()->randomElement(['XS', 'S', 'M', 'L', 'XL', 'XXL']),
            'peso' => fake()->randomFloat(3, 0.1, 5),
            'dimension' => fake()->randomElement(['S', 'M', 'L', 'XL']),

            // Precios y costos
            'precio' => fake()->randomFloat(3, 100, 10000),
            'costo' => fake()->randomFloat(3, 50, 5000),
            'precio_usd' => fake()->randomFloat(2, 10, 500),
            'costo_usd' => fake()->randomFloat(3, 5, 250),
            'publico' => fake()->randomFloat(2, 150, 12000),
            'costo_produccion' => fake()->randomFloat(3, 40, 4000),
            'costo_target' => fake()->randomFloat(3, 120, 11000),
            'markup' => fake()->randomFloat(3, 1.5, 3),
            'iva' => fake()->randomElement([21, 10.5, 0]),

            // Stock
            'stock' => fake()->numberBetween(0, 100),
            'stock_critico' => fake()->numberBetween(5, 20),
            'stock_comprometido' => 0,
            'reservados' => 0,
            'primera' => fake()->numberBetween(0, 50),
            'segunda' => fake()->numberBetween(0, 20),

            // Clasificación (IDs)
            'linea' => fake()->numberBetween(1, 10),
            'marca' => fake()->numberBetween(1, 10),
            'familia' => fake()->numberBetween(1, 10),
            'grupo' => fake()->numberBetween(1, 10),
            'subgrupo' => fake()->numberBetween(1, 10),
            'temporada' => fake()->numberBetween(1, 10),
            'target' => fake()->numberBetween(1, 10),
            'edad' => fake()->numberBetween(1, 10),
            'procedencia' => fake()->numberBetween(1, 10),

            // Nombres de clasificación (strings)
            'n_grupo' => fake()->randomElement(['Hombre', 'Mujer', 'Unisex']),
            'n_subgrupo' => fake()->randomElement(['Adulto', 'Joven', 'Niño']),
            'n_target' => fake()->randomElement(['Premium', 'Standard', 'Económico']),
            'n_temporada' => fake()->randomElement(['Verano 2026', 'Invierno 2026', 'Otoño 2026']),
            'n_procedencia' => fake()->randomElement(['Nacional', 'Importado', 'Regional']),

            // Producción
            'modelista' => fake()->name(),
            'molde' => 1,
            'progresion' => 1,
            'cantidad_a_fabricar' => fake()->numberBetween(10, 100),
            'complejidad' => fake()->numberBetween(1, 5),

            // Flags booleanos
            'exportar' => fake()->boolean(30),
            'es_vendible' => true,
            'remitible' => true,
            'estado' => 1, // activo
            'destacado_web' => fake()->boolean(20),
            'publicar_ml' => fake()->boolean(50),

            // Unidades de medida
            'unidad_medida' => 1,
            'cantidad_um_x_presentacion' => 1,

            // Metadata
            'articulo' => fake()->numberBetween(1000, 9999),
            'metadata_detalle1' => null,

            // E-commerce
            'stock_ml' => fake()->numberBetween(0, 50),
            'url_ecomm' => fake()->boolean(30) ? fake()->url() : null,

            // Fechas
            'fecha_costo' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }

    /**
     * Producto configurable (estilo Magento).
     * No se vende directamente, solo agrupa variantes.
     */
    public function configurable(): static
    {
        return $this->state(fn (array $attributes) => [
            'product_type' => ProductType::CONFIGURABLE,
            'parent_id' => null,
            'color' => null,
            'n_color' => null,
            'metadata_detalle2' => null, // Sin talle específico
            'n_talle' => null,
            'stock' => 0, // El stock está en las variantes
            'primera' => 0,
            'segunda' => 0,
            'es_vendible' => false, // No se vende, solo sus variantes
        ]);
    }

    /**
     * Producto simple (variante) hijo de un configurable.
     */
    public function variantOf(Product $configurable, string $color, int $talleId, string $talleNombre): static
    {
        return $this->state(function (array $attributes) use ($configurable, $color, $talleId, $talleNombre) {
            $codigoInterno = $configurable->codigo_interno . '-' . strtoupper(substr($color, 0, 3)) . '-' . $talleNombre;

            return [
                'product_type' => ProductType::SIMPLE,
                'parent_id' => $configurable->id,
                'nombre' => $configurable->nombre . ' - ' . ucfirst($color) . ' - ' . $talleNombre,
                'codigo_interno' => $codigoInterno,
                'busqueda' => strtolower($configurable->nombre . ' ' . $color . ' ' . $talleNombre . ' ' . $codigoInterno),
                'color' => ucfirst($color),
                'metadata_detalle2' => $talleId,
                'n_talle' => $talleNombre,
                'codigo_barras' => fake()->ean13(),

                // Hereda datos del configurable
                'descripcion_web' => $configurable->descripcion_web,
                'descripcion_tecnica' => $configurable->descripcion_tecnica,
                'peso' => $configurable->peso,
                'dimension' => $configurable->dimension,
                'precio' => $configurable->precio,
                'costo' => $configurable->costo,
                'precio_usd' => $configurable->precio_usd,
                'costo_usd' => $configurable->costo_usd,
                'publico' => $configurable->publico,
                'costo_produccion' => $configurable->costo_produccion,
                'costo_target' => $configurable->costo_target,
                'markup' => $configurable->markup,
                'iva' => $configurable->iva,

                // Stock individual por variante
                'stock' => fake()->numberBetween(0, 30),
                'stock_critico' => $configurable->stock_critico,
                'stock_comprometido' => 0,
                'reservados' => 0,
                'primera' => fake()->numberBetween(0, 20),
                'segunda' => fake()->numberBetween(0, 10),

                // Clasificación heredada
                'linea' => $configurable->linea,
                'marca' => $configurable->marca,
                'familia' => $configurable->familia,
                'grupo' => $configurable->grupo,
                'subgrupo' => $configurable->subgrupo,
                'temporada' => $configurable->temporada,
                'target' => $configurable->target,
                'edad' => $configurable->edad,
                'procedencia' => $configurable->procedencia,
                'n_grupo' => $configurable->n_grupo,
                'n_subgrupo' => $configurable->n_subgrupo,
                'n_target' => $configurable->n_target,
                'n_temporada' => $configurable->n_temporada,
                'n_procedencia' => $configurable->n_procedencia,
                'n_color' => ucfirst($color),

                // Producción heredada
                'modelista' => $configurable->modelista,
                'molde' => $configurable->molde,
                'progresion' => $configurable->progresion,
                'complejidad' => $configurable->complejidad,

                // Flags heredados
                'exportar' => $configurable->exportar,
                'es_vendible' => true, // Las variantes SÍ se venden
                'remitible' => $configurable->remitible,
                'estado' => $configurable->estado,
                'destacado_web' => $configurable->destacado_web,
                'publicar_ml' => $configurable->publicar_ml,

                // Metadata - hijo tiene relación con configurable
                'articulo' => $configurable->articulo,
                'metadata_detalle1' => null,

                // E-commerce
                'stock_ml' => fake()->numberBetween(0, 20),
                'color_ml' => ucfirst($color),

                // Fechas
                'fecha_costo' => $configurable->fecha_costo,
            ];
        });
    }

    /**
     * Producto activo.
     */
    public function activo(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 1,
            'es_vendible' => $attributes['product_type'] === ProductType::SIMPLE,
        ]);
    }

    /**
     * Producto inactivo.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 0,
            'es_vendible' => false,
        ]);
    }

    /**
     * Producto con stock crítico.
     */
    public function stockCritico(): static
    {
        return $this->state(function (array $attributes) {
            $stockCritico = $attributes['stock_critico'] ?? 10;

            return [
                'stock' => fake()->numberBetween(0, $stockCritico),
            ];
        });
    }

    /**
     * Producto sin stock.
     */
    public function sinStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
            'primera' => 0,
            'segunda' => 0,
        ]);
    }
}
