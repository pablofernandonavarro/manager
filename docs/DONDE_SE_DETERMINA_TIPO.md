# ¿Dónde se determina si un producto es Simple o Configurable?

## Respuesta Corta

Se determina **al momento de crear el producto** mediante el campo `product_type`.

---

## 3 Formas de Crear Productos

### 1️⃣ Usando ProductService (Recomendado)

```php
use App\Services\ProductService;

$service = app(ProductService::class);

// OPCIÓN A: Crear SIMPLE
$simple = $service->createSimple([
    'nombre' => 'Remera Negra M',
    'stock' => 10,
    // ... más campos
]);

// OPCIÓN B: Crear CONFIGURABLE
$configurable = $service->createConfigurable([
    'nombre' => 'Remera Deportiva',
    // ... más campos
]);

// OPCIÓN C: Crear CONFIGURABLE con variantes (Magento 2 style)
$configurable = $service->createConfigurableWithVariants(
    configurableData: [
        'nombre' => 'Remera Deportiva',
        'precio' => 500,
    ],
    variants: [
        ['color' => 'Negro', 'talle_nombre' => 'S', 'talle_id' => 1, 'stock' => 10],
        ['color' => 'Blanco', 'talle_nombre' => 'M', 'talle_id' => 2, 'stock' => 15],
    ]
);
```

---

### 2️⃣ Usando Eloquent directamente

```php
use App\Enums\ProductType;
use App\Models\Product;

// Crear SIMPLE
Product::create([
    'product_type' => ProductType::SIMPLE,  // ← AQUÍ
    'nombre' => 'Remera Negra M',
    'es_vendible' => true,
    'stock' => 10,
]);

// Crear CONFIGURABLE
Product::create([
    'product_type' => ProductType::CONFIGURABLE,  // ← AQUÍ
    'nombre' => 'Remera Deportiva',
    'es_vendible' => false,
    'stock' => 0,
]);
```

---

### 3️⃣ Usando Factory (para testing/seeders)

```php
use App\Models\Product;

// Crear SIMPLE (por defecto)
$simple = Product::factory()->create();

// Crear CONFIGURABLE
$configurable = Product::factory()->configurable()->create();

// Crear SIMPLE como variante de un CONFIGURABLE
$variante = Product::factory()
    ->variantOf($configurable, 'Negro', 1, 'S')
    ->create();
```

---

## En la UI (Livewire Component)

En tu formulario de creación, agregarías un campo para seleccionar:

```php
// app/Livewire/Products/Create.php

use App\Enums\ProductType;

class Create extends Component
{
    #[Rule('required')]
    public ProductType $product_type = ProductType::SIMPLE;  // ← AQUÍ se determina

    public string $nombre = '';
    public ?int $stock = 0;

    public function save()
    {
        Product::create([
            'product_type' => $this->product_type,  // ← Se guarda en DB
            'nombre' => $this->nombre,
            'stock' => $this->stock,
            'es_vendible' => $this->product_type === ProductType::SIMPLE,
        ]);
    }
}
```

```html
<!-- resources/views/livewire/products/create.blade.php -->

<select wire:model="product_type">
    <option value="simple">Simple (vendible)</option>
    <option value="configurable">Configurable (agrupa variantes)</option>
</select>
```

---

## Resumen Visual

```
┌─────────────────────────────────────────┐
│  MOMENTO DE CREACIÓN                    │
├─────────────────────────────────────────┤
│                                         │
│  product_type = 'simple'                │
│  ↓                                      │
│  Producto SIMPLE                        │
│  ✅ Vendible                            │
│  ✅ Tiene stock                         │
│                                         │
├─────────────────────────────────────────┤
│                                         │
│  product_type = 'configurable'          │
│  ↓                                      │
│  Producto CONFIGURABLE                  │
│  ❌ No vendible                         │
│  ❌ Sin stock (está en variantes)       │
│                                         │
└─────────────────────────────────────────┘
```

---

## Campo en la Base de Datos

```sql
-- Migration
$table->string('product_type', 20)->default('simple')->index();

-- Valores posibles:
-- 'simple'        → Producto vendible
-- 'configurable'  → Producto padre que agrupa variantes
```

---

## ¿Se puede cambiar después de creado?

⚠️ **NO recomendado**. El tipo define la arquitectura del producto:

- **Simple** → Puede tener stock, precio, se vende directamente
- **Configurable** → No se vende, agrupa variantes, no tiene stock propio

Cambiar el tipo después requeriría migrar toda la estructura.
