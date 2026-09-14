<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Jobs\PrepararImportacionProductos;
use App\Jobs\ProcesarLoteImportacionProductos;
use App\Livewire\Products\Importar;
use App\Models\AjusteInventario;
use App\Models\ImportacionProducto;
use App\Models\ImportacionProductoLote;
use App\Models\MovimientoStock;
use App\Models\Product;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ImportacionProductos;
use App\Support\Ean13;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Importación por lotes en la cola. En los tests la cola es `sync`: dispatch() corre la
 * cadena completa (preparar → lotes → finalizar) al encolar.
 */
class ImportacionProductosTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $central;

    private Sucursal $villaBosh;

    /** @var list<string> */
    private array $archivos = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Una migración ya crea la sucursal central.
        $this->central = Sucursal::firstOrCreate(['nombre' => 'Central'], ['is_central' => true]);
        $this->villaBosh = Sucursal::create(['nombre' => 'Villa Bosh']);
    }

    protected function tearDown(): void
    {
        foreach ($this->archivos as $archivo) {
            @unlink($archivo);
        }
        Storage::disk('local')->deleteDirectory('importaciones');

        parent::tearDown();
    }

    /** @param  list<list<mixed>>  $filas */
    private function excel(array $filas): string
    {
        $libro = new Spreadsheet;
        $libro->getActiveSheet()->fromArray($filas, null, 'A1', true);
        $ruta = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($libro))->save($ruta);

        return $this->archivos[] = $ruta;
    }

    private function catalogo(): string
    {
        return $this->excel([
            ['modelo', 'codigo', 'nombre', 'color', 'talle', 'codigo_barras', 'precio', 'costo', 'iva', 'stock Central', 'stock Villa Bosh'],
            ['REM-9', null, 'Remera oversize', 'Negro', 'M', null, '12.900,50', 5000, 21, 10, 3],
            ['REM-9', null, 'Remera oversize', 'Negro', 'L', null, 12900.5, 5000, 21, null, 0],
            [null, 'GOR-1', 'Gorra trucker', null, null, 7791234000017, 9900, 3500, null, null, 4],
        ]);
    }

    /** Lo mismo que hace la pantalla: guarda el archivo, crea el registro y encola. */
    private function importar(string $archivo, ?int $usuarioId = null): ImportacionProducto
    {
        $ruta = 'importaciones/'.Str::uuid().'.xlsx';
        Storage::disk('local')->put($ruta, file_get_contents($archivo));

        $registro = ImportacionProducto::create([
            'user_id' => $usuarioId,
            'archivo' => $ruta,
            'nombre_original' => basename($archivo),
            'estado' => ImportacionProducto::PENDIENTE,
        ]);

        PrepararImportacionProductos::dispatch($registro->id);

        return $registro->fresh();
    }

    public function test_crea_modelo_variantes_y_simple_con_stock_por_sucursal(): void
    {
        $importacion = $this->importar($this->catalogo());

        $this->assertSame(ImportacionProducto::COMPLETADA, $importacion->estado);
        $this->assertSame([3, 3, 3, 0, 3, 0, 1, 3], [
            $importacion->total_filas, $importacion->filas_procesadas, $importacion->filas_exitosas, $importacion->filas_con_error,
            $importacion->creados, $importacion->actualizados, $importacion->modelos_nuevos, $importacion->cambios_stock,
        ]);
        $this->assertSame(100, $importacion->porcentaje());
        $this->assertNotNull($importacion->finalizado_at);
        Storage::disk('local')->assertMissing($importacion->archivo);

        $modelo = Product::where('codigo_interno', 'REM-9')->sole();
        $this->assertSame(ProductType::CONFIGURABLE, $modelo->product_type);
        $this->assertFalse((bool) $modelo->es_vendible);

        $negroM = Product::where('parent_id', $modelo->id)->where('n_talle', 'M')->sole();
        $this->assertSame('REM-9-NEG-M', $negroM->codigo_interno);
        $this->assertEquals(12900.5, $negroM->precio);
        $this->assertTrue(Ean13::valido($negroM->codigo_barras));
        $this->assertSame(10, (int) StockSucursal::where('product_id', $negroM->id)->where('sucursal_id', $this->central->id)->value('cantidad'));
        $this->assertSame(3, (int) StockSucursal::where('product_id', $negroM->id)->where('sucursal_id', $this->villaBosh->id)->value('cantidad'));
        $this->assertSame(13, (int) $negroM->stock);

        $gorra = Product::where('codigo_interno', 'GOR-1')->sole();
        $this->assertSame('7791234000017', $gorra->codigo_barras, 'Un código de barras numérico en Excel no se rompe');
        $this->assertSame(4, (int) $gorra->stock);

        $this->assertSame(2, AjusteInventario::count(), 'Un ajuste por sucursal para toda la importación');
        $this->assertSame(1, ImportacionProductoLote::count());
    }

    public function test_subir_de_nuevo_el_mismo_archivo_actualiza_sin_duplicar_ni_sumar_stock(): void
    {
        $this->importar($this->catalogo());
        $productos = Product::count();
        $movimientos = MovimientoStock::count();

        $segunda = $this->importar($this->catalogo());

        $this->assertSame(ImportacionProducto::COMPLETADA, $segunda->estado);
        $this->assertSame([0, 3, 0, 0], [$segunda->creados, $segunda->actualizados, $segunda->modelos_nuevos, $segunda->cambios_stock]);
        $this->assertSame($productos, Product::count());
        $this->assertSame($movimientos, MovimientoStock::count(), 'Stock igual: sin movimientos nuevos');
        $this->assertSame(4, (int) Product::where('codigo_interno', 'GOR-1')->value('stock'));
        $this->assertSame(2, AjusteInventario::count(), 'Sin cambios de stock no queda un ajuste vacío');
    }

    public function test_errores_parciales_no_frenan_la_importacion_y_quedan_registrados(): void
    {
        Product::create(['product_type' => ProductType::SIMPLE, 'codigo_interno' => 'OTRO', 'nombre' => 'Otro', 'codigo_barras' => '7790000000001', 'precio' => 1]);

        $importacion = $this->importar($this->excel([
            ['modelo', 'codigo', 'nombre', 'color', 'talle', 'codigo_barras', 'precio', 'stock Central'],
            ['BUZ-1', null, 'Buzo', 'Gris', null, null, 100, null],          // 2: sin talle
            ['BUZ-1', null, 'Buzo', 'Gris', 'S', null, 'caro', null],        // 3: precio inválido
            ['BUZ-1', null, 'Buzo', 'Azul', 'S', null, 100, 5],              // 4: OK
            ['BUZ-1', null, 'Buzo', 'azul', 's', null, 100, 9],              // 5: repetida de la 4
            [null, 'NUEVO-1', 'Nuevo', null, null, '7790000000001', 100, null], // 6: barras de OTRO
            [null, 'OK-1', 'Bien', null, null, null, 250, 7],                // 7: OK
            [null, null, 'Sin código', null, null, null, 100, null],         // 8: simple sin código
        ]));

        $this->assertSame(ImportacionProducto::COMPLETADA_CON_ERRORES, $importacion->estado);
        $this->assertSame([7, 7, 2, 5], [$importacion->total_filas, $importacion->filas_procesadas, $importacion->filas_exitosas, $importacion->filas_con_error]);

        $errores = $importacion->errores()->orderBy('fila')->get()->keyBy('fila');
        $this->assertSame([2, 3, 5, 6, 8], $errores->keys()->all());
        $this->assertStringContainsStringIgnoringCase('necesita color y talle', $errores[2]->mensaje);
        $this->assertStringContainsStringIgnoringCase('precio "caro"', $errores[3]->mensaje);
        $this->assertStringContainsStringIgnoringCase('ya está en la fila 4', $errores[5]->mensaje);
        $this->assertStringContainsStringIgnoringCase('ya es de OTRO', $errores[6]->mensaje);
        $this->assertSame('NUEVO-1', $errores[6]->codigo);
        $this->assertEqualsCanonicalizing(['codigo' => 'NUEVO-1', 'nombre' => 'Nuevo', 'codigo_barras' => '7790000000001', 'precio' => '100'], $errores[6]->datos);

        $azulS = Product::where('color', 'Azul')->where('n_talle', 'S')->sole();
        $this->assertSame(5, (int) $azulS->stock, 'La repetida (9) no pisa a la primera');
        $this->assertNotNull(Product::where('codigo_interno', 'OK-1')->first());
        $this->assertNull(Product::where('codigo_interno', 'NUEVO-1')->first());
        $this->assertSame(1, Product::where('codigo_interno', 'BUZ-1')->count(), 'El modelo se creó una sola vez');
    }

    public function test_varios_lotes_con_un_modelo_repartido_entre_lotes(): void
    {
        $filas = [['modelo', 'codigo', 'nombre', 'color', 'talle', 'precio', 'stock Villa Bosh']];
        for ($i = 1; $i <= 499; $i++) {
            $filas[] = [null, "S-{$i}", "Simple {$i}", null, null, 1000 + $i, 2];
        }
        // Fila 501 (lote 1) y 502 (lote 2): mismo modelo.
        $filas[] = ['MOD-X', null, 'Modelo X', 'Negro', 'M', 5000, 1];
        $filas[] = ['MOD-X', null, 'Modelo X', 'Negro', 'L', 5000, 1];

        $importacion = $this->importar($this->excel($filas));

        $this->assertSame(ImportacionProducto::COMPLETADA, $importacion->estado);
        $this->assertSame(2, $importacion->lotes_total);
        $this->assertSame([501, 501, 501, 0], [$importacion->total_filas, $importacion->filas_procesadas, $importacion->filas_exitosas, $importacion->filas_con_error]);
        $this->assertSame([500, 1], ImportacionProductoLote::orderBy('lote')->pluck('procesadas')->all());
        $this->assertSame(1, Product::where('codigo_interno', 'MOD-X')->count());
        $this->assertSame(2, Product::where('parent_id', Product::where('codigo_interno', 'MOD-X')->value('id'))->count());
        $this->assertSame(1, AjusteInventario::count(), 'Un solo ajuste de Villa Bosh aunque la importación tenga 2 lotes');
        $this->assertSame(501, AjusteInventario::sole()->lineas()->count());
    }

    public function test_un_reintento_de_un_lote_ya_aplicado_no_duplica_nada(): void
    {
        $importacion = $this->importar($this->catalogo());
        $antes = [Product::count(), MovimientoStock::count(), StockSucursal::sum('cantidad'), $importacion->errores()->count()];

        // La cola reentrega el mismo lote (worker muerto después del commit).
        $importacion->update(['estado' => ImportacionProducto::PROCESANDO]);
        ProcesarLoteImportacionProductos::dispatch($importacion->id, 1, 2, 4);

        $this->assertSame($antes, [Product::count(), MovimientoStock::count(), StockSucursal::sum('cantidad'), $importacion->errores()->count()]);
        $this->assertSame([3, 3], [$importacion->fresh()->filas_procesadas, $importacion->fresh()->filas_exitosas], 'Los contadores no se suman dos veces');
        $this->assertSame(1, ImportacionProductoLote::count());
    }

    public function test_un_lote_que_falla_a_mitad_se_deshace_entero_y_el_reintento_lo_aplica_una_vez(): void
    {
        $ruta = 'importaciones/'.Str::uuid().'.xlsx';
        Storage::disk('local')->put($ruta, file_get_contents($this->catalogo()));
        $registro = ImportacionProducto::create(['archivo' => $ruta, 'nombre_original' => 'catalogo.xlsx', 'estado' => ImportacionProducto::PROCESANDO, 'total_filas' => 3, 'lotes_total' => 1]);

        // Primer intento: el lote escribe productos y se corta antes de terminar.
        $this->app->bind(ImportacionProductos::class, fn ($app) => new class($app->make(\App\Services\ProductConfigurableService::class)) extends ImportacionProductos
        {
            public function procesarLote(string $ruta, int $desde, int $hasta, array $saltear, callable $registrarError, ?int $usuarioId, string $referencia): array
            {
                parent::procesarLote($ruta, $desde, $hasta, $saltear, $registrarError, $usuarioId, $referencia);

                throw new \RuntimeException('Se cortó la conexión a mitad del lote');
            }
        });

        try {
            ProcesarLoteImportacionProductos::dispatch($registro->id, 1, 2, 4);
            $this->fail('El lote tenía que fallar');
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, Product::count(), 'La transacción del lote deshizo todo');
        $this->assertSame(0, StockSucursal::count());
        $this->assertSame(0, ImportacionProductoLote::count());
        $this->assertSame(0, $registro->fresh()->filas_procesadas);

        // Reintento normal.
        $this->app->forgetInstance(ImportacionProductos::class);
        $this->app->offsetUnset(ImportacionProductos::class);
        ProcesarLoteImportacionProductos::dispatch($registro->id, 1, 2, 4);

        $this->assertSame(4, Product::count(), 'Modelo + 2 variantes + gorra, una sola vez');
        $this->assertSame([3, 3], [$registro->fresh()->filas_procesadas, $registro->fresh()->filas_exitosas]);
        $this->assertSame(4, (int) Product::where('codigo_interno', 'GOR-1')->value('stock'));
    }

    public function test_pantalla_encola_muestra_progreso_y_pide_permisos(): void
    {
        foreach (['productos.crear', 'stock.ajustar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $sinPermiso = User::factory()->create();
        $this->actingAs($sinPermiso)->get(route('productos.importar'))->assertForbidden();
        $this->get(route('productos.importar.plantilla'))->assertForbidden();

        $soloProductos = User::factory()->create();
        $soloProductos->givePermissionTo('productos.crear');
        $this->actingAs($soloProductos);

        $this->get(route('productos.importar.plantilla'))->assertOk()->assertDownload('plantilla-productos.xlsx');

        $archivo = UploadedFile::fake()->createWithContent('catalogo.xlsx', file_get_contents($this->catalogo()));

        Livewire::test(Importar::class)
            ->set('archivo', $archivo)
            ->assertSee('no tenés permiso para ajustar stock')
            ->call('aplicar');
        $this->assertSame(0, ImportacionProducto::count());
        $this->assertSame(0, Product::count());

        $soloProductos->givePermissionTo('stock.ajustar');

        Livewire::test(Importar::class)
            ->set('archivo', $archivo)
            ->assertSet('errores', [])
            ->assertSet('totalFilas', 3)
            ->assertSee('Remera oversize')
            ->call('aplicar')
            ->assertSet('totalFilas', 0)
            ->assertSee('Completada')
            ->assertSee('100%');

        $importacion = ImportacionProducto::sole();
        $this->assertSame($soloProductos->id, $importacion->user_id);
        $this->assertSame(ImportacionProducto::COMPLETADA, $importacion->estado);
        $this->assertSame(4, Product::count());
    }

    public function test_el_pedido_web_rechaza_encabezados_invalidos_y_archivos_vacios(): void
    {
        $importacion = app(ImportacionProductos::class);

        $sinColumnas = $this->excel([['modelo', 'codigo', 'nombre', 'stock Sucursal Fantasma']]);
        $errores = implode(' ', $importacion->inspeccionar($sinColumnas)['errores']);
        $this->assertStringContainsString('Falta la columna "precio"', $errores);
        $this->assertStringContainsString('Sucursal Fantasma', $errores);

        $vacio = $this->excel([['codigo', 'nombre', 'precio']]);
        $this->assertStringContainsString('vacío', implode(' ', $importacion->inspeccionar($vacio)['errores']));
    }

    public function test_plantilla_trae_una_columna_de_stock_por_sucursal(): void
    {
        $encabezados = app(ImportacionProductos::class)->plantilla()->getSheet(0)->toArray()[0];

        $this->assertSame([...ImportacionProductos::COLUMNAS, 'stock Central', 'stock Villa Bosh'], $encabezados);
    }
}
