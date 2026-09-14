<?php

namespace App\Livewire\Sucursales;

use App\Enums\EstadoAjuste;
use App\Enums\TipoMovimiento;
use App\Models\AjusteInventario;
use App\Models\AjusteInventarioLinea;
use App\Models\MovimientoStock;
use App\Models\Product;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AjusteStock extends Component
{
    use WithFileUploads;

    public ?int $sucursalId = null;

    public string $modo = 'manual';

    public string $busqueda = '';

    /** @var array<int, string> */
    public array $stockNuevo = [];

    public string $descripcion = '';

    public $archivo = null;

    /** @var array<int, array{product_id: int, codigo_interno: string, nombre: string, stock_actual: int, nueva_cantidad: int, delta: int}> */
    public array $previsualizacion = [];

    /** @var array<int, string> */
    public array $erroresImportacion = [];

    /** ID del borrador activo, si existe */
    public ?int $ajusteId = null;

    public function mount(): void
    {
        // Defaultear a la primera sucursal NO central activa
        $this->sucursalId = Sucursal::where('activo', true)
            ->where('is_central', false)
            ->orderBy('nombre')
            ->first()?->id
            ?? Sucursal::where('activo', true)->orderBy('nombre')->first()?->id;

        $this->cargarBorradorActivo();
        $this->cargarStockActual();
    }

    public function updatedSucursalId(): void
    {
        $this->stockNuevo = [];
        $this->ajusteId = null;
        $this->descripcion = '';
        $this->previsualizacion = [];
        $this->erroresImportacion = [];
        $this->cargarBorradorActivo();
        $this->cargarStockActual();
    }

    private function cargarBorradorActivo(): void
    {
        if (! $this->sucursalId) {
            return;
        }

        $borrador = AjusteInventario::where('sucursal_id', $this->sucursalId)
            ->where('estado', EstadoAjuste::Borrador)
            ->latest()
            ->first();

        if ($borrador) {
            $this->ajusteId = $borrador->id;
            $this->descripcion = $borrador->descripcion ?? '';

            // Cargar las cantidades del borrador en stockNuevo
            foreach ($borrador->lineas as $linea) {
                $this->stockNuevo[$linea->product_id] = (string) $linea->cantidad_nueva;
            }
        }
    }

    private function cargarStockActual(): void
    {
        if (! $this->sucursalId) {
            return;
        }

        $stocks = StockSucursal::where('sucursal_id', $this->sucursalId)
            ->pluck('cantidad', 'product_id');

        foreach ($stocks as $productId => $cantidad) {
            if (! isset($this->stockNuevo[$productId])) {
                $this->stockNuevo[$productId] = (string) $cantidad;
            }
        }
    }

    public function guardarBorrador(): void
    {
        $this->validate(['sucursalId' => 'required|integer|exists:sucursales,id']);

        $stockActual = StockSucursal::where('sucursal_id', $this->sucursalId)
            ->pluck('cantidad', 'product_id');

        DB::transaction(function () use ($stockActual): void {
            $ajuste = AjusteInventario::updateOrCreate(
                ['id' => $this->ajusteId ?? 0],
                [
                    'sucursal_id' => $this->sucursalId,
                    'user_id' => Auth::id(),
                    'descripcion' => $this->descripcion ?: null,
                    'estado' => EstadoAjuste::Borrador,
                ]
            );

            $this->ajusteId = $ajuste->id;

            // Sincronizar líneas: solo guardar las que tienen cambio
            $lineas = [];
            foreach ($this->stockNuevo as $productId => $nuevaCantidad) {
                $productId = (int) $productId;
                $nueva = max(0, (int) $nuevaCantidad);
                $actual = (int) ($stockActual[$productId] ?? 0);

                if ($nueva !== $actual) {
                    $lineas[$productId] = [
                        'ajuste_inventario_id' => $ajuste->id,
                        'product_id' => $productId,
                        'cantidad_anterior' => $actual,
                        'cantidad_nueva' => $nueva,
                        'delta' => $nueva - $actual,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ];
                }
            }

            // Eliminar líneas que ya no tienen diferencia
            AjusteInventarioLinea::where('ajuste_inventario_id', $ajuste->id)
                ->whereNotIn('product_id', array_keys($lineas))
                ->delete();

            // Upsert de las líneas actuales
            if (! empty($lineas)) {
                AjusteInventarioLinea::upsert(
                    array_values($lineas),
                    ['ajuste_inventario_id', 'product_id'],
                    ['cantidad_anterior', 'cantidad_nueva', 'delta', 'updated_at']
                );
            }
        });

        session()->flash('success', 'Borrador guardado correctamente.');
    }

    public function aplicarAjuste(): void
    {
        $this->validate(['sucursalId' => 'required|integer|exists:sucursales,id']);

        $stockActual = StockSucursal::where('sucursal_id', $this->sucursalId)
            ->pluck('cantidad', 'product_id');

        $cambios = [];

        foreach ($this->stockNuevo as $productId => $nuevaCantidad) {
            $productId = (int) $productId;
            $nueva = max(0, (int) $nuevaCantidad);
            $actual = (int) ($stockActual[$productId] ?? 0);

            if ($nueva !== $actual) {
                $cambios[$productId] = ['nueva' => $nueva, 'delta' => $nueva - $actual, 'anterior' => $actual];
            }
        }

        if (empty($cambios)) {
            session()->flash('info', 'No hay cambios para aplicar.');

            return;
        }

        $sucursalId = $this->sucursalId;
        $descripcion = $this->descripcion ?: 'Ajuste de inventario';
        $ajusteId = $this->ajusteId;

        DB::transaction(function () use ($cambios, $sucursalId, $descripcion, $ajusteId): void {
            // Crear o actualizar el registro de ajuste de inventario
            if ($ajusteId) {
                $ajuste = AjusteInventario::findOrFail($ajusteId);
                $ajuste->update([
                    'descripcion' => $descripcion,
                    'estado' => EstadoAjuste::Aplicado,
                    'aplicado_at' => now(),
                ]);
            } else {
                $ajuste = AjusteInventario::create([
                    'sucursal_id' => $sucursalId,
                    'user_id' => Auth::id(),
                    'descripcion' => $descripcion,
                    'estado' => EstadoAjuste::Aplicado,
                    'aplicado_at' => now(),
                ]);
            }

            // Limpiar y guardar líneas definitivas
            $ajuste->lineas()->delete();

            foreach ($cambios as $productId => $datos) {
                AjusteInventarioLinea::create([
                    'ajuste_inventario_id' => $ajuste->id,
                    'product_id' => $productId,
                    'cantidad_anterior' => $datos['anterior'],
                    'cantidad_nueva' => $datos['nueva'],
                    'delta' => $datos['delta'],
                ]);

                StockSucursal::updateOrCreate(
                    ['sucursal_id' => $sucursalId, 'product_id' => $productId],
                    ['cantidad' => $datos['nueva']]
                );

                MovimientoStock::create([
                    'ajuste_inventario_id' => $ajuste->id,
                    'sucursal_id' => $sucursalId,
                    'product_id' => $productId,
                    'tipo' => TipoMovimiento::Ajuste,
                    'cantidad' => $datos['delta'],
                    'referencia' => $descripcion,
                    'fecha' => now(),
                ]);

                $totalGlobal = StockSucursal::where('product_id', $productId)->sum('cantidad');
                Product::where('id', $productId)->update(['stock' => $totalGlobal]);
            }
        });

        session()->flash('success', 'Ajuste aplicado: '.count($cambios).' producto(s) actualizados.');
        $this->ajusteId = null;
        $this->descripcion = '';
        $this->stockNuevo = [];
        $this->cargarStockActual();
    }

    public function descartarBorrador(): void
    {
        if ($this->ajusteId) {
            AjusteInventario::find($this->ajusteId)?->delete();
        }

        $this->ajusteId = null;
        $this->descripcion = '';
        $this->stockNuevo = [];
        $this->cargarStockActual();

        session()->flash('info', 'Borrador descartado.');
    }

    public function updatedArchivo(): void
    {
        $this->previsualizacion = [];
        $this->erroresImportacion = [];

        if (! $this->archivo) {
            return;
        }

        try {
            $path = $this->archivo->getRealPath();
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            $this->erroresImportacion[] = 'No se pudo leer el archivo: '.$e->getMessage();

            return;
        }

        if (empty($rows) || count($rows) < 2) {
            $this->erroresImportacion[] = 'El archivo está vacío o no tiene datos.';

            return;
        }

        $porCodigoInterno = Product::whereNotNull('codigo_interno')->pluck('id', 'codigo_interno');
        $porCodigoBarras = Product::whereNotNull('codigo_barras')->pluck('id', 'codigo_barras');
        $stockActual = StockSucursal::where('sucursal_id', $this->sucursalId)->pluck('cantidad', 'product_id');

        $previsualizacion = [];
        $errores = [];

        $startRow = (isset($rows[0][0]) && strtolower((string) $rows[0][0]) === 'codigo_interno') ? 1 : 0;

        foreach (array_slice($rows, $startRow) as $lineNum => $row) {
            $lineLabel = $lineNum + $startRow + 2;
            $codigoInterno = trim((string) ($row[0] ?? ''));
            $codigoBarras = trim((string) ($row[1] ?? ''));
            $nuevaCantidadRaw = trim((string) ($row[3] ?? ''));

            if ($codigoInterno === '' && $codigoBarras === '' && $nuevaCantidadRaw === '') {
                continue;
            }

            if ($nuevaCantidadRaw === '') {
                continue;
            }

            if (! is_numeric($nuevaCantidadRaw) || (int) $nuevaCantidadRaw < 0) {
                $errores[] = "Fila {$lineLabel}: nueva_cantidad '{$nuevaCantidadRaw}' inválida (debe ser número ≥ 0).";

                continue;
            }

            $productId = null;
            if ($codigoInterno !== '') {
                $productId = $porCodigoInterno[$codigoInterno] ?? null;
            }
            if (! $productId && $codigoBarras !== '') {
                $productId = $porCodigoBarras[$codigoBarras] ?? null;
            }

            if (! $productId) {
                $ref = $codigoInterno ?: $codigoBarras;
                $errores[] = "Fila {$lineLabel}: producto '{$ref}' no encontrado.";

                continue;
            }

            $nueva = (int) $nuevaCantidadRaw;
            $actual = (int) ($stockActual[$productId] ?? 0);

            $previsualizacion[] = [
                'product_id' => $productId,
                'codigo_interno' => $codigoInterno,
                'nombre' => (string) ($row[2] ?? ''),
                'stock_actual' => $actual,
                'nueva_cantidad' => $nueva,
                'delta' => $nueva - $actual,
            ];
        }

        $this->previsualizacion = $previsualizacion;
        $this->erroresImportacion = $errores;
    }

    public function aplicarImportacion(): void
    {
        if (empty($this->previsualizacion)) {
            return;
        }

        $sucursalId = $this->sucursalId;
        $descripcion = $this->descripcion ?: 'Ajuste por importación Excel';

        DB::transaction(function () use ($sucursalId, $descripcion): void {
            $ajuste = AjusteInventario::create([
                'sucursal_id' => $sucursalId,
                'user_id' => Auth::id(),
                'descripcion' => $descripcion,
                'estado' => EstadoAjuste::Aplicado,
                'aplicado_at' => now(),
            ]);

            foreach ($this->previsualizacion as $fila) {
                AjusteInventarioLinea::create([
                    'ajuste_inventario_id' => $ajuste->id,
                    'product_id' => $fila['product_id'],
                    'cantidad_anterior' => $fila['stock_actual'],
                    'cantidad_nueva' => $fila['nueva_cantidad'],
                    'delta' => $fila['delta'],
                ]);

                StockSucursal::updateOrCreate(
                    ['sucursal_id' => $sucursalId, 'product_id' => $fila['product_id']],
                    ['cantidad' => $fila['nueva_cantidad']]
                );

                if ($fila['delta'] !== 0) {
                    MovimientoStock::create([
                        'ajuste_inventario_id' => $ajuste->id,
                        'sucursal_id' => $sucursalId,
                        'product_id' => $fila['product_id'],
                        'tipo' => TipoMovimiento::Ajuste,
                        'cantidad' => $fila['delta'],
                        'referencia' => $descripcion,
                        'fecha' => now(),
                    ]);
                }

                $totalGlobal = StockSucursal::where('product_id', $fila['product_id'])->sum('cantidad');
                Product::where('id', $fila['product_id'])->update(['stock' => $totalGlobal]);
            }
        });

        session()->flash('success', 'Importación aplicada: '.count($this->previsualizacion).' producto(s) actualizados.');
        $this->resetImportacion();
        $this->cargarStockActual();
    }

    public function resetImportacion(): void
    {
        $this->archivo = null;
        $this->previsualizacion = [];
        $this->erroresImportacion = [];
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $sucursales = Sucursal::where('activo', true)
            ->orderBy('is_central')
            ->orderBy('nombre')
            ->get();

        $query = Product::query()
            ->select('products.id', 'products.codigo_interno', 'products.codigo_barras', 'products.nombre', 'products.marca')
            ->leftJoin('stock_sucursal', function ($join): void {
                $join->on('products.id', '=', 'stock_sucursal.product_id')
                    ->where('stock_sucursal.sucursal_id', '=', $this->sucursalId);
            })
            ->selectRaw('COALESCE(stock_sucursal.cantidad, 0) as stock_actual')
            ->whereNull('products.deleted_at')
            ->where('products.es_vendible', true);

        if ($this->busqueda) {
            $query->where(function ($q): void {
                $q->where('products.nombre', 'like', '%'.$this->busqueda.'%')
                    ->orWhere('products.codigo_interno', 'like', '%'.$this->busqueda.'%')
                    ->orWhere('products.codigo_barras', 'like', '%'.$this->busqueda.'%');
            });
        } else {
            $query->where(function ($q): void {
                $q->whereNotNull('stock_sucursal.cantidad')
                    ->where('stock_sucursal.cantidad', '>', 0);
            });
        }

        $productos = $query->orderBy('products.nombre')->get();

        $historial = AjusteInventario::with(['user', 'lineas'])
            ->where('sucursal_id', $this->sucursalId)
            ->where('estado', EstadoAjuste::Aplicado)
            ->latest('aplicado_at')
            ->limit(10)
            ->get();

        return view('livewire.sucursales.ajuste-stock', [
            'sucursales' => $sucursales,
            'productos' => $productos,
            'historial' => $historial,
        ]);
    }
}
