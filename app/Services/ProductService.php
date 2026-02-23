<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\Product;

/**
 * Servicio principal para creación de productos.
 * Similar a Magento 2 ProductRepositoryInterface.
 */
class ProductService
{
    /**
     * Crea un producto SIMPLE (vendible, con inventario).
     *
     * Ejemplo:
     * $product = $productService->createSimple([
     *     'nombre' => 'Remera Negra Talle M',
     *     'codigo_interno' => 'REM-NEG-M',
     *     'precio' => 500,
     *     'stock' => 10,
     *     'color' => 'Negro',
     *     'n_talle' => 'M',
     * ]);
     */
    public function createSimple(array $data): Product
    {
        return Product::create(array_merge($data, [
            'product_type' => ProductType::SIMPLE,
            'parent_id' => null,
            'es_vendible' => $data['es_vendible'] ?? true,
            'estado' => $data['estado'] ?? 1,
        ]));
    }

    /**
     * Crea un producto CONFIGURABLE (no vendible, agrupa variantes).
     *
     * Ejemplo:
     * $product = $productService->createConfigurable([
     *     'nombre' => 'Remera Deportiva',
     *     'codigo_interno' => 'REM-001',
     *     'precio' => 500,
     * ]);
     */
    public function createConfigurable(array $data): Product
    {
        return Product::create(array_merge($data, [
            'product_type' => ProductType::CONFIGURABLE,
            'parent_id' => null,
            'es_vendible' => false, // Los configurables NO se venden
            'stock' => 0, // Stock está en las variantes
            'color' => null,
            'n_color' => null,
            'metadata_detalle2' => null,
            'n_talle' => null,
            'estado' => $data['estado'] ?? 1,
        ]));
    }

    /**
     * Crea un producto CONFIGURABLE con sus variantes (estilo Magento 2).
     *
     * Proceso:
     * 1. Crea productos SIMPLES (variantes)
     * 2. Crea producto CONFIGURABLE (padre)
     * 3. Asocia las variantes al configurable
     *
     * Ejemplo:
     * $configurable = $productService->createConfigurableWithVariants(
     *     configurableData: [
     *         'nombre' => 'Remera Deportiva',
     *         'codigo_interno' => 'REM-001',
     *         'precio' => 500,
     *     ],
     *     variants: [
     *         ['color' => 'Negro', 'talle' => 'S', 'stock' => 10],
     *         ['color' => 'Negro', 'talle' => 'M', 'stock' => 15],
     *         ['color' => 'Blanco', 'talle' => 'S', 'stock' => 8],
     *     ]
     * );
     */
    public function createConfigurableWithVariants(array $configurableData, array $variants): Product
    {
        $service = app(ProductConfigurableService::class);
        return $service->createConfigurableWithVariants($configurableData, $variants);
    }

    /**
     * Agrega variantes a un producto configurable existente.
     */
    public function addVariants(Product $configurable, array $variants): void
    {
        if (!$configurable->isConfigurable()) {
            throw new \InvalidArgumentException('El producto debe ser de tipo CONFIGURABLE');
        }

        $service = app(ProductConfigurableService::class);
        $service->addVariantsToConfigurable($configurable, $variants);
    }

    /**
     * Convierte un producto simple en variante de un configurable.
     */
    public function attachToConfigurable(Product $simple, Product $configurable): Product
    {
        if (!$simple->isSimple()) {
            throw new \InvalidArgumentException('El producto debe ser de tipo SIMPLE');
        }

        if (!$configurable->isConfigurable()) {
            throw new \InvalidArgumentException('El padre debe ser de tipo CONFIGURABLE');
        }

        $simple->update(['parent_id' => $configurable->id]);

        return $simple->fresh();
    }

    /**
     * Convierte una variante en producto simple independiente.
     */
    public function detachFromConfigurable(Product $variant): Product
    {
        if (!$variant->isSimple() || !$variant->parent_id) {
            throw new \InvalidArgumentException('El producto debe ser una variante con padre asignado');
        }

        $variant->update(['parent_id' => null]);

        return $variant->fresh();
    }
}
