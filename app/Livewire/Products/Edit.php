<?php

namespace App\Livewire\Products;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductImage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class Edit extends Component
{
    use WithFileUploads;

    public Product $product;

    // Gallery images (Magento style)
    public $newImages = [];
    public array $imageRoles = [];
    public array $imageLabels = [];

    // Variants generation (Magento style)
    public array $selectedColors = [];
    public array $selectedSizes = [];
    public array $variants = [];

    public array $availableColors = [
        'Negro', 'Blanco', 'Rojo', 'Azul', 'Verde', 'Gris', 'Rosa', 'Amarillo', 'Naranja', 'Violeta'
    ];

    public array $availableSizes = [
        ['id' => 1, 'name' => 'XS'],
        ['id' => 2, 'name' => 'S'],
        ['id' => 3, 'name' => 'M'],
        ['id' => 4, 'name' => 'L'],
        ['id' => 5, 'name' => 'XL'],
        ['id' => 6, 'name' => 'XXL'],
    ];

    // Tipo de producto (NUEVO)
    #[Rule('required|in:simple,configurable')]
    public string $product_type = 'simple';

    // Información básica
    #[Rule('required|string|max:100')]
    public string $nombre = '';

    #[Rule('nullable|string|max:160')]
    public string $busqueda = '';

    #[Rule('nullable|string|max:100')]
    public string $codigo_interno = '0';

    #[Rule('nullable|string|max:100')]
    public string $codigo_barras = '';

    #[Rule('nullable|string|max:100')]
    public string $denominacion = '';

    #[Rule('nullable|string|max:100')]
    public string $abreviatura = '';

    // Precios y costos
    #[Rule('nullable|numeric|min:0')]
    public ?float $precio = null;

    #[Rule('nullable|numeric|min:0')]
    public ?float $costo = null;

    #[Rule('nullable|numeric|min:0')]
    public ?float $costo_usd = null;

    #[Rule('nullable|numeric|min:0')]
    public ?float $costo_produccion = null;

    #[Rule('nullable|numeric|min:0')]
    public ?float $precio_usd = null;

    #[Rule('nullable|numeric|min:0')]
    public ?float $publico = null;

    #[Rule('nullable|numeric|min:0')]
    public ?float $costo_corte = null;

    #[Rule('nullable|numeric|min:0')]
    public ?float $costo_target = null;

    #[Rule('nullable|numeric|min:0')]
    public ?float $costo_adicional = 0;

    #[Rule('nullable|numeric|min:0')]
    public ?float $precosto_compra = 0;

    #[Rule('nullable|numeric|min:0')]
    public ?float $precosto_avios = 0;

    #[Rule('nullable|numeric|min:0')]
    public ?float $precosto_telas = 0;

    #[Rule('nullable|numeric|min:0')]
    public ?float $markup = 0;

    // Stock
    #[Rule('nullable|integer|min:0')]
    public ?int $stock = 0;

    #[Rule('nullable|integer|min:0')]
    public ?int $stock_critico = 0;

    #[Rule('nullable|integer|min:0')]
    public ?int $stock_comprometido = 0;

    #[Rule('nullable|integer|min:0')]
    public ?int $stock_ml = null;

    #[Rule('nullable|integer|min:0')]
    public ?int $reservados = null;

    #[Rule('nullable|integer|min:0')]
    public ?int $primera = 0;

    #[Rule('nullable|integer|min:0')]
    public ?int $segunda = 0;

    // Descripciones y archivos
    #[Rule('nullable|string')]
    public string $descripcion_web = '';

    #[Rule('nullable|string')]
    public string $descripcion_tecnica = '';

    #[Rule('nullable|string|max:600')]
    public string $observaciones = '';

    #[Rule('nullable|string|max:255')]
    public string $observaciones_modelaje = '';

    #[Rule('nullable|file|mimes:jpg,jpeg,png,gif,pdf|max:5120')]
    public $plano;

    #[Rule('nullable|file|mimes:jpg,jpeg,png,gif,pdf|max:5120')]
    public $manual;

    #[Rule('nullable|file|mimes:jpg,jpeg,png,gif|max:2048')]
    public $imagen;

    #[Rule('nullable|file|mimes:jpg,jpeg,png,gif|max:2048')]
    public $imagen_ml;

    #[Rule('nullable|string|max:200')]
    public string $imagen_url = '';

    #[Rule('nullable|file|mimes:jpg,jpeg,png,gif|max:2048')]
    public $foto_modelo;

    #[Rule('nullable|file|mimes:jpg,jpeg,png,gif|max:2048')]
    public $foto_modelo_detalle;

    #[Rule('nullable|file|mimes:jpg,jpeg,png,gif|max:2048')]
    public $foto_medidas;

    #[Rule('nullable|file|mimes:jpg,jpeg,png,gif|max:2048')]
    public $foto_estampa;

    // Dimensiones y físicas
    #[Rule('nullable|numeric|min:0')]
    public ?float $peso = 0;

    #[Rule('nullable|string|max:100')]
    public string $dimension = '';

    #[Rule('nullable|numeric|min:0')]
    public ?float $pallet = null;

    #[Rule('nullable|numeric|min:0')]
    public ?float $cantidad_um_x_presentacion = null;

    #[Rule('nullable|numeric|min:0')]
    public ?float $rinde = null;

    #[Rule('nullable|string|max:255')]
    public string $ancho_proveedor = '';

    #[Rule('nullable|string|max:255')]
    public string $ancho_real = '';

    // Color y variantes
    #[Rule('nullable|string|max:100')]
    public string $color = '';

    #[Rule('nullable|string|max:100')]
    public string $color_ml = '';

    #[Rule('nullable|string|max:100')]
    public string $composicion = '';

    #[Rule('nullable|string|max:100')]
    public string $mix = '';

    #[Rule('nullable|string|max:50')]
    public string $n_talle = '';

    #[Rule('nullable|string|max:50')]
    public string $n_color = '';

    // IDs relacionados
    #[Rule('nullable|integer')]
    public ?int $linea = null;

    #[Rule('nullable|integer')]
    public ?int $marca = null;

    #[Rule('nullable|integer')]
    public ?int $medida = null;

    #[Rule('nullable|integer')]
    public ?int $presentacion = null;

    #[Rule('nullable|integer')]
    public ?int $cuenta = null;

    #[Rule('nullable|integer')]
    public ?int $sociedad = null;

    #[Rule('nullable|integer')]
    public ?int $articulo = null;

    #[Rule('nullable|integer')]
    public ?int $metadata_detalle1 = null;

    #[Rule('nullable|integer')]
    public ?int $metadata_detalle2 = null;

    #[Rule('nullable|integer')]
    public ?int $metadata_detalle3 = null;

    #[Rule('nullable|integer')]
    public ?int $edad = null;

    #[Rule('nullable|integer')]
    public ?int $grupo = null;

    #[Rule('nullable|integer')]
    public ?int $subgrupo = null;

    #[Rule('nullable|integer')]
    public ?int $temporada = null;

    #[Rule('nullable|integer')]
    public ?int $cliente = null;

    #[Rule('nullable|integer')]
    public ?int $proveedor = null;

    #[Rule('nullable|integer')]
    public ?int $proveedor2 = null;

    #[Rule('nullable|integer')]
    public ?int $proveedor3 = null;

    #[Rule('nullable|integer')]
    public ?int $grupo_proceso = null;

    #[Rule('nullable|integer')]
    public ?int $tipo_costeo = null;

    #[Rule('nullable|integer')]
    public ?int $proceso = null;

    #[Rule('nullable|integer')]
    public ?int $cantidad_a_fabricar = null;

    #[Rule('nullable|integer')]
    public ?int $disenador = null;

    #[Rule('nullable|integer')]
    public ?int $procedencia = null;

    #[Rule('nullable|integer')]
    public ?int $ingresado = null;

    #[Rule('nullable|integer')]
    public ?int $epc = null;

    #[Rule('nullable|integer')]
    public ?int $familia = null;

    #[Rule('nullable|integer')]
    public ?int $target = null;

    #[Rule('nullable|integer')]
    public ?int $unidad_medida = 1;

    #[Rule('nullable|integer')]
    public ?int $lista_publico = null;

    #[Rule('nullable|integer')]
    public ?int $lista_mayorista = null;

    #[Rule('nullable|integer')]
    public ?int $articulo_segunda = null;

    #[Rule('nullable|integer')]
    public ?int $categoria_dafiti = 0;

    #[Rule('nullable|integer')]
    public ?int $tipo_embalaje = 0;

    #[Rule('nullable|integer')]
    public ?int $complejidad = null;

    #[Rule('nullable|integer')]
    public ?int $etapa = null;

    #[Rule('nullable|integer')]
    public ?int $nivel_precio = null;

    #[Rule('nullable|integer')]
    public ?int $cant_colores = null;

    // Strings relacionados
    #[Rule('nullable|string|max:200')]
    public string $modelista = '';

    #[Rule('nullable|string|max:100')]
    public string $numero_molde = '';

    #[Rule('nullable|string|max:100')]
    public string $n_proveedor = '';

    #[Rule('nullable|string|max:100')]
    public string $n_grupo = '';

    #[Rule('nullable|string|max:100')]
    public string $n_grupo_ext = '';

    #[Rule('nullable|string|max:100')]
    public string $n_subgrupo = '';

    #[Rule('nullable|string|max:100')]
    public string $n_subgrupo_ext = '';

    #[Rule('nullable|string|max:100')]
    public string $n_target = '';

    #[Rule('nullable|string|max:100')]
    public string $n_target_ext = '';

    #[Rule('nullable|string|max:100')]
    public string $n_temporada = '';

    #[Rule('nullable|string|max:100')]
    public string $n_temporada_ext = '';

    #[Rule('nullable|string|max:100')]
    public string $n_procedencia = '';

    #[Rule('nullable|string|max:100')]
    public string $n_procedencia_ext = '';

    #[Rule('nullable|string|max:255')]
    public string $codigo_color_proveedor = '';

    #[Rule('nullable|string|max:255')]
    public string $codigo_articulo_proveedor = '';

    #[Rule('nullable|string|max:255')]
    public string $dafiti_sku = '';

    #[Rule('nullable|string|max:255')]
    public string $gtin = '';

    #[Rule('nullable|string|max:750')]
    public string $url_ecomm = '';

    #[Rule('nullable|string|max:100')]
    public string $articulo_origen = '';

    #[Rule('nullable|string|max:100')]
    public string $modelo_origen = '';

    // Impuestos
    #[Rule('nullable|numeric|min:0')]
    public ?float $iva = 21.000;

    #[Rule('nullable|numeric|min:0|max:100')]
    public ?float $descuento_web = null;

    #[Rule('nullable|numeric|min:0|max:100')]
    public ?float $porcentaje = null;

    // Estados y flags booleanos
    public int $estado = 1;
    public bool $exportar = false;
    public bool $comision_especial = false;
    public bool $compuesto = false;
    public bool $remitible = true;
    public int $garantia = 0;
    public bool $es_vendible = false;
    public bool $publicar_ml = false;
    public bool $es_materia_prima = false;
    public bool $procesado = false;
    public bool $nube_actualizar = false;
    public bool $precio_variable = false;
    public bool $no_aplica_descuento = false;
    public bool $destacado_web = false;
    public int $estado_web = 0;
    public int $estado_dafiti = 0;
    public int $magento_subido = 0;
    public int $magento_actualizado = 0;
    public int $dafiti_actualizar = 1;
    public int $remitible_auto = 0;

    // Flags de producción
    public int $molde = 1;
    public int $progresion = 1;
    public int $ficha_tecnica = 1;
    public int $estampa = 1;
    public int $bordado = 1;
    public int $tachas = 1;
    public int $etiquetas = 1;
    public int $avios = 1;
    public int $lavado = 1;
    public int $muestra = 1;
    public int $muestrario = 1;
    public int $encorte = 1;
    public int $ploter_ok = 0;
    public int $medicion_ok = 0;
    public int $orden = 0;

    // Fechas
    #[Rule('nullable|date')]
    public ?string $fecha_costo = null;

    #[Rule('nullable|date')]
    public ?string $fecha_venta1 = null;

    #[Rule('nullable|date')]
    public ?string $fecha_ingreso = null;

    #[Rule('nullable|date')]
    public ?string $ploter_desde = null;

    #[Rule('nullable|date')]
    public ?string $ploter_hasta = null;

    #[Rule('nullable|date')]
    public ?string $medicion_desde = null;

    #[Rule('nullable|date')]
    public ?string $medicion_hasta = null;

    public function mount(int $productId): void
    {
        $this->product = Product::with(['images', 'variants'])->findOrFail($productId);

        // Cargar todos los datos del producto
        $this->product_type = $this->product->product_type->value;
        $this->nombre = $this->product->nombre;
        $this->busqueda = $this->product->busqueda ?? '';
        $this->codigo_interno = $this->product->codigo_interno ?? '0';
        $this->codigo_barras = $this->product->codigo_barras ?? '';
        $this->denominacion = $this->product->denominacion ?? '';
        $this->abreviatura = $this->product->abreviatura ?? '';
        $this->precio = $this->product->precio;
        $this->costo = $this->product->costo;
        $this->costo_usd = $this->product->costo_usd;
        $this->costo_produccion = $this->product->costo_produccion;
        $this->precio_usd = $this->product->precio_usd;
        $this->publico = $this->product->publico;
        $this->costo_corte = $this->product->costo_corte;
        $this->costo_target = $this->product->costo_target;
        $this->costo_adicional = $this->product->costo_adicional ?? 0;
        $this->precosto_compra = $this->product->precosto_compra ?? 0;
        $this->precosto_avios = $this->product->precosto_avios ?? 0;
        $this->precosto_telas = $this->product->precosto_telas ?? 0;
        $this->markup = $this->product->markup ?? 0;
        $this->stock = $this->product->stock ?? 0;
        $this->stock_critico = $this->product->stock_critico ?? 0;
        $this->stock_comprometido = $this->product->stock_comprometido ?? 0;
        $this->stock_ml = $this->product->stock_ml;
        $this->reservados = $this->product->reservados;
        $this->primera = $this->product->primera ?? 0;
        $this->segunda = $this->product->segunda ?? 0;
        $this->descripcion_web = $this->product->descripcion_web ?? '';
        $this->descripcion_tecnica = $this->product->descripcion_tecnica ?? '';
        $this->observaciones = $this->product->observaciones ?? '';
        $this->observaciones_modelaje = $this->product->observaciones_modelaje ?? '';
        // File fields are not loaded from DB - they will be null unless user uploads new files
        $this->imagen_url = $this->product->imagen_url ?? '';
        $this->peso = $this->product->peso ?? 0;
        $this->dimension = $this->product->dimension ?? '';
        $this->pallet = $this->product->pallet;
        $this->cantidad_um_x_presentacion = $this->product->cantidad_um_x_presentacion;
        $this->rinde = $this->product->rinde;
        $this->ancho_proveedor = $this->product->ancho_proveedor ?? '';
        $this->ancho_real = $this->product->ancho_real ?? '';
        $this->color = $this->product->color ?? '';
        $this->color_ml = $this->product->color_ml ?? '';
        $this->composicion = $this->product->composicion ?? '';
        $this->mix = $this->product->mix ?? '';
        $this->n_talle = $this->product->n_talle ?? '';
        $this->n_color = $this->product->n_color ?? '';
        $this->linea = $this->product->linea;
        $this->marca = $this->product->marca;
        $this->medida = $this->product->medida;
        $this->presentacion = $this->product->presentacion;
        $this->cuenta = $this->product->cuenta;
        $this->sociedad = $this->product->sociedad;
        $this->articulo = $this->product->articulo;
        $this->metadata_detalle1 = $this->product->metadata_detalle1;
        $this->metadata_detalle2 = $this->product->metadata_detalle2;
        $this->metadata_detalle3 = $this->product->metadata_detalle3;
        $this->edad = $this->product->edad;
        $this->grupo = $this->product->grupo;
        $this->subgrupo = $this->product->subgrupo;
        $this->temporada = $this->product->temporada;
        $this->cliente = $this->product->cliente;
        $this->proveedor = $this->product->proveedor;
        $this->proveedor2 = $this->product->proveedor2;
        $this->proveedor3 = $this->product->proveedor3;
        $this->grupo_proceso = $this->product->grupo_proceso;
        $this->tipo_costeo = $this->product->tipo_costeo;
        $this->proceso = $this->product->proceso;
        $this->cantidad_a_fabricar = $this->product->cantidad_a_fabricar;
        $this->disenador = $this->product->disenador;
        $this->procedencia = $this->product->procedencia;
        $this->ingresado = $this->product->ingresado;
        $this->epc = $this->product->epc;
        $this->familia = $this->product->familia;
        $this->target = $this->product->target;
        $this->unidad_medida = $this->product->unidad_medida ?? 1;
        $this->lista_publico = $this->product->lista_publico;
        $this->lista_mayorista = $this->product->lista_mayorista;
        $this->articulo_segunda = $this->product->articulo_segunda;
        $this->categoria_dafiti = $this->product->categoria_dafiti ?? 0;
        $this->tipo_embalaje = $this->product->tipo_embalaje ?? 0;
        $this->complejidad = $this->product->complejidad;
        $this->etapa = $this->product->etapa;
        $this->nivel_precio = $this->product->nivel_precio;
        $this->cant_colores = $this->product->cant_colores;
        $this->modelista = $this->product->modelista ?? '';
        $this->numero_molde = $this->product->numero_molde ?? '';
        $this->n_proveedor = $this->product->n_proveedor ?? '';
        $this->n_grupo = $this->product->n_grupo ?? '';
        $this->n_grupo_ext = $this->product->n_grupo_ext ?? '';
        $this->n_subgrupo = $this->product->n_subgrupo ?? '';
        $this->n_subgrupo_ext = $this->product->n_subgrupo_ext ?? '';
        $this->n_target = $this->product->n_target ?? '';
        $this->n_target_ext = $this->product->n_target_ext ?? '';
        $this->n_temporada = $this->product->n_temporada ?? '';
        $this->n_temporada_ext = $this->product->n_temporada_ext ?? '';
        $this->n_procedencia = $this->product->n_procedencia ?? '';
        $this->n_procedencia_ext = $this->product->n_procedencia_ext ?? '';
        $this->codigo_color_proveedor = $this->product->codigo_color_proveedor ?? '';
        $this->codigo_articulo_proveedor = $this->product->codigo_articulo_proveedor ?? '';
        $this->dafiti_sku = $this->product->dafiti_sku ?? '';
        $this->gtin = $this->product->gtin ?? '';
        $this->url_ecomm = $this->product->url_ecomm ?? '';
        $this->articulo_origen = $this->product->articulo_origen ?? '';
        $this->modelo_origen = $this->product->modelo_origen ?? '';
        $this->iva = $this->product->iva ?? 21.000;
        $this->descuento_web = $this->product->descuento_web;
        $this->porcentaje = $this->product->porcentaje;
        $this->estado = $this->product->estado ?? 1;
        $this->exportar = (bool) $this->product->exportar;
        $this->comision_especial = (bool) $this->product->comision_especial;
        $this->compuesto = (bool) $this->product->compuesto;
        $this->remitible = (bool) $this->product->remitible;
        $this->garantia = $this->product->garantia ?? 0;
        $this->es_vendible = (bool) $this->product->es_vendible;
        $this->publicar_ml = (bool) $this->product->publicar_ml;
        $this->es_materia_prima = (bool) $this->product->es_materia_prima;
        $this->procesado = (bool) $this->product->procesado;
        $this->nube_actualizar = (bool) $this->product->nube_actualizar;
        $this->precio_variable = (bool) $this->product->precio_variable;
        $this->no_aplica_descuento = (bool) $this->product->no_aplica_descuento;
        $this->destacado_web = (bool) $this->product->destacado_web;
        $this->estado_web = $this->product->estado_web ?? 0;
        $this->estado_dafiti = $this->product->estado_dafiti ?? 0;
        $this->magento_subido = $this->product->magento_subido ?? 0;
        $this->magento_actualizado = $this->product->magento_actualizado ?? 0;
        $this->dafiti_actualizar = $this->product->dafiti_actualizar ?? 1;
        $this->remitible_auto = $this->product->remitible_auto ?? 0;
        $this->molde = $this->product->molde ?? 1;
        $this->progresion = $this->product->progresion ?? 1;
        $this->ficha_tecnica = $this->product->ficha_tecnica ?? 1;
        $this->estampa = $this->product->estampa ?? 1;
        $this->bordado = $this->product->bordado ?? 1;
        $this->tachas = $this->product->tachas ?? 1;
        $this->etiquetas = $this->product->etiquetas ?? 1;
        $this->avios = $this->product->avios ?? 1;
        $this->lavado = $this->product->lavado ?? 1;
        $this->muestra = $this->product->muestra ?? 1;
        $this->muestrario = $this->product->muestrario ?? 1;
        $this->encorte = $this->product->encorte ?? 1;
        $this->ploter_ok = $this->product->ploter_ok ?? 0;
        $this->medicion_ok = $this->product->medicion_ok ?? 0;
        $this->orden = $this->product->orden ?? 0;
        $this->fecha_costo = $this->product->fecha_costo?->format('Y-m-d');
        $this->fecha_venta1 = $this->product->fecha_venta1?->format('Y-m-d');
        $this->fecha_ingreso = $this->product->fecha_ingreso?->format('Y-m-d');
        $this->ploter_desde = $this->product->ploter_desde?->format('Y-m-d');
        $this->ploter_hasta = $this->product->ploter_hasta?->format('Y-m-d');
        $this->medicion_desde = $this->product->medicion_desde?->format('Y-m-d');
        $this->medicion_hasta = $this->product->medicion_hasta?->format('Y-m-d');

        // Load existing image roles
        foreach ($this->product->images as $image) {
            $this->imageRoles[$image->id] = $image->getRoles();
            $this->imageLabels[$image->id] = $image->label ?? '';
        }
    }

    public function updatedNewImages(): void
    {
        if (empty($this->newImages)) {
            return;
        }

        $this->validate([
            'newImages.*' => 'image|max:2048',
        ]);

        $position = $this->product->images()->max('position') ?? 0;
        $isFirstImage = $this->product->images()->count() === 0;

        foreach ($this->newImages as $file) {
            $position++;
            $path = $file->store('products/gallery', 'public');

            ProductImage::create([
                'product_id' => $this->product->id,
                'path' => $path,
                'position' => $position,
                'is_base' => $isFirstImage && $position === 1, // First image is base
            ]);
        }

        $this->reset('newImages');
        $this->product->refresh();
        $this->product->load('images');

        session()->flash('success', 'Imágenes subidas correctamente.');
    }

    public function uploadImages(): void
    {
        $this->updatedNewImages();
    }

    public function deleteImage(int $imageId): void
    {
        $image = ProductImage::findOrFail($imageId);

        if ($image->product_id !== $this->product->id) {
            return;
        }

        $image->delete();
        $this->product->load('images');
    }

    public function updateImageRoles(int $imageId, array $roles): void
    {
        $image = ProductImage::findOrFail($imageId);

        if ($image->product_id !== $this->product->id) {
            return;
        }

        $image->update([
            'is_base' => in_array('base', $roles),
            'is_small' => in_array('small', $roles),
            'is_thumbnail' => in_array('thumbnail', $roles),
            'is_swatch' => in_array('swatch', $roles),
            'label' => $this->imageLabels[$imageId] ?? null,
        ]);

        // If this image is set as base, remove base from others
        if ($image->is_base) {
            ProductImage::where('product_id', $this->product->id)
                ->where('id', '!=', $imageId)
                ->update(['is_base' => false]);
        }
    }

    public function updateImagePositions(array $positions): void
    {
        foreach ($positions as $index => $imageId) {
            ProductImage::where('id', $imageId)
                ->where('product_id', $this->product->id)
                ->update(['position' => $index]);
        }

        $this->product->load('images');
    }

    public function generateVariants(): void
    {
        if ($this->product_type !== 'configurable') {
            return;
        }

        $this->variants = [];

        foreach ($this->selectedColors as $color) {
            foreach ($this->selectedSizes as $sizeId) {
                $size = collect($this->availableSizes)->firstWhere('id', $sizeId);

                $this->variants[] = [
                    'color' => $color,
                    'size_id' => $sizeId,
                    'size_name' => $size['name'],
                    'stock' => 0,
                    'enabled' => true,
                ];
            }
        }

        \Log::info('Variants generated', ['count' => count($this->variants), 'variants' => $this->variants]);
    }

    public function saveVariants(): void
    {
        \Log::info('saveVariants called', ['variants_count' => count($this->variants)]);

        if (empty($this->variants)) {
            session()->flash('error', 'No hay variantes para guardar.');
            return;
        }

        $configurableData = [
            'nombre' => $this->nombre,
            'codigo_interno' => $this->codigo_interno,
            'codigo_barras' => $this->codigo_barras,
            'precio' => $this->precio,
            'costo' => $this->costo,
            'precio_usd' => $this->precio_usd,
            'publico' => $this->publico,
            'descripcion_web' => $this->descripcion_web,
            'descripcion_tecnica' => $this->descripcion_tecnica,
            'iva' => $this->iva,
            'stock_critico' => $this->stock_critico,
            'marca' => $this->marca,
            'linea' => $this->linea,
            'familia' => $this->familia,
            'grupo' => $this->grupo,
            'subgrupo' => $this->subgrupo,
            'temporada' => $this->temporada,
            'articulo' => $this->articulo,
            'estado' => $this->estado,
            'remitible' => $this->remitible,
            'publicar_ml' => $this->publicar_ml,
        ];

        try {
            $service = app(\App\Services\ProductConfigurableService::class);
            $service->createVariantsForExisting($this->product, $configurableData, $this->variants);

            // Limpiar el formulario
            $this->reset(['variants', 'selectedColors', 'selectedSizes']);

            // Recargar el producto con sus variantes
            $this->product->refresh();
            $this->product->load('variants');

            session()->flash('success', 'Variantes creadas exitosamente: ' . $this->product->variants->count() . ' variantes.');

            // Dispatch para actualizar la UI
            $this->dispatch('variants-saved');
        } catch (\Exception $e) {
            session()->flash('error', 'Error al crear variantes: ' . $e->getMessage());
        }
    }

    public function update(): void
    {
        $this->validate();

        // Build update array with basic fields
        $updateData = [
            'product_type' => ProductType::from($this->product_type),
            'nombre' => $this->nombre,
            'busqueda' => $this->busqueda ?: strtolower($this->nombre . ' ' . $this->codigo_interno . ' ' . $this->codigo_barras),
            'codigo_interno' => $this->codigo_interno,
            'codigo_barras' => $this->codigo_barras,
            'precio' => $this->precio,
            'costo' => $this->costo,
            'costo_usd' => $this->costo_usd,
            'costo_produccion' => $this->costo_produccion,
            'stock' => $this->stock,
            'stock_critico' => $this->stock_critico,
            'stock_comprometido' => $this->stock_comprometido,
            'stock_ml' => $this->stock_ml,
            'reservados' => $this->reservados,
            'descripcion_web' => $this->descripcion_web,
            'descripcion_tecnica' => $this->descripcion_tecnica,
            'linea' => $this->linea,
            'estado' => $this->estado,
            'imagen_url' => $this->imagen_url,
            'denominacion' => $this->denominacion,
            'marca' => $this->marca,
            'peso' => $this->peso,
            'dimension' => $this->dimension,
            'abreviatura' => $this->abreviatura,
            'iva' => $this->iva,
            'medida' => $this->medida,
            'pallet' => $this->pallet,
            'presentacion' => $this->presentacion,
            'cantidad_um_x_presentacion' => $this->cantidad_um_x_presentacion,
            'cuenta' => $this->cuenta,
            'sociedad' => $this->sociedad,
            'exportar' => $this->exportar,
            'orden' => $this->orden,
            'comision_especial' => $this->comision_especial,
            'compuesto' => $this->compuesto,
            'remitible' => $this->remitible,
            'garantia' => $this->garantia,
            'es_vendible' => $this->es_vendible,
            'articulo' => $this->articulo,
            'color' => $this->color,
            'color_ml' => $this->color_ml,
            'publicar_ml' => $this->publicar_ml,
            'metadata_detalle1' => $this->metadata_detalle1,
            'metadata_detalle2' => $this->metadata_detalle2,
            'metadata_detalle3' => $this->metadata_detalle3,
            'es_materia_prima' => $this->es_materia_prima,
            'edad' => $this->edad,
            'grupo' => $this->grupo,
            'subgrupo' => $this->subgrupo,
            'temporada' => $this->temporada,
            'modelista' => $this->modelista,
            'numero_molde' => $this->numero_molde,
            'cliente' => $this->cliente,
            'proveedor' => $this->proveedor,
            'proveedor2' => $this->proveedor2,
            'proveedor3' => $this->proveedor3,
            'grupo_proceso' => $this->grupo_proceso,
            'n_proveedor' => $this->n_proveedor,
            'tipo_costeo' => $this->tipo_costeo,
            'proceso' => $this->proceso,
            'rinde' => $this->rinde,
            'cantidad_a_fabricar' => $this->cantidad_a_fabricar,
            'disenador' => $this->disenador,
            'procedencia' => $this->procedencia,
            'costo_corte' => $this->costo_corte,
            'ingresado' => $this->ingresado,
            'epc' => $this->epc,
            'molde' => $this->molde,
            'progresion' => $this->progresion,
            'ficha_tecnica' => $this->ficha_tecnica,
            'estampa' => $this->estampa,
            'bordado' => $this->bordado,
            'tachas' => $this->tachas,
            'etiquetas' => $this->etiquetas,
            'avios' => $this->avios,
            'lavado' => $this->lavado,
            'muestra' => $this->muestra,
            'muestrario' => $this->muestrario,
            'encorte' => $this->encorte,
            'observaciones' => $this->observaciones,
            'familia' => $this->familia,
            'target' => $this->target,
            'estado_web' => $this->estado_web,
            'descuento_web' => $this->descuento_web,
            'destacado_web' => $this->destacado_web,
            'unidad_medida' => $this->unidad_medida,
            'precio_usd' => $this->precio_usd,
            'publico' => $this->publico,
            'no_aplica_descuento' => $this->no_aplica_descuento,
            'lista_publico' => $this->lista_publico,
            'lista_mayorista' => $this->lista_mayorista,
            'articulo_segunda' => $this->articulo_segunda,
            'procesado' => $this->procesado,
            'dafiti_sku' => $this->dafiti_sku,
            'estado_dafiti' => $this->estado_dafiti,
            'porcentaje' => $this->porcentaje,
            'nube_actualizar' => $this->nube_actualizar,
            'gtin' => $this->gtin,
            'ploter_desde' => $this->ploter_desde,
            'ploter_hasta' => $this->ploter_hasta,
            'ploter_ok' => $this->ploter_ok,
            'medicion_desde' => $this->medicion_desde,
            'medicion_hasta' => $this->medicion_hasta,
            'medicion_ok' => $this->medicion_ok,
            'ancho_proveedor' => $this->ancho_proveedor,
            'ancho_real' => $this->ancho_real,
            'precio_variable' => $this->precio_variable,
            'fecha_costo' => $this->fecha_costo,
            'observaciones_modelaje' => $this->observaciones_modelaje,
            'fecha_venta1' => $this->fecha_venta1,
            'n_grupo' => $this->n_grupo,
            'n_grupo_ext' => $this->n_grupo_ext,
            'n_subgrupo' => $this->n_subgrupo,
            'n_subgrupo_ext' => $this->n_subgrupo_ext,
            'n_target' => $this->n_target,
            'n_target_ext' => $this->n_target_ext,
            'n_talle' => $this->n_talle,
            'n_color' => $this->n_color,
            'n_temporada' => $this->n_temporada,
            'n_temporada_ext' => $this->n_temporada_ext,
            'n_procedencia' => $this->n_procedencia,
            'n_procedencia_ext' => $this->n_procedencia_ext,
            'primera' => $this->primera,
            'segunda' => $this->segunda,
            'mix' => $this->mix,
            'fecha_ingreso' => $this->fecha_ingreso,
            'composicion' => $this->composicion,
            'complejidad' => $this->complejidad,
            'etapa' => $this->etapa,
            'nivel_precio' => $this->nivel_precio,
            'cant_colores' => $this->cant_colores,
            'codigo_color_proveedor' => $this->codigo_color_proveedor,
            'codigo_articulo_proveedor' => $this->codigo_articulo_proveedor,
            'magento_subido' => $this->magento_subido,
            'magento_actualizado' => $this->magento_actualizado,
            'categoria_dafiti' => $this->categoria_dafiti,
            'dafiti_actualizar' => $this->dafiti_actualizar,
            'remitible_auto' => $this->remitible_auto,
            'costo_target' => $this->costo_target,
            'markup' => $this->markup,
            'precosto_avios' => $this->precosto_avios,
            'precosto_telas' => $this->precosto_telas,
            'url_ecomm' => $this->url_ecomm,
            'tipo_embalaje' => $this->tipo_embalaje,
            'costo_adicional' => $this->costo_adicional,
            'precosto_compra' => $this->precosto_compra,
            'articulo_origen' => $this->articulo_origen,
            'modelo_origen' => $this->modelo_origen,
        ];

        // Process file uploads and add to update data if new files were uploaded
        if ($this->imagen) {
            $updateData['imagen'] = $this->imagen->store('products/images', 'public');
        }
        if ($this->imagen_ml) {
            $updateData['imagen_ml'] = $this->imagen_ml->store('products/images', 'public');
        }
        if ($this->foto_modelo) {
            $updateData['foto_modelo'] = $this->foto_modelo->store('products/images', 'public');
        }
        if ($this->foto_modelo_detalle) {
            $updateData['foto_modelo_detalle'] = $this->foto_modelo_detalle->store('products/images', 'public');
        }
        if ($this->foto_medidas) {
            $updateData['foto_medidas'] = $this->foto_medidas->store('products/images', 'public');
        }
        if ($this->foto_estampa) {
            $updateData['foto_estampa'] = $this->foto_estampa->store('products/images', 'public');
        }
        if ($this->plano) {
            $updateData['plano'] = $this->plano->store('products/documents', 'public');
        }
        if ($this->manual) {
            $updateData['manual'] = $this->manual->store('products/documents', 'public');
        }

        $this->product->update($updateData);

        session()->flash('success', 'Producto actualizado correctamente.');

        $this->redirect('/productos', navigate: true);
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        // Reload variants to ensure fresh data
        $this->product->load('variants');

        return view('livewire.products.edit', [
            'existingVariants' => $this->product->variants,
        ]);
    }
}
