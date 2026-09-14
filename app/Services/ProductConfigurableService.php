<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
    public function __construct(
        private readonly StockInicialService $stockInicial,
    ) {}

    /**
     * Crea un producto configurable con sus variantes (estilo Magento 2).
     *
     * El `stock` de cada variante entra a `$sucursalStockId` (stock_sucursal + movimiento).
     * Sin sucursal no se carga stock: nunca queda en products.stock sin sucursal.
     *
     * @param  array  $configurableData  Datos del producto configurable (padre)
     * @param  array  $variants  Array de variantes: [['attributes' => [...], 'stock' => 10, 'codigo_barras' => ?], ...]
     * @return Product El producto configurable creado con sus variantes asociadas
     */
    public function createConfigurableWithVariants(array $configurableData, array $variants, ?int $sucursalStockId = null): Product
    {
        return DB::transaction(function () use ($configurableData, $variants, $sucursalStockId) {
            // Paso 1: Crear productos SIMPLES primero (como Magento 2)
            $simpleProducts = collect($variants)->map(function ($variantData) use ($configurableData, $sucursalStockId) {
                return $this->createSimpleProduct($configurableData, $variantData, $sucursalStockId);
            });

            // Paso 2: Crear producto CONFIGURABLE (padre)
            $configurable = $this->createConfigurableProduct($configurableData);

            // Paso 3: Asociar los productos simples al configurable
            $this->associateVariants($configurable, $simpleProducts);

            return $configurable->load('variants');
        });
    }

    /**
     * Crea un producto simple (variante) con atributos dinámicos.
     *
     * @param  array<string, mixed>  $configurableData
     * @param  array{attributes?: list<array{slug: string, product_column: string|null, value: string}>, stock?: int}  $variantData
     */
    protected function createSimpleProduct(array $configurableData, array $variantData, ?int $sucursalStockId = null): Product
    {
        $productData = [];
        $attrExtra = [];
        $allValues = [];

        foreach ($variantData['attributes'] ?? [] as $attr) {
            $allValues[] = $attr['value'];
            if ($attr['product_column']) {
                $productData[$attr['product_column']] = $attr['value'];
            } else {
                $attrExtra[$attr['slug']] = $attr['value'];
            }
        }

        $productData['atributos_extra'] = $attrExtra ?: null;

        // mb_*: con substr() un valor como "Ñandú" o "Único" cortaba un carácter por la mitad.
        $suffix = implode('-', array_map(
            fn ($a) => mb_strtoupper(mb_substr(trim((string) $a['value']), 0, 3)),
            $variantData['attributes'] ?? []
        ));

        $productData['nombre'] = $configurableData['nombre']
            .(count($allValues) > 0 ? ' - '.implode(' - ', $allValues) : '');

        $productData['codigo_interno'] = $this->codigoLibre($configurableData['codigo_interno']
            .($suffix ? '-'.$suffix : ''));

        $variante = Product::create(array_merge($productData, [
            'product_type' => ProductType::SIMPLE,
            'parent_id' => null,
            'codigo_barras' => $variantData['codigo_barras'] ?? null,
            'descripcion_web' => $configurableData['descripcion_web'] ?? null,
            'descripcion_tecnica' => $configurableData['descripcion_tecnica'] ?? null,
            // La suma de stock_sucursal; lo carga StockInicialService.
            'stock' => 0,
            'stock_critico' => $configurableData['stock_critico'] ?? 10,
            'primera' => $variantData['primera'] ?? 0,
            'segunda' => $variantData['segunda'] ?? 0,
            'precio' => $configurableData['precio'] ?? 0,
            'costo' => $configurableData['costo'] ?? 0,
            'precio_usd' => $configurableData['precio_usd'] ?? null,
            'publico' => $configurableData['publico'] ?? null,
            'iva' => $configurableData['iva'] ?? 21,
            'linea' => $configurableData['linea'] ?? null,
            'marca' => $configurableData['marca'] ?? null,
            'familia' => $configurableData['familia'] ?? null,
            'grupo' => $configurableData['grupo'] ?? null,
            'subgrupo' => $configurableData['subgrupo'] ?? null,
            'temporada' => $configurableData['temporada'] ?? null,
            'articulo' => $configurableData['articulo'] ?? null,
            'es_vendible' => true,
            'remitible' => $configurableData['remitible'] ?? true,
            'estado' => $configurableData['estado'] ?? 1,
            'publicar_ml' => $configurableData['publicar_ml'] ?? false,
        ]));

        if ($sucursalStockId) {
            $this->stockInicial->cargar($variante, $sucursalStockId, (int) ($variantData['stock'] ?? 0));
        }

        return $variante;
    }

    /**
     * "Azul" y "Azul marino" dan el mismo sufijo (AZU): el segundo SKU sale con -2 en vez
     * de repetir el código y confundir a la caja.
     */
    private function codigoLibre(string $codigo): string
    {
        $candidato = $codigo;

        for ($n = 2; Product::withTrashed()->where('codigo_interno', $candidato)->exists(); $n++) {
            $candidato = "{$codigo}-{$n}";
        }

        return $candidato;
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
    public function addVariantsToConfigurable(Product $configurable, array $variants, ?int $sucursalStockId = null): Collection
    {
        if (! $configurable->isConfigurable()) {
            throw new \InvalidArgumentException('El producto debe ser de tipo configurable');
        }

        $configurableData = $configurable->toArray();

        return DB::transaction(fn () => collect($variants)->map(function ($variantData) use ($configurableData, $sucursalStockId) {
            $simple = $this->createSimpleProduct($configurableData, $variantData, $sucursalStockId);
            $simple->update(['parent_id' => $configurableData['id']]);

            return $simple;
        }));
    }

    /**
     * Crea variantes para un producto configurable ya existente.
     */
    public function createVariantsForExisting(Product $configurable, array $configurableData, array $variants, ?int $sucursalStockId = null): void
    {
        if (! $configurable->isConfigurable()) {
            throw new \InvalidArgumentException('El producto debe ser de tipo configurable');
        }

        DB::transaction(function () use ($configurable, $configurableData, $variants, $sucursalStockId): void {
            foreach ($variants as $variantData) {
                $simple = $this->createSimpleProduct($configurableData, $variantData, $sucursalStockId);
                $simple->update(['parent_id' => $configurable->id]);
            }
        });
    }

    /**
     * Desasocia una variante de su configurable (la convierte en producto simple independiente).
     */
    public function detachVariant(Product $variant): Product
    {
        if (! $variant->isSimple() || ! $variant->parent_id) {
            throw new \InvalidArgumentException('El producto debe ser una variante con padre asignado');
        }

        $variant->update(['parent_id' => null]);

        return $variant;
    }
}
