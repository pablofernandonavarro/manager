# Guía de Productos - Sistema estilo Magento 2

## Tipos de Productos

### 1. Producto SIMPLE
- ✅ **Vendible directamente**
- ✅ Tiene inventario (stock)
- ✅ Tiene SKU único
- ✅ Puede tener color/talle específico
- ✅ Puede existir independiente O como variante de un configurable

### 2. Producto CONFIGURABLE
- ❌ **NO vendible directamente** (solo agrupa variantes)
- ❌ NO tiene stock propio (el stock está en las variantes)
- ✅ Tiene SKU único
- ✅ Define precio base
- ✅ Agrupa productos simples (variantes)

---

## ¿Dónde se determina el tipo?

El tipo de producto se determina **al momento de la creación** mediante el campo `product_type`:

```php
// OPCIÓN 1: Crear directamente con Product::create()
Product::create([
    'product_type' => ProductType::SIMPLE,  // ← AQUÍ se determina
    'nombre' => 'Remera Negra M',
    'stock' => 10,
    'es_vendible' => true,
]);

Product::create([
    'product_type' => ProductType::CONFIGURABLE,  // ← AQUÍ se determina
    'nombre' => 'Remera Deportiva',
    'stock' => 0,
    'es_vendible' => false,
]);
```

---

## Ejemplos de Uso

### CASO 1: Crear Producto Simple Independiente

```php
use App\Services\ProductService;

$productService = app(ProductService::class);

// Producto simple vendible (ej: accesorio, producto único)
$producto = $productService->createSimple([
    'nombre' => 'Gorra Nike',
    'codigo_interno' => 'GORRA-001',
    'precio' => 300,
    'costo' => 150,
    'stock' => 25,
    'color' => 'Negra',
    'iva' => 21,
    'linea' => 1,
    'marca' => 2,
]);

// $producto->isSimple() → true
// $producto->isConfigurable() → false
// $producto->es_vendible → true
// $producto->parent_id → null
```

### CASO 2: Crear Producto Configurable con Variantes (Magento 2 style)

```php
use App\Services\ProductService;

$productService = app(ProductService::class);

// Esto creará:
// 1. Primero: 6 productos SIMPLES (variantes)
// 2. Luego: 1 producto CONFIGURABLE (padre)
// 3. Finalmente: Asocia los simples al configurable

$configurable = $productService->createConfigurableWithVariants(
    configurableData: [
        'nombre' => 'Remera Deportiva',
        'codigo_interno' => 'REM-001',
        'descripcion_web' => 'Remera deportiva de alta calidad',
        'precio' => 500,
        'costo' => 250,
        'iva' => 21,
        'linea' => 1,
        'marca' => 2,
        'articulo' => 1001,
    ],
    variants: [
        [
            'color' => 'Negro',
            'talle_id' => 1,
            'talle_nombre' => 'S',
            'stock' => 10,
        ],
        [
            'color' => 'Negro',
            'talle_id' => 2,
            'talle_nombre' => 'M',
            'stock' => 15,
        ],
        [
            'color' => 'Negro',
            'talle_id' => 3,
            'talle_nombre' => 'L',
            'stock' => 12,
        ],
        [
            'color' => 'Blanco',
            'talle_id' => 1,
            'talle_nombre' => 'S',
            'stock' => 8,
        ],
        [
            'color' => 'Blanco',
            'talle_id' => 2,
            'talle_nombre' => 'M',
            'stock' => 20,
        ],
        [
            'color' => 'Blanco',
            'talle_id' => 3,
            'talle_nombre' => 'L',
            'stock' => 18,
        ],
    ]
);

// Resultado:
// $configurable->isConfigurable() → true
// $configurable->es_vendible → false
// $configurable->stock → 0
// $configurable->variants->count() → 6
// $configurable->total_variants_stock → 83 (suma de todas las variantes)
// $configurable->available_colors → ['Negro', 'Blanco']
// $configurable->available_sizes → ['S', 'M', 'L']

// Cada variante es un producto simple:
$variante = $configurable->variants->first();
// $variante->isSimple() → true
// $variante->parent_id → (ID del configurable)
// $variante->es_vendible → true
// $variante->stock → 10
// $variante->nombre → "Remera Deportiva - Negro - S"
```

### CASO 3: Crear Configurable Vacío y Agregar Variantes Después

```php
use App\Services\ProductService;

$productService = app(ProductService::class);

// Paso 1: Crear configurable vacío
$configurable = $productService->createConfigurable([
    'nombre' => 'Pantalón Deportivo',
    'codigo_interno' => 'PANT-001',
    'precio' => 800,
    'costo' => 400,
]);

// Paso 2: Agregar variantes más tarde
$productService->addVariants($configurable, [
    ['color' => 'Azul', 'talle_id' => 3, 'talle_nombre' => 'M', 'stock' => 10],
    ['color' => 'Azul', 'talle_id' => 4, 'talle_nombre' => 'L', 'stock' => 15],
]);
```

### CASO 4: Convertir Simple Independiente en Variante

```php
use App\Services\ProductService;

$productService = app(ProductService::class);

// Tengo un simple independiente
$simple = $productService->createSimple([
    'nombre' => 'Remera Roja XL',
    'codigo_interno' => 'REM-ROJ-XL',
    'precio' => 500,
    'stock' => 5,
    'color' => 'Roja',
    'n_talle' => 'XL',
]);

// Tengo un configurable
$configurable = Product::find(1);

// Asociar el simple al configurable
$productService->attachToConfigurable($simple, $configurable);

// Ahora:
// $simple->parent_id → 1
// $configurable->variants->count() → +1
```

### CASO 5: Convertir Variante en Simple Independiente

```php
use App\Services\ProductService;

$productService = app(ProductService::class);

// Tengo una variante
$variante = Product::find(10);

// Desasociar del configurable
$productService->detachFromConfigurable($variante);

// Ahora:
// $variante->parent_id → null
// Es un producto simple independiente
```

---

## Consultas Útiles

```php
use App\Models\Product;

// Obtener solo configurables
$configurables = Product::configurable()->get();

// Obtener solo simples
$simples = Product::simple()->get();

// Obtener productos de nivel superior (sin padre)
$topLevel = Product::topLevel()->get();

// Obtener variantes de un configurable
$variantes = Product::variantsOf(1)->get();

// Obtener variante específica
$configurable = Product::find(1);
$variante = $configurable->findVariant('Negro', 'M');

// Verificar tipo
if ($product->isSimple()) {
    // Es simple
}

if ($product->isConfigurable()) {
    // Es configurable
}

// Verificar si tiene variantes
if ($product->hasVariants()) {
    // Tiene hijos asociados
}
```

---

## En el Componente Livewire (Create/Edit)

En tu componente Livewire `Products/Create.php`, agregarías un campo para seleccionar el tipo:

```php
use App\Enums\ProductType;

class Create extends Component
{
    #[Rule('required')]
    public ProductType $product_type = ProductType::SIMPLE;

    public function save(): void
    {
        $this->validate();

        if ($this->product_type === ProductType::SIMPLE) {
            // Crear producto simple vendible
            Product::create([
                'product_type' => ProductType::SIMPLE,
                'nombre' => $this->nombre,
                'stock' => $this->stock,
                'es_vendible' => true,
                // ... más campos
            ]);
        } else {
            // Crear producto configurable (sin stock, no vendible)
            Product::create([
                'product_type' => ProductType::CONFIGURABLE,
                'nombre' => $this->nombre,
                'stock' => 0,
                'es_vendible' => false,
                // ... más campos
            ]);
        }
    }
}
```

---

## Resumen

| Aspecto | Simple | Configurable |
|---------|--------|--------------|
| **Se determina en** | Campo `product_type` al crear | Campo `product_type` al crear |
| **Vendible** | ✅ Sí | ❌ No |
| **Tiene stock** | ✅ Sí | ❌ No (stock en variantes) |
| **Tiene color/talle** | ✅ Puede tener | ❌ No (las variantes sí) |
| **Puede tener parent_id** | ✅ Sí (si es variante) | ❌ No |
| **Tiene variants** | ❌ No | ✅ Sí |
| **Ejemplo** | Remera Negra M | Remera Deportiva |
