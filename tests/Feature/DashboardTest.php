<?php

namespace Tests\Feature;

use App\Enums\EstadoRemito;
use App\Livewire\Dashboard;
use App\Models\Product;
use App\Models\PuntoDeVenta;
use App\Models\Remito;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Models\Venta;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private const ZONA = 'America/Argentina/Buenos_Aires';

    private Sucursal $villaBosh;

    private Sucursal $centro;

    private PuntoDeVenta $caja;

    private Product $remera;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.display_timezone' => self::ZONA]);
        // En UTC: con la zona de Argentina, Carbon lee las fechas de la base en esa zona.
        Carbon::setTestNow(Carbon::parse('2026-09-18 16:00', self::ZONA)->utc());

        $this->villaBosh = Sucursal::create(['nombre' => 'Villa Bosh']);
        $this->centro = Sucursal::create(['nombre' => 'Centro']);
        $this->caja = PuntoDeVenta::create(['sucursal_id' => $this->villaBosh->id, 'nombre' => 'Caja 2', 'secret' => Hash::make('x')]);
        $this->remera = Product::factory()->create(['nombre' => 'Remera - Negro - M', 'codigo_interno' => 'REM-NEG-M', 'es_vendible' => true, 'stock_critico' => 5]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function venta(string $local, float $total, int $unidades, ?PuntoDeVenta $caja = null): void
    {
        $caja ??= $this->caja;
        $venta = Venta::create([
            'uuid' => (string) Str::uuid(), 'punto_de_venta_id' => $caja->id, 'sucursal_id' => $caja->sucursal_id,
            'cajero' => 'Ana', 'numero_venta' => 'V-'.Str::random(5), 'fecha' => Carbon::parse($local, self::ZONA)->utc(),
            'subtotal' => $total, 'descuento' => 0, 'descuento_manual' => 0, 'total' => $total,
        ]);
        $venta->items()->create(['product_id' => $this->remera->id, 'cantidad' => $unidades, 'precio_unitario' => $total / $unidades, 'subtotal' => $total]);
        $venta->pagos()->create(['medio' => 'efectivo', 'monto' => $total, 'importe' => $total, 'descuento' => 0]);
    }

    public function test_ventas_de_hoy_contra_ayer_y_del_mes(): void
    {
        $this->venta('2026-09-18 10:00', 3000, 2);
        $this->venta('2026-09-18 23:30', 1000, 1); // noche local: sigue siendo hoy
        $this->venta('2026-09-17 12:00', 2000, 1);
        $this->venta('2026-08-10 12:00', 8000, 4); // mes anterior, dentro de los mismos días
        $this->venta('2026-08-25 12:00', 9999, 1); // mes anterior, después del día 18: no compara

        $datos = app(DashboardService::class)->ventas(null, Carbon::now(self::ZONA)->startOfDay());

        $this->assertEquals(4000, $datos['hoy']['neto']);
        $this->assertSame(2, $datos['hoy']['cantidad']);
        $this->assertSame(3, $datos['hoy']['unidades']);
        $this->assertEquals(2000, $datos['ayer']['neto']);
        $this->assertEquals(6000, $datos['mes']['neto']);
        $this->assertEquals(8000, $datos['mes_anterior']['neto']);

        $evolucion = app(DashboardService::class)->evolucion(null, Carbon::now(self::ZONA)->startOfDay());
        $this->assertCount(14, $evolucion);
        $this->assertSame('2026-09-18', end($evolucion)['dia']);
        $this->assertEquals(4000, end($evolucion)['total']);
        $this->assertEquals(0, $evolucion[0]['total'], 'Los días sin ventas aparecen en 0');
    }

    public function test_filtra_por_sucursal_y_arma_alertas(): void
    {
        $cajaCentro = PuntoDeVenta::create(['sucursal_id' => $this->centro->id, 'nombre' => 'Caja centro', 'secret' => Hash::make('x')]);
        $this->venta('2026-09-18 10:00', 3000, 2);
        $this->venta('2026-09-18 11:00', 500, 1, $cajaCentro);

        StockSucursal::create(['sucursal_id' => $this->villaBosh->id, 'product_id' => $this->remera->id, 'cantidad' => 2]);
        StockSucursal::create(['sucursal_id' => $this->centro->id, 'product_id' => $this->remera->id, 'cantidad' => 40]);
        Remito::create(['sucursal_origen_id' => $this->centro->id, 'sucursal_destino_id' => $this->villaBosh->id, 'estado' => EstadoRemito::Remitido, 'remitido_at' => now()]);

        $tablero = app(DashboardService::class);
        $hoy = Carbon::now(self::ZONA)->startOfDay();

        $this->assertEquals(3000, $tablero->ventas($this->villaBosh->id, $hoy)['hoy']['neto']);
        $this->assertSame('Remera - Negro - M', $tablero->masVendidos($this->villaBosh->id, $hoy)[0]['nombre']);
        $critico = $tablero->stockCritico(null);
        $this->assertSame(1, $critico['cantidad'], 'Solo Villa Bosh está bajo el mínimo');
        $this->assertSame('Villa Bosh', $critico['items'][0]['sucursal']);
        $this->assertSame(0, $tablero->stockCritico($this->centro->id)['cantidad']);
        $this->assertSame(1, $tablero->remitosEnTransito($this->villaBosh->id)['cantidad']);

        // Caja instalada, sin conexión hace una hora y con el turno abierto.
        $this->caja->codigosInstalacion()->create(['codigo' => 'ABCD-1234', 'expira_at' => now()->addDay(), 'usado_at' => now()->subDay()]);
        $this->caja->forceFill(['ultima_conexion_at' => now()->subHour()])->save();
        TurnoCaja::create(['uuid' => (string) Str::uuid(), 'punto_de_venta_id' => $this->caja->id, 'sucursal_id' => $this->villaBosh->id, 'numero' => 1, 'cajero' => 'Ana', 'estado' => 'abierto', 'abierto_at' => now()->subHours(3), 'fondo_inicial' => 0]);

        $permisos = ['reportes.ver', 'productos.ver', 'remitos.ver', 'terminales.ver', 'facturacion.ver', 'clientes.gestionar'];
        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $admin = User::factory()->create();
        $admin->givePermissionTo($permisos);
        $this->actingAs($admin);

        $this->get('/dashboard')->assertOk()
            ->assertSee('Ventas de hoy')
            ->assertSee('$ 3.500')
            ->assertSee('1 artículo con stock crítico')
            ->assertSee('1 remito en tránsito')
            ->assertSee('1 caja con problemas')
            ->assertSee('Sin conexión hace')
            ->assertSee('Esperando CAE')
            ->assertSee('Cuentas corrientes');

        Livewire::test(Dashboard::class)
            ->set('sucursalId', $this->centro->id)
            ->assertSee('$ 500')
            ->assertDontSee('con stock crítico');
    }

    public function test_una_venta_nueva_aparece_en_el_tablero_al_refrescar_sin_recargar_la_pagina(): void
    {
        Permission::findOrCreate('reportes.ver', 'web');
        $usuario = User::factory()->create();
        $usuario->givePermissionTo('reportes.ver');
        $this->actingAs($usuario);

        $tablero = Livewire::test(Dashboard::class);
        $this->assertSame(0, $tablero->viewData('ventas')['hoy']['cantidad']);

        $this->venta('2026-09-18 15:00', 3000, 2);
        $tablero->call('$refresh');

        $this->assertSame(1, $tablero->viewData('ventas')['hoy']['cantidad']);
    }

    public function test_el_tablero_declara_el_poll_y_el_refresco_al_volver_a_la_pestana(): void
    {
        $this->actingAs(User::factory()->create());

        // Solo verifica que el HTML lo declara: Livewire::test no ejecuta JavaScript.
        Livewire::test(Dashboard::class)
            ->assertSeeHtml('wire:poll.60s')
            ->assertSeeHtml('visibilitychange.document');
    }

    public function test_sin_permisos_no_muestra_numeros(): void
    {
        $this->venta('2026-09-18 10:00', 3000, 2);
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertOk()
            ->assertDontSee('Ventas de hoy')
            ->assertDontSee('$ 3.000')
            ->assertSee('no tiene acceso a indicadores');
    }
}
