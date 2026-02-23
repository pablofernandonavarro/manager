<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Servicio para manejar productos configurables estilo Magento 2.
 *
 * En Magento 2:
 * - Primero se crean los productos SIMPLES (variantes) con SKUs únicos
 * - Luego se crea el producto CONFIGURABLE (padre)
 * - Se asocian los simples al configurable mediante atributos
 */
class ProductConfigurableService
{
    /**
     * Crea un producto configurable con sus variantes (estilo Magento 2).
     *
     * @param array $configurableData Datos del producto configurable (padre)
     * @param array $variants Array de variantes: [['color' => 'Rojo', 'talle' => 'M', 'stock' => 10], ...]
     * @return Product El producto configurable creado con sus variantes asociadas
     */
    public function createConfigurableWithVariants(array $configurableData, array $variants): Product
    {
        // Paso 1: Crear productos SIMPLES primero (como Magento 2)
        $simpleProducts = collect($variants)->map(function ($variantData) use ($configurableData) {
            return $this->createSimpleProduct($configurableData, $variantData);
        });

        // Paso 2: Crear producto CONFIGURABLE (padre)
        $configurable = $this->createConfigurableProduct($configurableData);

        // Paso 3: Asociar los productos simples al configurable
        $this->associateVariants($configurable, $simpleProducts);

        return $configurable->load('variants');
    }

    /**
     * Crea un producto simple (variante).
     */
    protected function createSimpleProduct(array $configurableData, array $variantData): Product
    {
        $codigoInterno = $configurableData['codigo_interno']
            . '-' . strtoupper(substr($variantData['color'], 0, 3))
            . '-' . $variantData['talle_nombre'];

        return Product::create([
            'product_type' => ProductType::SIMPLE,
            'parent_id' => null, // Se asignará después cuando se cree el configurable

            // Información básica heredada + específica de variante
            'nombre' => $configurableData['nombre'] . ' - ' . $variantData['color'] . ' - ' . $variantData['talle_nombre'],
            'codigo_interno' => $codigoInterno,
            'busqueda' => strtolower($configurableData['nombre'] . ' ' . $variantData['color'] . ' ' . $variantData['talle_nombre']),
            'codigo_barras' => $variantData['codigo_barras'] ?? null,

            // Atributos de variante
            'color' => $variantData['color'],
            'n_color' => $variantData['color'],
            'metadata_detalle2' => $variantData['talle_id'],
            'n_talle' => $variantData['talle_nombre'],

            // Descripción heredada
            'descripcion_web' => $configurableData['descripcion_web'] ?? null,
            'descripcion_tecnica' => $configurableData['descripcion_tecnica'] ?? null,

            // Stock específico de la variante
            'stock' => $variantData['stock'] ?? 0,
            'stock_critico' => $configurableData['stock_critico'] ?? 10,
            'primera' => $variantData['primera'] ?? 0,
            'segunda' => $variantData['segunda'] ?? 0,

            // Precios heredados del configurable
            'precio' => $configurableData['precio'] ?? 0,
            'costo' => $configurableData['costo'] ?? 0,
            'precio_usd' => $configurableData['precio_usd'] ?? null,
            'publico' => $configurableData['publico'] ?? null,
            'iva' => $configurableData['iva'] ?? 21,

            // Clasificación heredada
            'linea' => $configurableData['linea'] ?? null,
            'marca' => $configurableData['marca'] ?? null,
            'familia' => $configurableData['familia'] ?? null,
            'grupo' => $configurableData['grupo'] ?? null,
            'subgrupo' => $configurableData['subgrupo'] ?? null,
            'temporada' => $configurableData['temporada'] ?? null,

            // Metadata
            'articulo' => $configurableData['articulo'] ?? null,

            // Flags
            'es_vendible' => true, // Los simples SÍ se venden
            'remitible' => $configurableData['remitible'] ?? true,
            'estado' => $configurableData['estado'] ?? 1,
            'publicar_ml' => $configurableData['publicar_ml'] ?? false,
        ]);
    }

    /**
     * Crea un producto configurable (padre).
     */
    protected function createConfigurableProduct(array $data): Product
    {
        return Product::create([
            'product_type' => ProductType::CONFIGURABLE,
            'parent_id' => null,

            // Información básica
            'nombre' => $data['nombre'],
            'codigo_interno' => $data['codigo_interno'],
            'busqueda' => strtolower($data['nombre'] . ' ' . $data['codigo_interno']),
            'codigo_barras' => $data['codigo_barras'] ?? null,

            // Sin color/talle específico (es el padre)
            'color' => null,
            'n_color' => null,
            'metadata_detalle2' => null,
            'n_talle' => null,

            // Descripción
            'descripcion_web' => $data['descripcion_web'] ?? null,
            'descripcion_tecnica' => $data['descripcion_tecnica'] ?? null,

            // Sin stock propio (está en las variantes)
            'stock' => 0,
            'stock_critico' => $data['stock_critico'] ?? 10,
            'primera' => 0,
            'segunda' => 0,

            // Precios base
            'precio' => $data['precio'] ?? 0,
            'costo' => $data['costo'] ?? 0,
            'precio_usd' => $data['precio_usd'] ?? null,
            'publico' => $data['publico'] ?? null,
            'iva' => $data['iva'] ?? 21,

            // Clasificación
            'linea' => $data['linea'] ?? null,
            'marca' => $data['marca'] ?? null,
            'familia' => $data['familia'] ?? null,
            'grupo' => $data['grupo'] ?? null,
            'subgrupo' => $data['subgrupo'] ?? null,
            'temporada' => $data['temporada'] ?? null,

            // Metadata
            'articulo' => $data['articulo'] ?? null,

            // Flags
            'es_vendible' => false, // El configurable NO se vende, solo sus variantes
            'remitible' => $data['remitible'] ?? true,
            'estado' => $data['estado'] ?? 1,
            'publicar_ml' => $data['publicar_ml'] ?? false,
        ]);
    }

    /**
     * Asocia productos simples a un configurable (asigna parent_id).
     */
    protected function associateVariants(Product $configurable, Collection $simpleProducts): void
    {
        foreach ($simpleProducts as $simple) {
            $simple->update(['parent_id' => $configurable->id]);
        }
    }

    /**
     * Agrega variantes adicionales a un configurable existente.
     */
    public function addVariantsToConfigurable(Product $configurable, array $variants): Collection
    {
        if (!$configurable->isConfigurable()) {
            throw new \InvalidArgumentException('El producto debe ser de tipo configurable');
        }

        $configurableData = $configurable->toArray();

        $newVariants = collect($variants)->map(function ($variantData) use ($configurableData) {
            $simple = $this->createSimpleProduct($configurableData, $variantData);
            $simple->update(['parent_id' => $configurableData['id']]);
            return $simple;
        });

        return $newVariants;
    }

    /**
     * Crea variantes para un producto configurable ya existente.
     */
    public function createVariantsForExisting(Product $configurable, array $configurableData, array $variants): void
    {
        if (!$configurable->isConfigurable()) {
            throw new \InvalidArgumentException('El producto debe ser de tipo configurable');
        }

        $createdCount = 0;
        foreach ($variants as $variantData) {
            $variantData['talle_id'] = $variantData['size_id'];
            $variantData['talle_nombre'] = $variantData['size_name'];

            $simple = $this->createSimpleProduct($configurableData, $variantData);
            $simple->update(['parent_id' => $configurable->id]);
            $createdCount++;
        }

        \Log::info('Variantes creadas', [
            'configurable_id' => $configurable->id,
            'variants_created' => $createdCount,
            'variants_in_db' => $configurable->fresh()->variants()->count(),
        ]);
    }

    /**
     * Desasocia una variante de su configurable (la convierte en producto simple independiente).
     */
    public function detachVariant(Product $variant): Product
    {
        if (!$variant->isSimple() || !$variant->parent_id) {
            throw new \InvalidArgumentException('El producto debe ser una variante con padre asignado');
        }

        $variant->update(['parent_id' => null]);

        return $variant;
    }
}
