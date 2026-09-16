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
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Throwable;

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
 * El archivo nunca se carga entero: se lee por tramos de filas (filtro de lectura). La
 * importación corre en la cola por lotes (App\Jobs\*ImportacionProductos): cada lote en su
 * transacción y cada fila en un savepoint, así una fila con error no frena las demás.
 */
class ImportacionProductos
{
    public const COLUMNAS = ['modelo', 'codigo', 'nombre', 'color', 'talle', 'codigo_barras', 'precio', 'costo', 'iva'];

    /**
     * XLSX: PhpSpreadsheet vuelve a recorrer el archivo entero en cada lectura por tramos
     * (el filtro ahorra memoria, no tiempo): el costo crece al cuadrado. 20.000 filas es
     * razonable; más, en CSV, que se lee línea por línea y cada lote arranca en su byte.
     */
    public const MAX_FILAS_XLSX = 20000;

    public const MAX_FILAS_CSV = 200000;

    /** En la e2-micro de producción 500 filas nuevas tardaron hasta 169 s; con 250, ~85 s. */
    public const FILAS_POR_LOTE = 250;

    private const FILAS_POR_LECTURA = 1000;

    private const IVAS = [0.0, 2.5, 5.0, 10.5, 21.0, 27.0];

    /** @var array<string, true> valores de atributo ya verificados en este lote */
    private array $valoresConocidos = [];

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
     * Lo que se valida en el pedido web: encabezado y cantidad de filas, sin leer las celdas.
     *
     * @return array{errores: list<string>, ultima_fila: int, columnas_stock: bool}
     */
    public function inspeccionar(string $ruta): array
    {
        try {
            $ultimaFila = $this->ultimaFila($ruta);
            $estructura = $this->estructura($ruta);
        } catch (Throwable $e) {
            return ['errores' => ['No se pudo leer el archivo: '.$e->getMessage()], 'ultima_fila' => 0, 'columnas_stock' => false];
        }

        $errores = $estructura['errores'];

        $maximo = $this->esCsv($ruta) ? self::MAX_FILAS_CSV : self::MAX_FILAS_XLSX;

        if ($ultimaFila < 2) {
            $errores[] = 'El archivo está vacío o no tiene datos.';
        } elseif ($ultimaFila - 1 > $maximo) {
            $errores[] = $this->esCsv($ruta)
                ? 'El archivo tiene más de '.number_format($maximo, 0, ',', '.').' filas. Dividilo en partes.'
                : 'Un Excel (.xlsx) admite hasta '.number_format($maximo, 0, ',', '.').' filas. Para más, guardalo como CSV (hasta '.number_format(self::MAX_FILAS_CSV, 0, ',', '.').').';
        }

        return ['errores' => $errores, 'ultima_fila' => $ultimaFila, 'columnas_stock' => $estructura['stock'] !== []];
    }

    /**
     * Vista previa: las primeras filas con las mismas validaciones que la importación. No
     * escribe nada.
     *
     * @return array{filas: list<array<string, mixed>>, errores: list<string>, resumen: array<string, int>}
     */
    public function analizar(string $ruta, int $limite = 300): array
    {
        $inspeccion = $this->inspeccionar($ruta);

        if ($inspeccion['errores']) {
            return $this->resultado([], $inspeccion['errores']);
        }

        $estructura = $this->estructura($ruta);
        $atributos = $this->atributosDeVariante();
        $vistos = [];
        $plan = [];
        $errores = [];

        foreach ($this->leerFilas($ruta, 2, $limite + 1) as $n => $fila) {
            [$entrada, $erroresFila] = $this->validarFila($fila, $n, $estructura, $atributos);
            $erroresFila = [...$erroresFila, ...$this->repetidaEnArchivo($fila, $n, $estructura, $vistos)];

            if ($erroresFila) {
                array_push($errores, ...array_map(fn ($e) => "Fila {$n}: {$e}", $erroresFila));
            } else {
                $plan[] = $entrada;
            }
        }

        return $this->resultado($plan, $errores);
    }

    /**
     * Recorre todo el archivo por tramos: cuenta las filas con datos y marca las repetidas
     * (misma variante, código o código de barras que una fila anterior). Esas filas son error.
     *
     * En CSV devuelve además `offsets`: el byte donde empieza cada lote, para que cada lote lea
     * solo su tramo en vez de recorrer el archivo desde el principio.
     *
     * @return array{total: int, ultima_fila: int, repetidas: array<int, array{codigo: ?string, mensaje: string, datos: array<string, string>}>, offsets: array<int, int>}
     */
    public function repetidas(string $ruta): array
    {
        $estructura = $this->estructura($ruta);
        $vistos = [];
        $repetidas = [];
        $offsets = [];
        $total = 0;

        $revisar = function (array $fila, int $n) use (&$total, &$repetidas, &$vistos, $estructura): void {
            $total++;

            if ($mensajes = $this->repetidaEnArchivo($fila, $n, $estructura, $vistos)) {
                $repetidas[$n] = [
                    'codigo' => $this->codigoDeFila($fila, $estructura),
                    'mensaje' => implode(' ', $mensajes),
                    'datos' => $this->datosDeFila($fila, $estructura),
                ];
            }
        };

        if ($this->esCsv($ruta)) {
            $ultima = 1;
            foreach ($this->recorrerCsv($ruta, 2) as [$n, $fila, $byte]) {
                if (($n - 2) % self::FILAS_POR_LOTE === 0) {
                    $offsets[$n] = $byte;
                }
                $ultima = $n;
                if ($this->filaConDatos($fila)) {
                    $revisar($fila, $n);
                }
            }

            return ['total' => $total, 'ultima_fila' => $ultima, 'repetidas' => $repetidas, 'offsets' => $offsets];
        }

        $ultima = $this->ultimaFila($ruta);

        for ($desde = 2; $desde <= $ultima; $desde += self::FILAS_POR_LECTURA) {
            foreach ($this->leerFilas($ruta, $desde, min($ultima, $desde + self::FILAS_POR_LECTURA - 1)) as $n => $fila) {
                $revisar($fila, $n);
            }
        }

        return ['total' => $total, 'ultima_fila' => $ultima, 'repetidas' => $repetidas, 'offsets' => []];
    }

    /**
     * Procesa las filas [$desde, $hasta] de Excel. Tiene que correr dentro de una transacción
     * (la del lote): cada fila va en un savepoint y, si falla, se deshace solo esa fila.
     *
     * @param  array<int, bool>  $saltear  filas que ya tienen un error registrado (repetidas)
     * @param  callable(int, ?string, string, array<string, string>): void  $registrarError
     * @return array{procesadas: int, exitosas: int, errores: int, creados: int, actualizados: int, modelos: int, stock: int}
     */
    public function procesarLote(string $ruta, int $desde, int $hasta, array $saltear, callable $registrarError, ?int $usuarioId, string $referencia, ?int $offset = null): array
    {
        $estructura = $this->estructura($ruta);
        $atributos = $this->atributosDeVariante();
        $conteo = ['procesadas' => 0, 'exitosas' => 0, 'errores' => 0, 'creados' => 0, 'actualizados' => 0, 'modelos' => 0, 'stock' => 0];
        $ajustes = [];
        $ajustesNuevos = [];
        $this->valoresConocidos = [];

        foreach ($this->leerFilas($ruta, $desde, $hasta, $offset) as $n => $fila) {
            $conteo['procesadas']++;

            if (isset($saltear[$n])) {
                $conteo['errores']++;

                continue;
            }

            [$entrada, $errores] = $this->validarFila($fila, $n, $estructura, $atributos);

            if ($errores) {
                $registrarError($n, $this->codigoDeFila($fila, $estructura), implode(' ', array_map(fn ($e) => Str::ucfirst($e), $errores)), $this->datosDeFila($fila, $estructura));
                $conteo['errores']++;

                continue;
            }

            // El ajuste de cada sucursal se busca o crea fuera del savepoint de la fila: si la
            // fila falla no se lleva el ajuste que usan las demás.
            foreach (array_keys($entrada['stock']) as $sucursalId) {
                if (! isset($ajustes[$sucursalId])) {
                    $ajuste = AjusteInventario::where('sucursal_id', $sucursalId)->where('descripcion', $referencia)->first();
                    if (! $ajuste) {
                        $ajuste = AjusteInventario::create([
                            'sucursal_id' => $sucursalId,
                            'user_id' => $usuarioId,
                            'descripcion' => $referencia,
                            'estado' => EstadoAjuste::Aplicado,
                            'aplicado_at' => now(),
                        ]);
                        $ajustesNuevos[] = $ajuste->id;
                    }
                    $ajustes[$sucursalId] = $ajuste->id;
                }
            }

            try {
                $resultado = DB::transaction(function () use ($entrada, $atributos, $ajustes, $referencia) {
                    $guardado = $entrada['tipo'] === 'simple'
                        ? ['producto' => $this->guardarSimple($entrada), 'modelo_nuevo' => false]
                        : $this->guardarVariante($entrada, $atributos);

                    return $guardado + ['stock' => $this->aplicarStock($guardado['producto']->id, $entrada['stock'], $ajustes, $referencia)];
                });
            } catch (Throwable $e) {
                report($e);
                $registrarError($n, $this->codigoDeFila($fila, $estructura), 'No se pudo guardar la fila: '.Str::limit($e->getMessage(), 200), $this->datosDeFila($fila, $estructura));
                $conteo['errores']++;

                continue;
            }

            $conteo['exitosas']++;
            $conteo[$entrada['accion'] === 'crear' ? 'creados' : 'actualizados']++;
            $conteo['modelos'] += $resultado['modelo_nuevo'] ? 1 : 0;
            $conteo['stock'] += $resultado['stock'];
        }

        // Un ajuste creado en este lote cuyas filas fallaron todas quedaría vacío.
        if ($ajustesNuevos) {
            AjusteInventario::whereIn('id', $ajustesNuevos)->whereDoesntHave('lineas')->delete();
        }

        return $conteo;
    }

    /**
     * Validaciones de una fila, con el catálogo actual (incluye lo que ya creó este mismo
     * lote: las consultas corren dentro de su transacción). No valida repetidas en el archivo.
     *
     * @param  list<mixed>  $fila
     * @param  array{columnas: array<string, int>, stock: array<int, Sucursal>}  $estructura
     * @param  array{color: ?AttributeType, talle: ?AttributeType}  $atributos
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function validarFila(array $fila, int $n, array $estructura, array $atributos): array
    {
        $columnas = $estructura['columnas'];
        $celda = fn (string $col) => isset($columnas[$col]) ? $this->texto($fila[$columnas[$col]] ?? null) : '';
        $errores = [];

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

        foreach (['precio' => [$celda('precio'), $precio], 'costo' => [$celda('costo'), $costo]] as $campo => [$crudo, $valor]) {
            // $valor null con celda llena = no se pudo leer como número.
            if ($crudo !== '' && ($valor === null || $valor < 0)) {
                $errores[] = "{$campo} \"{$crudo}\" no es un número válido.";
            }
        }
        if ($iva !== null && ! in_array($iva, self::IVAS, true)) {
            $errores[] = "iva \"{$ivaCrudo}\" tiene que ser 0, 2.5, 5, 10.5, 21 o 27.";
        }
        if ($barras !== '' && ! preg_match('/^\d{8,14}$/', $barras)) {
            $errores[] = "codigo_barras \"{$barras}\" tiene que tener entre 8 y 14 dígitos.";
        }

        $stock = [];
        foreach ($estructura['stock'] as $indice => $sucursal) {
            $crudo = $this->texto($fila[$indice] ?? null);
            if ($crudo === '') {
                continue;
            }
            $cantidad = $this->numero($crudo);
            if ($cantidad === null || $cantidad < 0 || floor($cantidad) != $cantidad) {
                $errores[] = "stock {$sucursal->nombre} \"{$crudo}\" tiene que ser un entero ≥ 0.";

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
                $errores[] = "la variante del modelo {$modelo} necesita color y talle.";
            }
            if (! $atributos['color'] || ! $atributos['talle']) {
                $errores[] = 'faltan los atributos "color" y "talle" en Configuración → Atributos.';
            }

            $padre = Product::where('codigo_interno', $modelo)->first();
            if ($padre && $padre->product_type !== ProductType::CONFIGURABLE) {
                $errores[] = "{$modelo} es un producto simple, no un modelo con variantes.";
                $padre = null;
            }

            $existente = $padre && $color !== '' && $talle !== ''
                ? Product::where('parent_id', $padre->id)->where('color', $color)->where('n_talle', $talle)->first()
                : null;

            $entrada['tipo'] = 'variante';
            $entrada['modelo_id'] = $padre?->id;
            $entrada['producto_id'] = $existente?->id;
            $entrada['accion'] = $existente ? 'actualizar' : 'crear';

            if (! $padre && $nombre === '') {
                $errores[] = "el modelo {$modelo} es nuevo: falta el nombre.";
            }
            if (! $existente && $codigo !== '' && Product::withTrashed()->where('codigo_interno', $codigo)->exists()) {
                $errores[] = "el código {$codigo} ya lo usa otro producto.";
            }
        } else {
            if ($codigo === '') {
                $errores[] = 'un producto simple necesita codigo (o completá modelo, color y talle si es una variante).';
            }

            $existente = $codigo !== '' ? Product::where('codigo_interno', $codigo)->first() : null;
            if ($existente && ($existente->product_type === ProductType::CONFIGURABLE || $existente->parent_id)) {
                $errores[] = "{$codigo} es un modelo o una variante: cargalo con las columnas modelo, color y talle.";
                $existente = null;
            }

            $entrada['tipo'] = 'simple';
            $entrada['producto_id'] = $existente?->id;
            $entrada['accion'] = $existente ? 'actualizar' : 'crear';

            if (! $existente && $nombre === '') {
                $errores[] = "{$codigo} es nuevo: falta el nombre.";
            }
        }

        if ($entrada['accion'] === 'crear' && $precio === null) {
            $errores[] = 'falta el precio para crear el producto.';
        }

        if ($barras !== '') {
            $duenio = Product::where('codigo_barras', $barras)->first(['id', 'codigo_interno']);
            if ($duenio && $duenio->id !== $entrada['producto_id']) {
                $errores[] = "el código de barras {$barras} ya es de {$duenio->codigo_interno}.";
            }
        }

        return [$entrada, $errores];
    }

    /**
     * @param  list<mixed>  $fila
     * @param  array{columnas: array<string, int>}  $estructura
     * @param  array<string, array<string, int>>  $vistos
     * @return list<string>
     */
    private function repetidaEnArchivo(array $fila, int $n, array $estructura, array &$vistos): array
    {
        $columnas = $estructura['columnas'];
        $celda = fn (string $col) => isset($columnas[$col]) ? $this->texto($fila[$columnas[$col]] ?? null) : '';
        $modelo = $celda('modelo');
        $codigo = $celda('codigo');
        $barras = $celda('codigo_barras');
        $mensajes = [];

        if ($modelo !== '') {
            $color = $celda('color');
            $talle = $celda('talle');
            $clave = Str::lower("{$modelo}|{$color}|{$talle}");
            if (isset($vistos['clave'][$clave])) {
                $mensajes[] = "{$modelo} {$color} / {$talle} ya está en la fila {$vistos['clave'][$clave]}.";
            } else {
                $vistos['clave'][$clave] = $n;
            }
        }

        if ($codigo !== '') {
            $claveCodigo = Str::lower($codigo);
            if (isset($vistos['codigo'][$claveCodigo])) {
                $mensajes[] = "el código {$codigo} ya está en la fila {$vistos['codigo'][$claveCodigo]}.";
            } else {
                $vistos['codigo'][$claveCodigo] = $n;
            }
        }

        if ($barras !== '') {
            if (isset($vistos['barras'][$barras])) {
                $mensajes[] = "el código de barras {$barras} ya está en la fila {$vistos['barras'][$barras]}.";
            } else {
                $vistos['barras'][$barras] = $n;
            }
        }

        return $mensajes;
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
     * @param  array<string, mixed>  $f
     * @param  array{color: AttributeType, talle: AttributeType}  $atributos
     * @return array{producto: Product, modelo_nuevo: bool}
     */
    private function guardarVariante(array $f, array $atributos): array
    {
        $padre = $f['modelo_id'] ? Product::find($f['modelo_id']) : null;
        $modeloNuevo = false;

        if (! $padre) {
            $padre = Product::create([
                'product_type' => ProductType::CONFIGURABLE,
                'codigo_interno' => $f['modelo'],
                'nombre' => $f['nombre'],
                'precio' => $f['precio'] ?? 0,
                'publico' => $f['precio'] ?? 0,
                'costo' => $f['costo'] ?? 0,
                'iva' => $f['iva'] ?? 21,
                'stock' => 0,
                'estado' => 1,
                'es_vendible' => false,
                'remitible' => true,
            ]);
            $modeloNuevo = true;
        }

        $this->asegurarValor($atributos['color'], $f['color']);
        $this->asegurarValor($atributos['talle'], $f['talle']);

        $variante = $f['producto_id'] ? Product::find($f['producto_id']) : null;

        if (! $variante) {
            $variante = $this->configurables->addVariantsToConfigurable($padre, [[
                'attributes' => [
                    ['slug' => $atributos['color']->slug, 'product_column' => $atributos['color']->product_column, 'value' => $f['color']],
                    ['slug' => $atributos['talle']->slug, 'product_column' => $atributos['talle']->product_column, 'value' => $f['talle']],
                ],
                'codigo_barras' => $f['codigo_barras'] ?: null,
            ]])->first();
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

        return ['producto' => $variante, 'modelo_nuevo' => $modeloNuevo];
    }

    /**
     * Deja el stock de cada sucursal en la cantidad del archivo, como una línea del ajuste de
     * inventario de esta importación. Cantidad igual a la actual = no se toca (por eso un
     * reintento no genera movimientos de más).
     *
     * @param  array<int, int>  $cantidades  [sucursal_id => cantidad]
     * @param  array<int, int>  $ajustes  [sucursal_id => ajuste_inventario_id]
     */
    private function aplicarStock(int $productoId, array $cantidades, array $ajustes, string $referencia): int
    {
        $cambios = 0;

        foreach ($cantidades as $sucursalId => $nueva) {
            $anterior = (int) StockSucursal::where('sucursal_id', $sucursalId)->where('product_id', $productoId)->value('cantidad');

            if ($nueva === $anterior) {
                continue;
            }

            AjusteInventarioLinea::create([
                'ajuste_inventario_id' => $ajustes[$sucursalId],
                'product_id' => $productoId,
                'cantidad_anterior' => $anterior,
                'cantidad_nueva' => $nueva,
                'delta' => $nueva - $anterior,
            ]);

            StockSucursal::updateOrCreate(['sucursal_id' => $sucursalId, 'product_id' => $productoId], ['cantidad' => $nueva]);

            MovimientoStock::create([
                'ajuste_inventario_id' => $ajustes[$sucursalId],
                'sucursal_id' => $sucursalId,
                'product_id' => $productoId,
                'tipo' => TipoMovimiento::Ajuste,
                'cantidad' => $nueva - $anterior,
                'referencia' => $referencia,
                'fecha' => now(),
            ]);

            $cambios++;
        }

        if ($cambios) {
            Product::recalcularStock($productoId);
        }

        return $cambios;
    }

    private function asegurarValor(AttributeType $tipo, string $valor): void
    {
        // Cache por lote: 250 filas de un mismo modelo repiten los mismos colores y talles.
        $clave = $tipo->id.'|'.Str::lower($valor);
        if (isset($this->valoresConocidos[$clave])) {
            return;
        }

        $existe = AttributeValue::where('attribute_type_id', $tipo->id)->where('valor', $valor)->exists();
        $this->valoresConocidos[$clave] = true;

        if (! $existe) {
            AttributeValue::create([
                'attribute_type_id' => $tipo->id,
                'valor' => $valor,
                'orden' => (int) AttributeValue::where('attribute_type_id', $tipo->id)->max('orden') + 1,
                'activo' => true,
            ]);
        }
    }

    /** @return array{color: ?AttributeType, talle: ?AttributeType} */
    private function atributosDeVariante(): array
    {
        $tipos = AttributeType::whereIn('slug', ['color', 'talle'])->whereNotNull('product_column')->get()->keyBy('slug');

        return ['color' => $tipos['color'] ?? null, 'talle' => $tipos['talle'] ?? null];
    }

    /**
     * Columnas del encabezado (fila 1) y columnas de stock por sucursal.
     *
     * @return array{columnas: array<string, int>, stock: array<int, Sucursal>, errores: list<string>}
     */
    private function estructura(string $ruta): array
    {
        $encabezado = $this->leerFilas($ruta, 1, 1)[1] ?? [];
        $originales = array_map(fn ($h) => Str::squish((string) $h), $encabezado);
        $columnas = array_flip(array_filter(array_map(fn ($h) => Str::lower($h), $originales), fn ($h) => $h !== ''));
        $errores = [];

        foreach (['nombre', 'precio'] as $obligatoria) {
            if (! isset($columnas[$obligatoria])) {
                $errores[] = "Falta la columna \"{$obligatoria}\". Descargá la plantilla.";
            }
        }
        if (! isset($columnas['codigo']) && ! isset($columnas['modelo'])) {
            $errores[] = 'Falta la columna "codigo" o "modelo". Descargá la plantilla.';
        }

        $sucursalesPorNombre = $this->sucursales()->keyBy(fn (Sucursal $s) => Str::lower($s->nombre));
        $stock = [];
        foreach ($columnas as $encabezadoColumna => $indice) {
            if (str_starts_with($encabezadoColumna, 'stock ')) {
                $sucursal = $sucursalesPorNombre[trim(substr($encabezadoColumna, 6))] ?? null;
                $sucursal
                    ? $stock[$indice] = $sucursal
                    : $errores[] = "La columna \"{$originales[$indice]}\" no corresponde a ninguna sucursal activa.";
            }
        }

        return ['columnas' => $columnas, 'stock' => $stock, 'errores' => $errores];
    }

    private function ultimaFila(string $ruta): int
    {
        if ($this->esCsv($ruta)) {
            $ultima = 0;
            foreach ($this->recorrerCsv($ruta, 1) as [$n]) {
                $ultima = $n;
            }

            return $ultima;
        }

        $info = IOFactory::createReaderForFile($ruta)->listWorksheetInfo($ruta);

        return (int) ($info[0]['totalRows'] ?? 0);
    }

    private function esCsv(string $ruta): bool
    {
        return in_array(strtolower(pathinfo($ruta, PATHINFO_EXTENSION)), ['csv', 'txt'], true);
    }

    /** @param  list<mixed>  $fila */
    private function filaConDatos(array $fila): bool
    {
        return collect($fila)->contains(fn ($v) => trim((string) $v) !== '');
    }

    /**
     * Recorre un CSV registro por registro sin cargarlo: [número de fila, celdas, byte donde
     * empieza el registro]. Separador `;` o `,` según el encabezado (Excel en español guarda
     * con `;`); acepta UTF-8 con o sin BOM y Windows-1252.
     *
     * @return \Generator<int, array{0: int, 1: list<string>, 2: int}>
     */
    private function recorrerCsv(string $ruta, int $desde, ?int $offset = null): \Generator
    {
        $archivo = fopen($ruta, 'rb');

        try {
            $primera = (string) fgets($archivo);
            $separador = substr_count($primera, ';') >= substr_count($primera, ',') ? ';' : ',';
            $bom = str_starts_with($primera, "\xEF\xBB\xBF") ? 3 : 0;

            if ($offset !== null && $offset > 0) {
                fseek($archivo, $offset);
                $n = $desde - 1;
            } else {
                fseek($archivo, $bom);
                $n = 0;
            }

            while (true) {
                $byte = ftell($archivo);
                $celdas = fgetcsv($archivo, null, $separador, '"', '');
                if ($celdas === false) {
                    break;
                }
                $n++;
                if ($n < $desde) {
                    continue;
                }

                yield [$n, array_map(fn ($v) => $v === null ? '' : (mb_check_encoding($v, 'UTF-8') ? $v : mb_convert_encoding($v, 'UTF-8', 'Windows-1252')), $celdas), $byte];
            }
        } finally {
            fclose($archivo);
        }
    }

    /**
     * Lee solo las filas [$desde, $hasta] de la primera hoja, sin cargar el resto del archivo.
     * En CSV, con `$offset` (byte donde empieza `$desde`) va directo a esa fila.
     *
     * @return array<int, list<mixed>> fila de Excel => celdas (A = 0); sin las filas vacías
     */
    private function leerFilas(string $ruta, int $desde, int $hasta, ?int $offset = null): array
    {
        if ($this->esCsv($ruta)) {
            $filas = [];
            foreach ($this->recorrerCsv($ruta, $desde, $offset) as [$n, $celdas]) {
                if ($n > $hasta) {
                    break;
                }
                if ($this->filaConDatos($celdas)) {
                    $filas[$n] = $celdas;
                }
            }

            return $filas;
        }

        $lector = IOFactory::createReaderForFile($ruta);
        $lector->setReadDataOnly(true);
        $lector->setReadFilter(new class($desde, $hasta) implements IReadFilter
        {
            public function __construct(private int $desde, private int $hasta) {}

            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row >= $this->desde && $row <= $this->hasta;
            }
        });

        if (! $lector instanceof Csv) {
            $lector->setLoadSheetsOnly([$lector->listWorksheetNames($ruta)[0]]);
        }

        $libro = $lector->load($ruta);
        $hoja = $libro->getSheet(0);
        $celdas = $hoja->rangeToArray("A{$desde}:{$hoja->getHighestColumn()}{$hasta}", null, true, false, false);
        $libro->disconnectWorksheets();
        unset($libro, $hoja);

        $filas = [];
        foreach ($celdas as $i => $fila) {
            if (collect($fila)->contains(fn ($v) => trim((string) $v) !== '')) {
                $filas[$desde + $i] = $fila;
            }
        }

        return $filas;
    }

    /** @param  list<mixed>  $fila */
    private function codigoDeFila(array $fila, array $estructura): ?string
    {
        foreach (['codigo', 'modelo'] as $col) {
            if (isset($estructura['columnas'][$col]) && ($valor = $this->texto($fila[$estructura['columnas'][$col]] ?? null)) !== '') {
                return Str::limit($valor, 100, '');
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $fila
     * @return array<string, string>
     */
    private function datosDeFila(array $fila, array $estructura): array
    {
        $datos = [];
        foreach ($estructura['columnas'] as $nombre => $indice) {
            $valor = $this->texto($fila[$indice] ?? null);
            if ($valor !== '') {
                $datos[$nombre] = Str::limit($valor, 120);
            }
        }

        return $datos;
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
