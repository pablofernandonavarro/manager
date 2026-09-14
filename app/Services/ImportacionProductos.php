<?php

namespace App\Services;

use App\Enums\EstadoAjuste;
use App\Enums\ProductType;
use App\Enums\TipoMovimiento;
use App\Models\AjusteInventario;
use App\Models\AjusteInventarioLinea;
use App\Models\AttributeType;
use App\Models\AttributeValue;
use App\Models\MovimientoStock;
use App\Models\Product;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Alta y actualización de productos desde Excel.
 *
 * Una fila por artículo vendible:
 * - Sin `modelo` → producto simple, identificado por `codigo`.
 * - Con `modelo` → variante color + talle de ese configurable (se crea si no existe),
 *   identificada por modelo + color + talle.
 *
 * Las columnas `stock <sucursal>` dejan el stock de esa sucursal **en** ese número (como un
 * ajuste de inventario, no una suma): subir dos veces el mismo archivo no duplica stock.
 * Celda vacía = no tocar.
 *
 * Primero `analizar()` (previsualización, sin escribir nada); `aplicar()` vuelve a analizar
 * el archivo y, si no hay errores, escribe todo en una transacción.
 */
class ImportacionProductos
{
    public const COLUMNAS = ['modelo', 'codigo', 'nombre', 'color', 'talle', 'codigo_barras', 'precio', 'costo', 'iva'];

    private const MAX_FILAS = 5000;

    private const IVAS = [0.0, 2.5, 5.0, 10.5, 21.0, 27.0];

    public function __construct(
        private readonly ProductConfigurableService $configurables,
    ) {}

    public function plantilla(): Spreadsheet
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Productos');

        $encabezados = [...self::COLUMNAS, ...$this->sucursales()->map(fn (Sucursal $s) => 'stock '.$s->nombre)->all()];
        $hoja->fromArray($encabezados, null, 'A1');

        $ultima = $hoja->getHighestColumn();
        $hoja->getStyle("A1:{$ultima}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
        ]);
        foreach (range(1, count($encabezados)) as $i) {
            $hoja->getColumnDimensionByColumn($i)->setWidth($i === 3 ? 34 : 16);
        }
        // Códigos y talles como texto: Excel convierte 7791234000017 en 7,79E+12 y "040" en 40.
        $hoja->getStyle('A:F')->getNumberFormat()->setFormatCode('@');

        $ayuda = $libro->createSheet();
        $ayuda->setTitle('Instrucciones');
        $ayuda->fromArray([
            ['Columna', 'Qué va'],
            ['modelo', 'Código del modelo (ej. CONF-4301). Vacío = producto simple.'],
            ['codigo', 'SKU. Obligatorio en productos simples. En variantes, vacío = se arma MODELO-COL-TAL.'],
            ['nombre', 'Nombre del producto o del modelo. Obligatorio al crear.'],
            ['color / talle', 'Obligatorios en variantes.'],
            ['codigo_barras', 'Opcional. Vacío = se genera un EAN-13 interno.'],
            ['precio / costo', 'Números. Precio obligatorio al crear.'],
            ['iva', 'Opcional: 0, 2.5, 5, 10.5, 21 o 27. Por defecto 21.'],
            ['stock <sucursal>', 'Stock que queda en esa sucursal. Vacío = no se toca.'],
            [],
            ['Ejemplos (no copiar esta hoja)'],
            [...self::COLUMNAS, 'stock Central'],
            ['CONF-4301', '', 'Remera básica algodón', 'Negro', 'M', '', 12900, 5200, 21, 10],
            ['CONF-4301', '', 'Remera básica algodón', 'Negro', 'L', '', 12900, 5200, 21, 8],
            ['', 'ACC-001', 'Cinturón de cuero', '', '', '7791234200023', 15900, 6100, 21, 4],
        ]);
        $ayuda->getColumnDimension('A')->setWidth(18);
        $ayuda->getColumnDimension('B')->setWidth(80);
        $ayuda->getStyle('A1:B1')->getFont()->setBold(true);
        $ayuda->getStyle('A11')->getFont()->setBold(true);

        $libro->setActiveSheetIndex(0);

        return $libro;
    }

    /**
     * @return array{filas: list<array<string, mixed>>, errores: list<string>, resumen: array<string, int>}
     */
    public function analizar(string $ruta): array
    {
        $errores = [];

        try {
            $filas = IOFactory::load($ruta)->getSheet(0)->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            return $this->resultado([], ['No se pudo leer el archivo: '.$e->getMessage()]);
        }

        $originales = array_map(fn ($h) => Str::squish((string) $h), array_shift($filas) ?? []);
        $encabezados = array_map(fn ($h) => Str::lower($h), $originales);
        $columnas = array_flip(array_filter($encabezados, fn ($h) => $h !== ''));

        foreach (['nombre', 'precio'] as $obligatoria) {
            if (! isset($columnas[$obligatoria])) {
                $errores[] = "Falta la columna \"{$obligatoria}\". Descargá la plantilla.";
            }
        }
        if (! isset($columnas['codigo']) && ! isset($columnas['modelo'])) {
            $errores[] = 'Falta la columna "codigo" o "modelo". Descargá la plantilla.';
        }

        $sucursalesPorNombre = $this->sucursales()->keyBy(fn (Sucursal $s) => Str::lower($s->nombre));
        $columnasStock = [];
        foreach ($columnas as $encabezado => $indice) {
            if (str_starts_with($encabezado, 'stock ')) {
                $nombre = trim(substr($encabezado, 6));
                $sucursal = $sucursalesPorNombre[$nombre] ?? null;
                $sucursal
                    ? $columnasStock[$indice] = $sucursal
                    : $errores[] = "La columna \"{$originales[$indice]}\" no corresponde a ninguna sucursal activa.";
            }
        }

        if ($errores) {
            return $this->resultado([], $errores);
        }

        // Conserva las claves: fila de Excel = clave + 2 (encabezado y base 1).
        $filas = array_filter($filas, fn ($f) => collect($f)->contains(fn ($v) => trim((string) $v) !== ''));

        if (count($filas) > self::MAX_FILAS) {
            return $this->resultado([], ['El archivo tiene más de '.self::MAX_FILAS.' filas. Dividilo en partes.']);
        }

        $productos = Product::withTrashed()->get(['id', 'product_type', 'parent_id', 'codigo_interno', 'codigo_barras', 'color', 'n_talle', 'nombre', 'deleted_at']);
        $porCodigo = $productos->whereNull('deleted_at')->keyBy('codigo_interno');
        $porBarras = $productos->whereNull('deleted_at')->filter(fn ($p) => filled($p->codigo_barras))->keyBy('codigo_barras');
        $variantes = $productos->whereNull('deleted_at')->whereNotNull('parent_id')
            ->keyBy(fn ($p) => $p->parent_id.'|'.Str::lower((string) $p->color).'|'.Str::lower((string) $p->n_talle));
        $codigosEnUso = $productos->pluck('codigo_interno')->filter()->map(fn ($c) => Str::lower($c))->flip();

        $plan = [];
        $vistos = ['clave' => [], 'barras' => [], 'codigo' => []];

        foreach ($filas as $i => $fila) {
            $n = $i + 2;
            $celda = fn (string $col) => isset($columnas[$col]) ? $this->texto($fila[$columnas[$col]] ?? null) : '';
            $falla = function (string $mensaje) use (&$errores, $n): void {
                $errores[] = "Fila {$n}: {$mensaje}";
            };

            $modelo = $celda('modelo');
            $codigo = $celda('codigo');
            $nombre = $celda('nombre');
            $color = $celda('color');
            $talle = $celda('talle');
            $barras = $celda('codigo_barras');
            $precio = $this->numero($celda('precio'));
            $costo = $this->numero($celda('costo'));
            $ivaCrudo = $celda('iva');
            $iva = $ivaCrudo === '' ? null : $this->numero($ivaCrudo);
            $erroresAntes = count($errores);

            foreach (['precio' => [$celda('precio'), $precio], 'costo' => [$celda('costo'), $costo]] as $campo => [$crudo, $valor]) {
                // $valor null con celda llena = no se pudo leer como número.
                if ($crudo !== '' && ($valor === null || $valor < 0)) {
                    $falla("{$campo} \"{$crudo}\" no es un número válido.");
                }
            }
            if ($iva !== null && ! in_array($iva, self::IVAS, true)) {
                $falla("iva \"{$ivaCrudo}\" tiene que ser 0, 2.5, 5, 10.5, 21 o 27.");
            }
            if ($barras !== '' && ! preg_match('/^\d{8,14}$/', $barras)) {
                $falla("codigo_barras \"{$barras}\" tiene que tener entre 8 y 14 dígitos.");
            }

            $stock = [];
            foreach ($columnasStock as $indice => $sucursal) {
                $crudo = $this->texto($fila[$indice] ?? null);
                if ($crudo === '') {
                    continue;
                }
                $cantidad = $this->numero($crudo);
                if ($cantidad === null || $cantidad < 0 || floor($cantidad) != $cantidad) {
                    $falla("stock {$sucursal->nombre} \"{$crudo}\" tiene que ser un entero ≥ 0.");

                    continue;
                }
                $stock[$sucursal->id] = (int) $cantidad;
            }

            $entrada = [
                'fila' => $n, 'modelo' => $modelo, 'codigo' => $codigo, 'nombre' => $nombre, 'color' => $color,
                'talle' => $talle, 'codigo_barras' => $barras, 'precio' => $precio, 'costo' => $costo, 'iva' => $iva,
                'stock' => $stock, 'producto_id' => null, 'modelo_id' => null,
            ];

            if ($modelo !== '') {
                if ($color === '' || $talle === '') {
                    $falla("la variante del modelo {$modelo} necesita color y talle.");
                }

                $padre = $porCodigo[$modelo] ?? null;
                if ($padre && $padre->product_type !== ProductType::CONFIGURABLE) {
                    $falla("{$modelo} es un producto simple, no un modelo con variantes.");
                    $padre = null;
                }

                $clave = Str::lower("{$modelo}|{$color}|{$talle}");
                if (isset($vistos['clave'][$clave])) {
                    $falla("{$modelo} {$color} / {$talle} ya está en la fila {$vistos['clave'][$clave]}.");
                }
                $vistos['clave'][$clave] = $n;

                $existente = $padre ? ($variantes[$padre->id.'|'.Str::lower($color).'|'.Str::lower($talle)] ?? null) : null;
                $entrada['tipo'] = 'variante';
                $entrada['modelo_id'] = $padre?->id;
                $entrada['producto_id'] = $existente?->id;
                $entrada['accion'] = $existente ? 'actualizar' : 'crear';

                if (! $padre && $nombre === '') {
                    $falla("el modelo {$modelo} es nuevo: falta el nombre.");
                }
                if (! $existente && $codigo !== '' && isset($codigosEnUso[Str::lower($codigo)])) {
                    $falla("el código {$codigo} ya lo usa otro producto.");
                }
            } else {
                if ($codigo === '') {
                    $falla('un producto simple necesita codigo (o completá modelo, color y talle si es una variante).');
                }

                $existente = $codigo !== '' ? ($porCodigo[$codigo] ?? null) : null;
                if ($existente && ($existente->product_type === ProductType::CONFIGURABLE || $existente->parent_id)) {
                    $falla("{$codigo} es un modelo o una variante: cargalo con las columnas modelo, color y talle.");
                    $existente = null;
                }

                $entrada['tipo'] = 'simple';
                $entrada['producto_id'] = $existente?->id;
                $entrada['accion'] = $existente ? 'actualizar' : 'crear';

                if (! $existente && $nombre === '') {
                    $falla("{$codigo} es nuevo: falta el nombre.");
                }
            }

            if ($entrada['accion'] === 'crear' && $precio === null) {
                $falla('falta el precio para crear el producto.');
            }

            if ($codigo !== '') {
                $claveCodigo = Str::lower($codigo);
                if (isset($vistos['codigo'][$claveCodigo])) {
                    $falla("el código {$codigo} ya está en la fila {$vistos['codigo'][$claveCodigo]}.");
                }
                $vistos['codigo'][$claveCodigo] = $n;
            }

            if ($barras !== '') {
                $duenio = $porBarras[$barras] ?? null;
                if ($duenio && $duenio->id !== $entrada['producto_id']) {
                    $falla("el código de barras {$barras} ya es de {$duenio->codigo_interno}.");
                }
                if (isset($vistos['barras'][$barras])) {
                    $falla("el código de barras {$barras} ya está en la fila {$vistos['barras'][$barras]}.");
                }
                $vistos['barras'][$barras] = $n;
            }

            if (count($errores) === $erroresAntes) {
                $plan[] = $entrada;
            }
        }

        if (! $plan && ! $errores) {
            $errores[] = 'El archivo no tiene filas con datos.';
        }

        if (collect($plan)->contains(fn ($f) => $f['tipo'] === 'variante')) {
            foreach (['color', 'talle'] as $slug) {
                if (! AttributeType::where('slug', $slug)->whereNotNull('product_column')->exists()) {
                    $errores[] = "Falta el atributo \"{$slug}\" en Configuración → Atributos.";
                }
            }
        }

        return $this->resultado($plan, $errores);
    }

    /**
     * @return array{creados: int, actualizados: int, modelos: int, stock: int}
     *
     * @throws \RuntimeException si el archivo tiene errores
     */
    public function aplicar(string $ruta, ?int $usuarioId, string $nombreArchivo): array
    {
        $analisis = $this->analizar($ruta);

        if ($analisis['errores']) {
            throw new \RuntimeException('El archivo tiene errores: corregilos y volvé a subirlo.');
        }

        return DB::transaction(function () use ($analisis, $usuarioId, $nombreArchivo) {
            $color = AttributeType::where('slug', 'color')->first();
            $talle = AttributeType::where('slug', 'talle')->first();
            $conteo = ['creados' => 0, 'actualizados' => 0, 'modelos' => 0, 'stock' => 0];
            $stockPorProducto = [];
            $filas = collect($analisis['filas']);

            foreach ($filas->where('tipo', 'simple') as $f) {
                $producto = $this->guardarSimple($f);
                $conteo[$f['accion'] === 'crear' ? 'creados' : 'actualizados']++;
                $stockPorProducto[$producto->id] = $f['stock'];
            }

            foreach ($filas->where('tipo', 'variante')->groupBy(fn ($f) => Str::lower($f['modelo'])) as $grupo) {
                $primera = $grupo->first();
                $padre = $primera['modelo_id'] ? Product::find($primera['modelo_id']) : null;

                if (! $padre) {
                    $padre = Product::create([
                        'product_type' => ProductType::CONFIGURABLE,
                        'codigo_interno' => $primera['modelo'],
                        'nombre' => $primera['nombre'],
                        'precio' => $primera['precio'] ?? 0,
                        'publico' => $primera['precio'] ?? 0,
                        'costo' => $primera['costo'] ?? 0,
                        'iva' => $primera['iva'] ?? 21,
                        'stock' => 0,
                        'estado' => 1,
                        'es_vendible' => false,
                        'remitible' => true,
                    ]);
                    $conteo['modelos']++;
                }

                $this->asegurarValores($color, $grupo->pluck('color')->all());
                $this->asegurarValores($talle, $grupo->pluck('talle')->all());

                foreach ($grupo as $f) {
                    $variante = $f['producto_id'] ? Product::find($f['producto_id']) : null;

                    if (! $variante) {
                        $variante = $this->configurables->addVariantsToConfigurable($padre->fresh(), [[
                            'attributes' => [
                                ['slug' => $color->slug, 'product_column' => $color->product_column, 'value' => $f['color']],
                                ['slug' => $talle->slug, 'product_column' => $talle->product_column, 'value' => $f['talle']],
                            ],
                            'codigo_barras' => $f['codigo_barras'] ?: null,
                        ]])->first();
                        $conteo['creados']++;
                    } else {
                        $conteo['actualizados']++;
                    }

                    $variante->fill(array_filter([
                        'codigo_interno' => $f['accion'] === 'crear' && $f['codigo'] !== '' ? $f['codigo'] : null,
                        'nombre' => $f['accion'] === 'actualizar' && $f['nombre'] !== '' ? "{$f['nombre']} - {$f['color']} - {$f['talle']}" : null,
                        'codigo_barras' => $f['codigo_barras'] ?: null,
                        'precio' => $f['precio'],
                        'publico' => $f['precio'],
                        'costo' => $f['costo'],
                        'iva' => $f['iva'],
                    ], fn ($v) => $v !== null))->fill(['es_vendible' => true])->save();

                    $stockPorProducto[$variante->id] = $f['stock'];
                }
            }

            $conteo['stock'] = $this->aplicarStock($stockPorProducto, $usuarioId, "Importación de productos ({$nombreArchivo})");

            return $conteo;
        });
    }

    /** @param  array<string, mixed>  $f */
    private function guardarSimple(array $f): Product
    {
        $producto = $f['producto_id'] ? Product::find($f['producto_id']) : new Product([
            'product_type' => ProductType::SIMPLE,
            'codigo_interno' => $f['codigo'],
            'stock' => 0,
            'estado' => 1,
            'es_vendible' => true,
            'remitible' => true,
            'iva' => 21,
        ]);

        $producto->fill(array_filter([
            'nombre' => $f['nombre'] !== '' ? $f['nombre'] : null,
            'codigo_barras' => $f['codigo_barras'] ?: null,
            'precio' => $f['precio'],
            'publico' => $f['precio'],
            'costo' => $f['costo'],
            'iva' => $f['iva'],
        ], fn ($v) => $v !== null))->save();

        return $producto;
    }

    /**
     * Un ajuste de inventario por sucursal, igual que la pantalla de ajuste: queda en su
     * historial y en los movimientos que ven las cajas.
     *
     * @param  array<int, array<int, int>>  $stockPorProducto  [product_id => [sucursal_id => cantidad]]
     */
    private function aplicarStock(array $stockPorProducto, ?int $usuarioId, string $referencia): int
    {
        $porSucursal = [];
        foreach ($stockPorProducto as $productoId => $cantidades) {
            foreach ($cantidades as $sucursalId => $cantidad) {
                $porSucursal[$sucursalId][$productoId] = $cantidad;
            }
        }

        $cambios = 0;

        foreach ($porSucursal as $sucursalId => $cantidades) {
            $actuales = StockSucursal::where('sucursal_id', $sucursalId)->whereIn('product_id', array_keys($cantidades))->pluck('cantidad', 'product_id');
            $ajuste = null;

            foreach ($cantidades as $productoId => $nueva) {
                $anterior = (int) ($actuales[$productoId] ?? 0);
                if ($nueva === $anterior) {
                    continue;
                }

                $ajuste ??= AjusteInventario::create([
                    'sucursal_id' => $sucursalId,
                    'user_id' => $usuarioId,
                    'descripcion' => $referencia,
                    'estado' => EstadoAjuste::Aplicado,
                    'aplicado_at' => now(),
                ]);

                AjusteInventarioLinea::create([
                    'ajuste_inventario_id' => $ajuste->id,
                    'product_id' => $productoId,
                    'cantidad_anterior' => $anterior,
                    'cantidad_nueva' => $nueva,
                    'delta' => $nueva - $anterior,
                ]);

                StockSucursal::updateOrCreate(['sucursal_id' => $sucursalId, 'product_id' => $productoId], ['cantidad' => $nueva]);

                MovimientoStock::create([
                    'ajuste_inventario_id' => $ajuste->id,
                    'sucursal_id' => $sucursalId,
                    'product_id' => $productoId,
                    'tipo' => TipoMovimiento::Ajuste,
                    'cantidad' => $nueva - $anterior,
                    'referencia' => $referencia,
                    'fecha' => now(),
                ]);

                Product::whereKey($productoId)->update(['stock' => StockSucursal::where('product_id', $productoId)->sum('cantidad')]);
                $cambios++;
            }
        }

        return $cambios;
    }

    /** @param  list<string>  $valores */
    private function asegurarValores(AttributeType $tipo, array $valores): void
    {
        $existentes = AttributeValue::where('attribute_type_id', $tipo->id)->pluck('valor')->map(fn ($v) => Str::lower($v))->flip();
        $orden = (int) AttributeValue::where('attribute_type_id', $tipo->id)->max('orden');

        foreach (array_unique($valores) as $valor) {
            if (! isset($existentes[Str::lower($valor)])) {
                AttributeValue::create(['attribute_type_id' => $tipo->id, 'valor' => $valor, 'orden' => ++$orden, 'activo' => true]);
                $existentes[Str::lower($valor)] = true;
            }
        }
    }

    /** @return \Illuminate\Support\Collection<int, Sucursal> */
    private function sucursales()
    {
        return Sucursal::where('activo', true)->orderByDesc('is_central')->orderBy('nombre')->get(['id', 'nombre']);
    }

    private function texto(mixed $valor): string
    {
        if (is_float($valor) && floor($valor) == $valor && abs($valor) >= 1e6) {
            // Un código de barras que Excel guardó como número.
            return number_format($valor, 0, '', '');
        }

        return trim((string) $valor);
    }

    /** Acepta 12900, 12900.5, "12.900,50" y "12,900.50". */
    private function numero(string $valor): ?float
    {
        if ($valor === '') {
            return null;
        }

        $limpio = str_replace(['$', ' '], '', $valor);

        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $limpio) || preg_match('/^-?\d+,\d+$/', $limpio)) {
            $limpio = str_replace(['.', ','], ['', '.'], $limpio);
        } elseif (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $limpio)) {
            $limpio = str_replace(',', '', $limpio);
        }

        return is_numeric($limpio) ? (float) $limpio : null;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<string>  $errores
     * @return array{filas: list<array<string, mixed>>, errores: list<string>, resumen: array<string, int>}
     */
    private function resultado(array $filas, array $errores): array
    {
        $coleccion = collect($filas);

        return [
            'filas' => $filas,
            'errores' => $errores,
            'resumen' => [
                'crear' => $coleccion->where('accion', 'crear')->count(),
                'actualizar' => $coleccion->where('accion', 'actualizar')->count(),
                'modelos_nuevos' => $coleccion->where('tipo', 'variante')->whereNull('modelo_id')->pluck('modelo')->map(fn ($m) => Str::lower($m))->unique()->count(),
                'con_stock' => $coleccion->filter(fn ($f) => $f['stock'] !== [])->count(),
            ],
        ];
    }
}
