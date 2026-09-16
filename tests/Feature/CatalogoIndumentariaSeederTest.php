<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\PuntoDeVenta;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use App\Support\Ean13;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\StockSeeder;
use Database\Seeders\SucursalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CatalogoIndumentariaSeederTest extends TestCase
{
    use RefreshDatabase;

    private function stock(string $codigo, string $sucursal): ?int
    {
        return StockSucursal::whereHas('product', fn ($q) => $q->where('codigo_interno', $codigo))
            ->whereHas('sucursal', fn ($q) => $q->where('nombre', $sucursal))
            ->value('cantidad');
    }

    public function test_ean13(): void
    {
        // 7+21+9+3+2+9+4+3 = 58 → verificador 2
        $this->assertSame('7791234100002', Ean13::conVerificador('779123410000'));
        $this->assertTrue(Ean13::valido('7790001000019'));
        $this->assertFalse(Ean13::valido('7790001000018'));
        $this->assertTrue(Ean13::valido(Ean13::conVerificador('779123410203')));
    }

    public function test_remera_basica_es_un_configurable_con_seis_variantes_color_y_talle(): void
    {
        $this->seed(DatabaseSeeder::class);

        $padre = Product::where('codigo_interno', 'CONF-4301')->sole();
        $this->assertSame(ProductType::CONFIGURABLE, $padre->product_type);
        $this->assertFalse($padre->es_vendible, 'el modelo no se vende');
        $this->assertSame('Remera básica algodón', $padre->nombre);

        $variantes = $padre->variants()->orderBy('codigo_interno')->get();

        $this->assertSame(
            ['CONF-4301-BLA-L', 'CONF-4301-BLA-M', 'CONF-4301-BLA-S', 'CONF-4301-NEG-L', 'CONF-4301-NEG-M', 'CONF-4301-NEG-S'],
            $variantes->pluck('codigo_interno')->all()
        );

        $negroM = $variantes->firstWhere('codigo_interno', 'CONF-4301-NEG-M');
        $this->assertSame(['Negro', 'M', true, ProductType::SIMPLE], [$negroM->color, $negroM->n_talle, $negroM->es_vendible, $negroM->product_type]);
        $this->assertSame('Remera básica algodón - Negro - M', $negroM->nombre);
        $this->assertStringContainsString('negro', $negroM->busqueda);

        $barras = Product::whereNotNull('codigo_barras')->pluck('codigo_barras');
        $this->assertTrue($barras->every(fn ($c) => Ean13::valido($c)), 'todos los códigos de barras son EAN-13 válidos');
        $this->assertSame($barras->count(), $barras->unique()->count(), 'no se repiten');
        $this->assertSame(0, Product::whereNotNull('parent_id')->where(fn ($q) => $q->whereNull('color')->orWhereNull('n_talle')->orWhereNull('codigo_barras'))->count());

        $pantalon = Product::where('codigo_interno', 'PANT-001')->sole();
        $this->assertSame([ProductType::SIMPLE, null, true], [$pantalon->product_type, $pantalon->parent_id, $pantalon->es_vendible]);
    }

    public function test_stock_por_variante_y_nunca_sobre_el_padre(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['CONF-4301-NEG-S' => 10, 'CONF-4301-NEG-M' => 15, 'CONF-4301-NEG-L' => 8, 'CONF-4301-BLA-S' => 7, 'CONF-4301-BLA-M' => 12, 'CONF-4301-BLA-L' => 5] as $codigo => $cantidad) {
            $this->assertSame($cantidad, $this->stock($codigo, SucursalesSeeder::LOCAL), $codigo);
            $this->assertSame($cantidad * 2, $this->stock($codigo, SucursalesSeeder::CENTRAL), "{$codigo} en Central");
            $this->assertSame($cantidad * 3, Product::where('codigo_interno', $codigo)->value('stock'), 'products.stock = suma de sucursales');
        }

        $padre = Product::where('codigo_interno', 'CONF-4301')->sole();
        $this->assertSame(0, StockSucursal::where('product_id', $padre->id)->count());
        $this->assertSame(0, $padre->stock);
        $this->assertNotNull($this->stock('PANT-001', SucursalesSeeder::LOCAL));
    }

    public function test_correr_los_seeders_de_nuevo_no_duplica_nada(): void
    {
        $this->seed(DatabaseSeeder::class);
        $antes = [Product::count(), StockSucursal::count(), Sucursal::count(), Product::pluck('codigo_barras', 'codigo_interno')->all()];

        // Los de catálogo y stock (RolesAndPermissionsSeeder no es reproducible y no es parte de esto).
        $this->seed([SucursalesSeeder::class, ProductSeeder::class, StockSeeder::class]);

        $this->assertSame($antes, [Product::count(), StockSucursal::count(), Sucursal::count(), Product::pluck('codigo_barras', 'codigo_interno')->all()]);
    }

    public function test_la_caja_recibe_las_variantes_con_su_modelo_y_no_el_padre(): void
    {
        $this->seed(DatabaseSeeder::class);

        $caja = PuntoDeVenta::create(['sucursal_id' => Sucursal::where('nombre', SucursalesSeeder::LOCAL)->value('id'), 'nombre' => 'caja', 'secret' => Hash::make('x')]);

        $token = $caja->createToken('pos-sync')->plainTextToken;

        // La respuesta es streaming (catálogos grandes): el contenido sale de streamedContent().
        $cuerpo = json_decode($this->withToken($token)->getJson('/api/v1/sync/productos')->assertOk()->streamedContent(), true);
        $datos = collect($cuerpo['data']);
        $this->assertSame($datos->count(), $cuerpo['total']);
        $this->assertNotEmpty($cuerpo['synced_at']);

        $this->assertNull($datos->firstWhere('codigo_interno', 'CONF-4301'), 'el configurable no viaja');

        // Delta: solo lo modificado después de updated_since. synced_at trae 15 minutos de margen
        // (transacciones largas), así que la marca se toma pasado ese margen.
        $this->travel(20)->minutes();
        $marca = json_decode($this->withToken($token)->getJson('/api/v1/sync/productos')->assertOk()->streamedContent(), true)['synced_at'];
        $this->travel(1)->minutes();
        Product::where('codigo_interno', 'PANT-001')->first()->update(['precio' => 31000]);
        $delta = json_decode($this->withToken($token)->getJson('/api/v1/sync/productos?updated_since='.urlencode($marca))->assertOk()->streamedContent(), true);
        $this->assertSame(['PANT-001'], array_column($delta['data'], 'codigo_interno'));
        $this->assertEquals(31000, $delta['data'][0]['precio']);

        $negroM = $datos->firstWhere('codigo_interno', 'CONF-4301-NEG-M');
        $this->assertSame(['Negro', 'M', 'CONF-4301', 'Remera básica algodón', 15], [$negroM['color'], $negroM['n_talle'], $negroM['parent_codigo_interno'], $negroM['parent_nombre'], $negroM['stock']]);
        $this->assertNull($datos->firstWhere('codigo_interno', 'PANT-001')['parent_codigo_interno']);
    }
}
