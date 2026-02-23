<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_type',
        'parent_id',
        'nombre',
        'busqueda',
        'codigo_interno',
        'precio',
        'costo',
        'costo_usd',
        'costo_produccion',
        'stock',
        'stock_critico',
        'plano',
        'descripcion_web',
        'descripcion_tecnica',
        'manual',
        'linea',
        'estado',
        'imagen',
        'imagen_ml',
        'imagen_url',
        'stock_comprometido',
        'denominacion',
        'marca',
        'peso',
        'dimension',
        'abreviatura',
        'iva',
        'medida',
        'pallet',
        'presentacion',
        'cantidad_um_x_presentacion',
        'cuenta',
        'sociedad',
        'exportar',
        'orden',
        'comision_especial',
        'compuesto',
        'remitible',
        'garantia',
        'es_vendible',
        'articulo',
        'color',
        'color_ml',
        'publicar_ml',
        'stock_ml',
        'codigo_barras',
        'metadata_detalle1',
        'metadata_detalle2',
        'metadata_detalle3',
        'es_materia_prima',
        'edad',
        'grupo',
        'subgrupo',
        'temporada',
        'modelista',
        'numero_molde',
        'cliente',
        'proveedor',
        'proveedor2',
        'proveedor3',
        'grupo_proceso',
        'n_proveedor',
        'tipo_costeo',
        'proceso',
        'rinde',
        'cantidad_a_fabricar',
        'disenador',
        'procedencia',
        'costo_corte',
        'ingresado',
        'epc',
        'reservados',
        'molde',
        'progresion',
        'ficha_tecnica',
        'estampa',
        'bordado',
        'tachas',
        'etiquetas',
        'avios',
        'lavado',
        'muestra',
        'muestrario',
        'encorte',
        'observaciones',
        'familia',
        'target',
        'estado_web',
        'descuento_web',
        'destacado_web',
        'unidad_medida',
        'precio_usd',
        'publico',
        'no_aplica_descuento',
        'lista_publico',
        'lista_mayorista',
        'articulo_segunda',
        'procesado',
        'dafiti_sku',
        'estado_dafiti',
        'porcentaje',
        'nube_actualizar',
        'gtin',
        'ploter_desde',
        'ploter_hasta',
        'ploter_ok',
        'medicion_desde',
        'medicion_hasta',
        'medicion_ok',
        'ancho_proveedor',
        'ancho_real',
        'precio_variable',
        'fecha_costo',
        'observaciones_modelaje',
        'fecha_venta1',
        'n_grupo',
        'n_grupo_ext',
        'n_subgrupo',
        'n_subgrupo_ext',
        'n_target',
        'n_target_ext',
        'n_talle',
        'n_color',
        'n_temporada',
        'n_temporada_ext',
        'n_procedencia',
        'n_procedencia_ext',
        'primera',
        'segunda',
        'mix',
        'fecha_ingreso',
        'composicion',
        'foto_modelo',
        'foto_modelo_detalle',
        'foto_medidas',
        'foto_estampa',
        'complejidad',
        'etapa',
        'nivel_precio',
        'cant_colores',
        'codigo_color_proveedor',
        'codigo_articulo_proveedor',
        'magento_subido',
        'magento_actualizado',
        'categoria_dafiti',
        'dafiti_actualizar',
        'remitible_auto',
        'costo_target',
        'markup',
        'precosto_avios',
        'precosto_telas',
        'url_ecomm',
        'tipo_embalaje',
        'costo_adicional',
        'precosto_compra',
        'articulo_origen',
        'modelo_origen',
    ];

    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'precio' => 'decimal:3',
            'costo' => 'decimal:3',
            'costo_usd' => 'decimal:3',
            'costo_produccion' => 'decimal:3',
            'peso' => 'decimal:3',
            'iva' => 'decimal:3',
            'pallet' => 'decimal:3',
            'cantidad_um_x_presentacion' => 'decimal:3',
            'rinde' => 'decimal:2',
            'costo_corte' => 'decimal:2',
            'descuento_web' => 'decimal:2',
            'precio_usd' => 'decimal:2',
            'publico' => 'decimal:2',
            'porcentaje' => 'decimal:2',
            'costo_target' => 'decimal:3',
            'markup' => 'decimal:3',
            'precosto_avios' => 'decimal:3',
            'precosto_telas' => 'decimal:3',
            'costo_adicional' => 'decimal:3',
            'precosto_compra' => 'decimal:3',
            'exportar' => 'boolean',
            'comision_especial' => 'boolean',
            'compuesto' => 'boolean',
            'remitible' => 'boolean',
            'es_vendible' => 'boolean',
            'publicar_ml' => 'boolean',
            'es_materia_prima' => 'boolean',
            'procesado' => 'boolean',
            'nube_actualizar' => 'boolean',
            'precio_variable' => 'boolean',
            'no_aplica_descuento' => 'boolean',
            'destacado_web' => 'boolean',
            'ploter_desde' => 'date',
            'ploter_hasta' => 'date',
            'medicion_desde' => 'date',
            'medicion_hasta' => 'date',
            'fecha_costo' => 'date',
            'fecha_venta1' => 'date',
            'fecha_ingreso' => 'date',
        ];
    }

    /**
     * Scope para filtrar productos activos.
     */
    public function scopeActive($query)
    {
        return $query->where('estado', 1);
    }

    /**
     * Scope para filtrar productos vendibles.
     */
    public function scopeVendible($query)
    {
        return $query->where('es_vendible', true);
    }

    /**
     * Scope para buscar productos por nombre o código.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('nombre', 'like', "%{$search}%")
              ->orWhere('codigo_interno', 'like', "%{$search}%")
              ->orWhere('codigo_barras', 'like', "%{$search}%")
              ->orWhere('busqueda', 'like', "%{$search}%");
        });
    }

    /**
     * Verifica si el producto tiene stock disponible.
     */
    public function hasStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Verifica si el producto está en stock crítico.
     */
    public function isCriticalStock(): bool
    {
        return $this->stock <= $this->stock_critico;
    }

    /**
     * Obtiene el stock disponible (stock - stock_comprometido).
     */
    public function getStockDisponibleAttribute(): int
    {
        return max(0, $this->stock - ($this->stock_comprometido ?? 0));
    }

    // ==================== RELACIONES (Estilo Magento) ====================

    /**
     * Producto padre (para productos simples que son variantes).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_id');
    }

    /**
     * Variantes/hijos del producto configurable.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(Product::class, 'parent_id');
    }

    /**
     * Variantes activas y vendibles.
     */
    public function activeVariants(): HasMany
    {
        return $this->variants()->where('estado', 1)->where('es_vendible', true);
    }

    /**
     * Imágenes del producto (galería Magento-style).
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    /**
     * Imagen base del producto.
     */
    public function baseImage()
    {
        return $this->images()->where('is_base', true)->first();
    }

    // ==================== SCOPES ====================

    /**
     * Scope para productos simples (variantes).
     */
    public function scopeSimple($query)
    {
        return $query->where('product_type', ProductType::SIMPLE);
    }

    /**
     * Scope para productos configurables (padres).
     */
    public function scopeConfigurable($query)
    {
        return $query->where('product_type', ProductType::CONFIGURABLE);
    }

    /**
     * Scope para productos sin padre (independientes o configurables).
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope para obtener variantes de un producto configurable.
     */
    public function scopeVariantsOf($query, int $parentId)
    {
        return $query->simple()->where('parent_id', $parentId);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Verifica si es un producto simple.
     */
    public function isSimple(): bool
    {
        return $this->product_type === ProductType::SIMPLE;
    }

    /**
     * Verifica si es un producto configurable.
     */
    public function isConfigurable(): bool
    {
        return $this->product_type === ProductType::CONFIGURABLE;
    }

    /**
     * Verifica si el producto tiene variantes.
     */
    public function hasVariants(): bool
    {
        return $this->isConfigurable() && $this->variants()->exists();
    }

    /**
     * Obtiene el stock total de todas las variantes (para configurables).
     */
    public function getTotalVariantsStockAttribute(): int
    {
        if (!$this->isConfigurable()) {
            return $this->stock;
        }

        return $this->variants()->sum('stock');
    }

    /**
     * Obtiene todas las combinaciones únicas de colores disponibles en variantes.
     */
    public function getAvailableColorsAttribute(): array
    {
        if (!$this->isConfigurable()) {
            return [];
        }

        return $this->activeVariants()
            ->whereNotNull('color')
            ->distinct()
            ->pluck('color')
            ->toArray();
    }

    /**
     * Obtiene todos los talles únicos disponibles en variantes.
     */
    public function getAvailableSizesAttribute(): array
    {
        if (!$this->isConfigurable()) {
            return [];
        }

        return $this->activeVariants()
            ->whereNotNull('n_talle')
            ->distinct()
            ->pluck('n_talle')
            ->toArray();
    }

    /**
     * Busca una variante específica por color y talle.
     */
    public function findVariant(?string $color = null, ?string $size = null): ?Product
    {
        if (!$this->isConfigurable()) {
            return null;
        }

        $query = $this->activeVariants();

        if ($color) {
            $query->where('color', $color);
        }

        if ($size) {
            $query->where('n_talle', $size);
        }

        return $query->first();
    }

    /**
     * Obtiene el precio mínimo de las variantes (para configurables).
     */
    public function getMinVariantPriceAttribute(): ?float
    {
        if (!$this->isConfigurable()) {
            return $this->precio;
        }

        return $this->activeVariants()->min('precio');
    }

    /**
     * Obtiene el precio máximo de las variantes (para configurables).
     */
    public function getMaxVariantPriceAttribute(): ?float
    {
        if (!$this->isConfigurable()) {
            return $this->precio;
        }

        return $this->activeVariants()->max('precio');
    }
}
