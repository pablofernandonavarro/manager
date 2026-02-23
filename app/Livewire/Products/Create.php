<?php

namespace App\Livewire\Products;

use App\Enums\ProductType;
use App\Models\Product;
use App\Services\ProductCodeService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;
    // Tipo de producto (NUEVO)
    #[Rule('required|in:simple,configurable')]
    public string $product_type = 'simple';

    // Variantes (para productos configurables)
    public array $selectedColors = [];
    public array $selectedSizes = [];
    public array $variants = [];

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

    // Colores y talles disponibles
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

    /**
     * Inicializa el componente con valores por defecto de la configuración.
     */
    public function mount(): void
    {
        try {
            $codeService = app(ProductCodeService::class);
            $config = $codeService->getConfig();

            // Generar código automático si está habilitado
            if ($config->auto_generate_code) {
                $this->codigo_interno = $codeService->generateCode();
            }

            // Aplicar valores por defecto
            if ($config->default_marca) {
                $this->marca = $config->default_marca;
            }

            if ($config->default_linea) {
                $this->linea = $config->default_linea;
            }

            if ($config->default_familia) {
                $this->familia = $config->default_familia;
            }

            if ($config->default_temporada) {
                $this->temporada = $config->default_temporada;
            }

            $this->estado = $config->default_estado ? 1 : 0;
            $this->es_vendible = $config->default_es_vendible;
            $this->remitible = $config->default_remitible;
            $this->stock_critico = $config->default_stock_critical;
            $this->iva = $config->default_tax_rate;
        } catch (\Exception $e) {
            // Si la tabla no existe o hay error, usar valores por defecto
            $this->codigo_interno = '0';
            $this->estado = 1;
            $this->es_vendible = false;
            $this->remitible = true;
            $this->stock_critico = 10;
            $this->iva = 21.000;
        }
    }

    /**
     * Genera variantes automáticamente cuando cambian colores o talles (estilo Magento).
     */
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
    }

    /**
     * Actualiza el stock de una variante específica.
     */
    public function updateVariantStock(int $index, int $stock): void
    {
        if (isset($this->variants[$index])) {
            $this->variants[$index]['stock'] = $stock;
        }
    }

    public function save(): void
    {
        $this->validate();

        // Si es configurable con variantes, usar el servicio estilo Magento
        if ($this->product_type === 'configurable' && count($this->variants) > 0) {
            $this->saveConfigurableWithVariants();
            return;
        }

        // Procesar uploads de archivos
        $imagenPath = $this->imagen ? $this->imagen->store('products/images', 'public') : '';
        $imagenMlPath = $this->imagen_ml ? $this->imagen_ml->store('products/images', 'public') : '';
        $fotoModeloPath = $this->foto_modelo ? $this->foto_modelo->store('products/images', 'public') : '';
        $fotoModeloDetallePath = $this->foto_modelo_detalle ? $this->foto_modelo_detalle->store('products/images', 'public') : '';
        $fotoMedidasPath = $this->foto_medidas ? $this->foto_medidas->store('products/images', 'public') : '';
        $fotoEstampaPath = $this->foto_estampa ? $this->foto_estampa->store('products/images', 'public') : '';
        $planoPath = $this->plano ? $this->plano->store('products/documents', 'public') : '';
        $manualPath = $this->manual ? $this->manual->store('products/documents', 'public') : '';

        // Aplicar cálculo automático de precio si está configurado
        try {
            $codeService = app(ProductCodeService::class);
            $config = $codeService->getConfig();

            if ($config->auto_calculate_price && $this->costo > 0 && (!$this->precio || $this->precio == 0)) {
                $this->precio = $config->calculatePrice($this->costo);
            }
        } catch (\Exception $e) {
            // Si hay error en configuración, continuar sin cálculo automático
        }

        // Si es simple o configurable sin variantes, crear normalmente
        Product::create([
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
            'plano' => $planoPath,
            'descripcion_web' => $this->descripcion_web,
            'descripcion_tecnica' => $this->descripcion_tecnica,
            'manual' => $manualPath,
            'linea' => $this->linea,
            'estado' => $this->estado,
            'imagen' => $imagenPath,
            'imagen_ml' => $imagenMlPath,
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
            'foto_modelo' => $fotoModeloPath,
            'foto_modelo_detalle' => $fotoModeloDetallePath,
            'foto_medidas' => $fotoMedidasPath,
            'foto_estampa' => $fotoEstampaPath,
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
        ]);

        session()->flash('success', 'Producto creado correctamente.');

        $this->redirect('/productos', navigate: true);
    }

    /**
     * Guarda producto configurable con variantes (estilo Magento 2).
     */
    protected function saveConfigurableWithVariants(): void
    {
        $service = app(\App\Services\ProductConfigurableService::class);

        // Preparar datos del configurable
        $configurableData = [
            'nombre' => $this->nombre,
            'codigo_interno' => $this->codigo_interno,
            'codigo_barras' => $this->codigo_barras,
            'descripcion_web' => $this->descripcion_web,
            'descripcion_tecnica' => $this->descripcion_tecnica,
            'precio' => $this->precio,
            'costo' => $this->costo,
            'precio_usd' => $this->precio_usd,
            'publico' => $this->publico,
            'iva' => $this->iva,
            'stock_critico' => $this->stock_critico,
            'linea' => $this->linea,
            'marca' => $this->marca,
            'familia' => $this->familia,
            'grupo' => $this->grupo,
            'subgrupo' => $this->subgrupo,
            'temporada' => $this->temporada,
            'articulo' => $this->articulo,
            'estado' => $this->estado,
            'remitible' => $this->remitible,
            'publicar_ml' => $this->publicar_ml,
        ];

        // Preparar variantes
        $variantsData = array_map(function ($variant) {
            return [
                'color' => $variant['color'],
                'talle_id' => $variant['size_id'],
                'talle_nombre' => $variant['size_name'],
                'stock' => $variant['stock'] ?? 0,
                'primera' => 0,
                'segunda' => 0,
            ];
        }, $this->variants);

        // Crear usando el servicio
        $service->createConfigurableWithVariants($configurableData, $variantsData);

        session()->flash('success', "Producto configurable creado con " . count($this->variants) . " variantes.");
        $this->redirect('/productos', navigate: true);
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.products.create');
    }
}
