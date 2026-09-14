<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Jobs\ImportarProductos;
use App\Livewire\Products\Importar;
use App\Models\AjusteInventario;
use App\Models\ImportacionProducto;
use App\Models\Product;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ImportacionProductos;
use App\Support\Ean13;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

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

    private function importacion(): ImportacionProductos
    {
        return app(ImportacionProductos::class);
    }

    public function test_crea_modelo_variantes_y_simple_con_stock_por_sucursal(): void
    {
        $resultado = $this->importacion()->aplicar($this->catalogo(), null, 'catalogo.xlsx');

        $this->assertSame(['creados' => 3, 'actualizados' => 0, 'modelos' => 1, 'stock' => 3], $resultado);

        $modelo = Product::where('codigo_interno', 'REM-9')->sole();
        $this->assertSame(ProductType::CONFIGURABLE, $modelo->product_type);
        $this->assertFalse((bool) $modelo->es_vendible);

        $negroM = Product::where('parent_id', $modelo->id)->where('n_talle', 'M')->sole();
        $this->assertSame('REM-9-NEG-M', $negroM->codigo_interno);
        $this->assertSame('Negro', $negroM->color);
        $this->assertEquals(12900.5, $negroM->precio);
        $this->assertTrue(Ean13::valido($negroM->codigo_barras));
        $this->assertTrue((bool) $negroM->es_vendible);
        $this->assertSame(10, (int) StockSucursal::where('product_id', $negroM->id)->where('sucursal_id', $this->central->id)->value('cantidad'));
        $this->assertSame(3, (int) StockSucursal::where('product_id', $negroM->id)->where('sucursal_id', $this->villaBosh->id)->value('cantidad'));
        $this->assertSame(13, (int) $negroM->stock);

        $gorra = Product::where('codigo_interno', 'GOR-1')->sole();
        $this->assertSame('7791234000017', $gorra->codigo_barras, 'Un código de barras numérico en Excel no se rompe');
        $this->assertEquals(21, $gorra->iva);
        $this->assertSame(4, (int) $gorra->stock);

        $this->assertSame(2, AjusteInventario::count(), 'Un ajuste por sucursal, en el historial');
    }

    public function test_subir_de_nuevo_el_mismo_archivo_actualiza_sin_duplicar_ni_sumar_stock(): void
    {
        $this->importacion()->aplicar($this->catalogo(), null, 'catalogo.xlsx');
        $productos = Product::count();

        $resultado = $this->importacion()->aplicar($this->catalogo(), null, 'catalogo.xlsx');

        $this->assertSame(['creados' => 0, 'actualizados' => 3, 'modelos' => 0, 'stock' => 0], $resultado);
        $this->assertSame($productos, Product::count());
        $this->assertSame(4, (int) Product::where('codigo_interno', 'GOR-1')->value('stock'));

        $this->importacion()->aplicar($this->excel([
            ['codigo', 'nombre', 'precio', 'stock Villa Bosh'],
            ['GOR-1', null, 10500, 7],
        ]), null, 'precios.xlsx');

        $gorra = Product::where('codigo_interno', 'GOR-1')->sole();
        $this->assertSame('Gorra trucker', $gorra->nombre, 'Celda vacía no borra el nombre');
        $this->assertEquals(10500, $gorra->precio);
        $this->assertSame(7, (int) $gorra->stock);
    }

    public function test_errores_por_fila_y_no_se_escribe_nada(): void
    {
        Product::create(['product_type' => ProductType::SIMPLE, 'codigo_interno' => 'OTRO', 'nombre' => 'Otro', 'codigo_barras' => '7790000000001', 'precio' => 1]);

        $ruta = $this->excel([
            ['modelo', 'codigo', 'nombre', 'color', 'talle', 'codigo_barras', 'precio', 'stock Sucursal Fantasma'],
        ]);
        $this->assertStringContainsString('Sucursal Fantasma', implode(' ', $this->importacion()->analizar($ruta)['errores']));

        $ruta = $this->excel([
            ['modelo', 'codigo', 'nombre', 'color', 'talle', 'codigo_barras', 'precio'],
            ['BUZ-1', null, 'Buzo', 'Gris', null, null, 100],
            ['BUZ-1', null, 'Buzo', 'Gris', 'S', null, 'caro'],
            ['BUZ-1', null, 'Buzo', 'Azul', 'S', null, 100],
            ['BUZ-1', null, 'Buzo', 'azul', 's', null, 100],
            [null, 'NUEVO-1', 'Nuevo', null, null, '7790000000001', 100],
            [null, 'OTRO', null, null, null, null, 'abc'],
            [null, null, 'Sin código', null, null, null, 100],
        ]);

        $errores = implode("\n", $this->importacion()->analizar($ruta)['errores']);

        $this->assertStringContainsString('Fila 2: la variante del modelo BUZ-1 necesita color y talle', $errores);
        $this->assertStringContainsString('Fila 3: precio "caro"', $errores);
        $this->assertStringContainsString('Fila 5: BUZ-1 azul / s ya está en la fila 4', $errores);
        $this->assertStringContainsString('Fila 6: el código de barras 7790000000001 ya es de OTRO', $errores);
        $this->assertStringContainsString('Fila 7: precio "abc"', $errores);
        $this->assertStringContainsString('Fila 8: un producto simple necesita codigo', $errores);

        $this->expectException(\RuntimeException::class);
        try {
            $this->importacion()->aplicar($ruta, null, 'errores.xlsx');
        } finally {
            $this->assertSame(1, Product::count());
        }
    }

    public function test_pantalla_previsualiza_y_aplica_y_pide_permisos(): void
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
            ->assertSet('totalFilas', 3)
            ->assertSee('no tenés permiso para ajustar stock')
            ->call('aplicar');
        $this->assertSame(0, Product::count());
        $this->assertSame(0, ImportacionProducto::count());

        $soloProductos->givePermissionTo('stock.ajustar');

        // En los tests la cola es sync: el job corre al encolar.
        Livewire::test(Importar::class)
            ->set('archivo', $archivo)
            ->assertSet('errores', [])
            ->assertSee('Remera oversize')
            ->call('aplicar')
            ->assertSet('totalFilas', 0)
            ->assertSee('Terminada')
            ->assertSee('3 creados');

        $this->assertSame(4, Product::count());
        $importacion = ImportacionProducto::sole();
        $this->assertSame(ImportacionProducto::TERMINADA, $importacion->estado);
        $this->assertSame(3, $importacion->filas);
        $this->assertSame(3, $importacion->resultado['creados']);
        $this->assertSame($soloProductos->id, $importacion->user_id);
        Storage::disk('local')->assertMissing($importacion->archivo);
    }

    public function test_job_que_encuentra_errores_al_correr_queda_fallida_sin_guardar_nada(): void
    {
        // El catálogo cambió entre la vista previa y la corrida: el código de barras ya es de otro.
        Storage::disk('local')->put('importaciones/prueba.xlsx', file_get_contents($this->excel([
            ['codigo', 'nombre', 'codigo_barras', 'precio'],
            ['NUEVO-1', 'Nuevo', '7790000000001', 100],
        ])));
        Product::create(['product_type' => ProductType::SIMPLE, 'codigo_interno' => 'OTRO', 'nombre' => 'Otro', 'codigo_barras' => '7790000000001', 'precio' => 1]);
        $registro = ImportacionProducto::create(['archivo' => 'importaciones/prueba.xlsx', 'nombre_original' => 'prueba.xlsx', 'filas' => 1]);

        ImportarProductos::dispatchSync($registro->id);

        $registro->refresh();
        $this->assertSame(ImportacionProducto::FALLIDA, $registro->estado);
        $this->assertStringContainsString('errores', $registro->error);
        $this->assertNull(Product::where('codigo_interno', 'NUEVO-1')->first());
        Storage::disk('local')->assertMissing('importaciones/prueba.xlsx');

        // Una segunda corrida del mismo job (reintento de la cola) no hace nada.
        ImportarProductos::dispatchSync($registro->id);
        $this->assertSame(ImportacionProducto::FALLIDA, $registro->fresh()->estado);
    }

    public function test_plantilla_trae_una_columna_de_stock_por_sucursal(): void
    {
        $encabezados = $this->importacion()->plantilla()->getSheet(0)->toArray()[0];

        $this->assertSame([...ImportacionProductos::COLUMNAS, 'stock Central', 'stock Villa Bosh'], $encabezados);
    }
}
