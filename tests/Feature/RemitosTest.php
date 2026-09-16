<?php

namespace Tests\Feature;

use App\Enums\EstadoRemito;
use App\Enums\TipoMovimiento;
use App\Exceptions\RemitoException;
use App\Livewire\Sucursales\RemitoNuevo;
use App\Livewire\Sucursales\Remitos;
use App\Livewire\Sucursales\Stock as SucursalesStock;
use App\Models\MovimientoStock;
use App\Models\Product;
use App\Models\Remito;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\RemitoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RemitosTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $central;

    private Sucursal $centro;

    private Sucursal $norte;

    private Product $zapatillas;

    private Product $remera;

    protected function setUp(): void
    {
        parent::setUp();

        $this->central = Sucursal::create(['nombre' => 'Central', 'is_central' => true]);
        $this->centro = Sucursal::create(['nombre' => 'Centro']);
        $this->norte = Sucursal::create(['nombre' => 'Norte']);

        $this->zapatillas = Product::factory()->create(['nombre' => 'Zapatillas', 'codigo_interno' => 'ZAP001']);
        $this->remera = Product::factory()->create(['nombre' => 'Remera', 'codigo_interno' => 'REM001']);

        $this->stock($this->central, $this->zapatillas, 20);
        $this->stock($this->central, $this->remera, 5);
        $this->stock($this->centro, $this->zapatillas, 6);
    }

    private function stock(Sucursal $sucursal, Product $producto, ?int $cantidad = null): int
    {
        if ($cantidad !== null) {
            StockSucursal::updateOrCreate(
                ['sucursal_id' => $sucursal->id, 'product_id' => $producto->id],
                ['cantidad' => $cantidad]
            );
        }

        return (int) StockSucursal::where('sucursal_id', $sucursal->id)->where('product_id', $producto->id)->value('cantidad');
    }

    /** @param array<int, string> $permisos */
    private function usuarioCon(array $permisos): User
    {
        $rol = Role::findOrCreate('rol-'.uniqid(), 'web');
        $rol->givePermissionTo($permisos);

        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    private function servicio(): RemitoService
    {
        return app(RemitoService::class);
    }

    public function test_un_remito_con_varios_articulos_descuenta_del_origen_y_queda_en_transito(): void
    {
        $remito = $this->servicio()->crear($this->central->id, $this->centro->id, [
            $this->zapatillas->id => 8,
            $this->remera->id => 5,
        ]);

        $this->assertSame(EstadoRemito::Remitido, $remito->estado);
        $this->assertCount(2, $remito->detalles);

        $this->assertSame(12, $this->stock($this->central, $this->zapatillas));
        $this->assertSame(0, $this->stock($this->central, $this->remera));
        // El destino no recibe nada hasta confirmar
        $this->assertSame(6, $this->stock($this->centro, $this->zapatillas));

        // Lo en tránsito no está en ninguna sucursal: el total baja
        $this->assertSame(18, $this->zapatillas->fresh()->stock);

        $movimiento = MovimientoStock::where('sucursal_id', $this->central->id)->where('product_id', $this->zapatillas->id)->first();
        $this->assertSame(TipoMovimiento::Transferencia, $movimiento->tipo);
        $this->assertSame(-8, $movimiento->cantidad);
        $this->assertNull($movimiento->punto_de_venta_id);
        $this->assertSame('Remito #'.str_pad((string) $remito->id, 6, '0', STR_PAD_LEFT), $movimiento->referencia);
    }

    public function test_se_puede_remitir_entre_dos_sucursales_y_de_una_sucursal_a_central(): void
    {
        $haciaNorte = $this->servicio()->crear($this->centro->id, $this->norte->id, [$this->zapatillas->id => 4]);
        $this->servicio()->confirmar($haciaNorte);

        $this->assertSame(2, $this->stock($this->centro, $this->zapatillas));
        $this->assertSame(4, $this->stock($this->norte, $this->zapatillas));

        $devolucion = $this->servicio()->crear($this->norte->id, $this->central->id, [$this->zapatillas->id => 1]);
        $this->servicio()->confirmar($devolucion);

        $this->assertSame(3, $this->stock($this->norte, $this->zapatillas));
        $this->assertSame(21, $this->stock($this->central, $this->zapatillas));
        $this->assertSame(26, $this->zapatillas->fresh()->stock);
    }

    public function test_no_se_puede_mandar_mas_de_lo_que_hay_y_no_se_toca_nada(): void
    {
        try {
            $this->servicio()->crear($this->central->id, $this->centro->id, [
                $this->zapatillas->id => 3,
                $this->remera->id => 6,
            ]);
            $this->fail('Tenía que rechazar el remito');
        } catch (RemitoException $e) {
            $this->assertStringContainsString('REM001: pedís 6, hay 5', $e->getMessage());
        }

        // Todo o nada: tampoco se descontaron las zapatillas, que sí alcanzaban
        $this->assertSame(20, $this->stock($this->central, $this->zapatillas));
        $this->assertSame(0, Remito::count());
        $this->assertSame(0, MovimientoStock::count());
    }

    public function test_sin_stock_cargado_en_el_origen_se_rechaza(): void
    {
        $this->expectException(RemitoException::class);

        $this->servicio()->crear($this->norte->id, $this->centro->id, [$this->remera->id => 1]);
    }

    public function test_origen_y_destino_iguales_se_rechaza(): void
    {
        $this->expectException(RemitoException::class);

        $this->servicio()->crear($this->centro->id, $this->centro->id, [$this->zapatillas->id => 1]);
    }

    public function test_sin_articulos_o_con_cantidades_en_cero_se_rechaza(): void
    {
        $this->expectException(RemitoException::class);

        $this->servicio()->crear($this->central->id, $this->centro->id, [$this->zapatillas->id => 0, $this->remera->id => -2]);
    }

    public function test_confirmar_acredita_en_el_destino_una_sola_vez(): void
    {
        $remito = $this->servicio()->crear($this->central->id, $this->centro->id, [$this->zapatillas->id => 8]);

        $this->servicio()->confirmar($remito);

        $this->assertSame(14, $this->stock($this->centro, $this->zapatillas));
        $this->assertSame(26, $this->zapatillas->fresh()->stock);
        $this->assertSame(EstadoRemito::Confirmado, $remito->fresh()->estado);
        $this->assertNotNull($remito->fresh()->confirmado_at);

        // Segundo clic / otro usuario con la pantalla vieja: no se acredita de nuevo
        try {
            $this->servicio()->confirmar($remito);
            $this->fail('No tenía que confirmarse dos veces');
        } catch (RemitoException) {
        }

        $this->assertSame(14, $this->stock($this->centro, $this->zapatillas));
    }

    public function test_confirmar_crea_la_fila_de_stock_si_el_destino_nunca_tuvo_el_articulo(): void
    {
        $remito = $this->servicio()->crear($this->central->id, $this->norte->id, [$this->remera->id => 2]);
        $this->servicio()->confirmar($remito);

        $this->assertSame(2, $this->stock($this->norte, $this->remera));
    }

    public function test_cancelar_devuelve_al_origen_y_recalcula_el_total(): void
    {
        $remito = $this->servicio()->crear($this->central->id, $this->centro->id, [$this->zapatillas->id => 8]);
        $this->assertSame(18, $this->zapatillas->fresh()->stock);

        $this->servicio()->cancelar($remito);

        $this->assertSame(20, $this->stock($this->central, $this->zapatillas));
        $this->assertSame(6, $this->stock($this->centro, $this->zapatillas));
        // Antes quedaba en 18 hasta el próximo movimiento
        $this->assertSame(26, $this->zapatillas->fresh()->stock);
        $this->assertSame(EstadoRemito::Cancelado, $remito->fresh()->estado);
    }

    public function test_no_se_cancela_un_remito_ya_confirmado(): void
    {
        $remito = $this->servicio()->crear($this->central->id, $this->centro->id, [$this->zapatillas->id => 8]);
        $this->servicio()->confirmar($remito);

        $this->expectException(RemitoException::class);

        $this->servicio()->cancelar($remito);
    }

    public function test_la_pantalla_nuevo_remito_crea_un_remito_con_varios_articulos(): void
    {
        $this->actingAs($this->usuarioCon(['remitos.ver', 'remitos.crear']));

        Livewire::test(RemitoNuevo::class)
            // Por defecto sale de una sucursal central (la migración de stock inicial ya crea una)
            ->assertSet('origenId', fn ($id) => Sucursal::find($id)?->isCentral() === true)
            ->set('origenId', $this->central->id)
            ->set('destinoId', $this->norte->id)
            ->set('busqueda', 'ZAP001')
            ->call('agregarUnico')
            ->call('agregar', $this->zapatillas->id)
            ->call('agregar', $this->remera->id)
            ->set('observaciones', 'Bulto 1 de 1')
            ->call('crear')
            ->assertHasNoErrors()
            ->assertRedirect(route('sucursales.remitos', ['sucursal' => $this->central->id, 'direccion' => 'enviados']));

        $remito = Remito::with('detalles')->sole();
        $this->assertSame($this->norte->id, $remito->sucursal_destino_id);
        $this->assertSame('Bulto 1 de 1', $remito->observaciones);
        $this->assertSame([2, 1], $remito->detalles->sortBy('product_id')->pluck('cantidad')->values()->all());
    }

    public function test_la_pantalla_muestra_el_error_de_stock_sin_crear_nada(): void
    {
        $this->actingAs($this->usuarioCon(['remitos.ver', 'remitos.crear']));

        Livewire::test(RemitoNuevo::class)
            ->set('destinoId', $this->centro->id)
            ->set('items', [$this->remera->id => 50])
            ->call('crear')
            ->assertSet('error', fn ($error) => str_contains((string) $error, 'REM001'))
            ->assertNoRedirect();

        $this->assertSame(0, Remito::count());
    }

    public function test_envio_rapido_desde_stock_no_confia_en_el_disponible_del_navegador(): void
    {
        $this->actingAs($this->usuarioCon(['remitos.crear']));

        // Centro tiene 6 zapatillas. Se intenta mandar 10 repartidas en dos destinos.
        Livewire::test(SucursalesStock::class)
            ->set('sucursalSeleccionada', $this->centro->id)
            ->call('abrirRemito', $this->zapatillas->id)
            ->assertSet('remitoStockDisponible', 6)
            ->set('remitoStockDisponible', 999)
            ->set('remitoCantidades', [$this->central->id => 5, $this->norte->id => 5])
            ->call('confirmarRemito')
            ->assertSet('remitoError', fn ($error) => str_contains((string) $error, 'ZAP001'));

        // El primer destino tampoco quedó creado
        $this->assertSame(0, Remito::count());
        $this->assertSame(6, $this->stock($this->centro, $this->zapatillas));
    }

    public function test_envio_rapido_a_varios_destinos_crea_un_remito_por_destino(): void
    {
        $this->actingAs($this->usuarioCon(['remitos.crear']));

        Livewire::test(SucursalesStock::class)
            ->set('sucursalSeleccionada', $this->central->id)
            ->call('abrirRemito', $this->zapatillas->id)
            ->set('remitoCantidades', [$this->centro->id => 3, $this->norte->id => 2])
            ->call('confirmarRemito')
            ->assertSet('remitoError', null);

        $this->assertSame(2, Remito::count());
        $this->assertSame(15, $this->stock($this->central, $this->zapatillas));
    }

    public function test_confirmar_y_cancelar_desde_la_pantalla_de_remitos(): void
    {
        $this->actingAs($this->usuarioCon(['remitos.ver', 'remitos.recibir', 'remitos.cancelar']));

        $aConfirmar = $this->servicio()->crear($this->central->id, $this->centro->id, [$this->zapatillas->id => 2]);
        $aCancelar = $this->servicio()->crear($this->central->id, $this->centro->id, [$this->remera->id => 1]);

        Livewire::test(Remitos::class, ['sucursalSeleccionada' => $this->centro->id])
            ->call('confirmarRecepcion', $aConfirmar->id)
            ->call('cancelarRemito', $aCancelar->id)
            // Ya confirmado: muestra el error, no revienta
            ->call('confirmarRecepcion', $aConfirmar->id);

        $this->assertSame(8, $this->stock($this->centro, $this->zapatillas));
        $this->assertSame(5, $this->stock($this->central, $this->remera));
    }

    public function test_sin_permisos_no_se_crea_ni_se_confirma_ni_se_cancela(): void
    {
        $remito = $this->servicio()->crear($this->central->id, $this->centro->id, [$this->zapatillas->id => 2]);

        $this->actingAs($this->usuarioCon(['remitos.ver']));

        $this->get(route('sucursales.remitos.nuevo'))->assertForbidden();

        Livewire::test(Remitos::class, ['sucursalSeleccionada' => $this->centro->id])
            ->call('confirmarRecepcion', $remito->id)
            ->assertForbidden();

        Livewire::test(Remitos::class, ['sucursalSeleccionada' => $this->centro->id])
            ->call('cancelarRemito', $remito->id)
            ->assertForbidden();

        Livewire::test(SucursalesStock::class)
            ->call('abrirRemito', $this->zapatillas->id)
            ->assertForbidden();

        $this->assertSame(EstadoRemito::Remitido, $remito->fresh()->estado);
        $this->assertSame(6, $this->stock($this->centro, $this->zapatillas));
    }

    public function test_sin_permiso_de_ver_no_se_entra_a_remitos_ni_se_imprime(): void
    {
        $remito = $this->servicio()->crear($this->central->id, $this->centro->id, [$this->zapatillas->id => 2]);

        $this->actingAs(User::factory()->create());

        $this->get(route('sucursales.remitos'))->assertForbidden();
        $this->get(route('remitos.imprimir', $remito->id))->assertForbidden();
    }

    public function test_confirmar_con_cantidad_parcial_deja_el_resto_rechazado(): void
    {
        $remito = $this->servicio()->crear($this->central->id, $this->centro->id, [
            $this->zapatillas->id => 10,
        ]);

        // Centro confirma recibir solo 6 de 10
        $remito = $this->servicio()->confirmar($remito, cantidadesRecibidas: [
            $this->zapatillas->id => 6,
        ]);

        $this->assertSame(EstadoRemito::Confirmado, $remito->estado);

        $detalle = $remito->detalles()->first();
        $this->assertSame(6, $detalle->cantidad_recibida);
        $this->assertSame(4, $detalle->cantidad_rechazada);

        // Centro: 6 (inicial) + 10 (acreditado por remito padre) - 4 (descontado por remito hijo que va al origen) = 12
        $this->assertSame(12, $this->stock($this->centro, $this->zapatillas));

        // Central: 20 (inicial) - 10 (descuento de salida del padre)
        // El remito hijo va a Centro (origen del padre) con config='origen' (default), no a Central
        $this->assertSame(10, $this->stock($this->central, $this->zapatillas));
    }

    public function test_confirmar_parcial_con_config_origen_crea_remito_hijo_hacia_el_origen(): void
    {
        \App\Models\ConfiguracionRemitos::actual()->update(['destino_rechazados' => 'origen']);

        $remito = $this->servicio()->crear($this->central->id, $this->centro->id, [
            $this->zapatillas->id => 10,
        ]);

        $remito = $this->servicio()->confirmar($remito, cantidadesRecibidas: [
            $this->zapatillas->id => 6,
        ]);

        // Debe haber un remito hijo
        $this->assertCount(1, $remito->hijos);
        $hijo = $remito->hijos->first();

        $this->assertSame(EstadoRemito::Remitido, $hijo->estado);
        $this->assertSame($this->centro->id, $hijo->sucursal_origen_id);
        $this->assertSame($this->central->id, $hijo->sucursal_destino_id);
        $this->assertSame($remito->id, $hijo->remito_origen_id);

        $detalleHijo = $hijo->detalles()->first();
        $this->assertSame(4, $detalleHijo->cantidad);
    }

    public function test_confirmar_parcial_con_config_manager_crea_remito_hijo_hacia_la_central(): void
    {
        \App\Models\ConfiguracionRemitos::actual()->update(['destino_rechazados' => 'manager']);

        // Asegurar que Centro tiene suficiente stock para el remito
        $this->stock($this->centro, $this->zapatillas, 15);

        $remito = $this->servicio()->crear($this->centro->id, $this->norte->id, [
            $this->zapatillas->id => 10,
        ]);

        $remito = $this->servicio()->confirmar($remito, cantidadesRecibidas: [
            $this->zapatillas->id => 7,
        ]);

        // Debe haber un hijo, originado en Norte
        $remito = Remito::find($remito->id);
        $hijo = $remito->hijos()->first();
        $this->assertNotNull($hijo);
        $this->assertSame($this->norte->id, $hijo->sucursal_origen_id);
        $this->assertSame(3, $hijo->detalles->first()->cantidad);
    }

    public function test_confirmar_parcial_con_config_elegir_sin_destino_lanza_excepcion(): void
    {
        \App\Models\ConfiguracionRemitos::actual()->update(['destino_rechazados' => 'elegir']);

        $remito = $this->servicio()->crear($this->central->id, $this->centro->id, [
            $this->zapatillas->id => 10,
        ]);

        $this->expectException(RemitoException::class);
        $this->expectExceptionMessage('Elegí a dónde va la mercadería no recibida');

        $this->servicio()->confirmar($remito, cantidadesRecibidas: [
            $this->zapatillas->id => 6,
        ]);
    }

    public function test_confirmar_parcial_con_config_elegir_y_destino_indicado_crea_hijo(): void
    {
        \App\Models\ConfiguracionRemitos::actual()->update(['destino_rechazados' => 'elegir']);

        $remito = $this->servicio()->crear($this->central->id, $this->centro->id, [
            $this->zapatillas->id => 10,
        ]);

        $remito = $this->servicio()->confirmar($remito, cantidadesRecibidas: [
            $this->zapatillas->id => 6,
        ], destinoRechazadosId: $this->norte->id);

        $hijo = $remito->hijos()->first();
        $this->assertSame($this->norte->id, $hijo->sucursal_destino_id);
    }

    public function test_confirmar_parcial_con_config_elegir_y_destino_igual_a_quien_rechaza_lanza_excepcion(): void
    {
        \App\Models\ConfiguracionRemitos::actual()->update(['destino_rechazados' => 'elegir']);

        $remito = $this->servicio()->crear($this->central->id, $this->centro->id, [
            $this->zapatillas->id => 10,
        ]);

        $this->expectException(RemitoException::class);
        $this->expectExceptionMessage('no puede ser la misma sucursal que lo está rechazando');

        $this->servicio()->confirmar($remito, cantidadesRecibidas: [
            $this->zapatillas->id => 6,
        ], destinoRechazadosId: $this->centro->id);
    }

    public function test_confirmar_sin_cantidades_recibidas_sigue_recibiendo_todo_como_antes(): void
    {
        $remito = $this->servicio()->crear($this->central->id, $this->centro->id, [
            $this->zapatillas->id => 10,
        ]);

        // Sin pasar cantidadesRecibidas: debe recibir el 100%
        $remito = $this->servicio()->confirmar($remito);

        $detalle = $remito->detalles()->first();
        $this->assertSame(10, $detalle->cantidad_recibida);
        $this->assertSame(0, $detalle->cantidad_rechazada);

        // No debe haber remito hijo
        $this->assertCount(0, $remito->hijos);
    }

    public function test_crear_remito_desde_una_caja_persiste_quien_lo_creo(): void
    {
        $pdv = \App\Models\PuntoDeVenta::create(['sucursal_id' => $this->central->id, 'nombre' => 'Caja 1', 'secret' => 'test-secret-123']);

        $remito = $this->servicio()->crear($this->central->id, $this->centro->id, [
            $this->zapatillas->id => 5,
        ], caja: $pdv);

        $this->assertSame($pdv->id, $remito->creado_por_punto_de_venta_id);

        // El movimiento de salida también debe tener la caja registrada
        $movimiento = MovimientoStock::where('sucursal_id', $this->central->id)
            ->where('product_id', $this->zapatillas->id)
            ->where('cantidad', -5)
            ->first();

        $this->assertNotNull($movimiento);
        $this->assertSame($pdv->id, $movimiento->punto_de_venta_id);
    }

    public function test_caso_borde_quien_recibe_es_la_central_con_config_manager(): void
    {
        \App\Models\ConfiguracionRemitos::actual()->update(['destino_rechazados' => 'manager']);

        // Asegurar que Centro tiene suficiente stock
        $this->stock($this->centro, $this->zapatillas, 10);

        // Remito directo a Central que se recibe parcialmente en la Central
        $remito = $this->servicio()->crear($this->centro->id, $this->central->id, [
            $this->zapatillas->id => 10,
        ]);

        $remito = $this->servicio()->confirmar($remito, cantidadesRecibidas: [
            $this->zapatillas->id => 6,
        ]);

        // El hijo debe existir y estar dirigido hacia otro lado que no sea Central (quien rechaza)
        $remito = Remito::find($remito->id);
        $hijo = $remito->hijos()->first();
        $this->assertNotNull($hijo);
        $this->assertNotSame($this->central->id, $hijo->sucursal_destino_id);
    }

    public function test_la_migracion_crea_los_permisos_de_remitos(): void
    {
        foreach (['remitos.ver', 'remitos.crear', 'remitos.recibir', 'remitos.cancelar', 'remitos.configurar'] as $permiso) {
            $this->assertTrue(\Spatie\Permission\Models\Permission::where('name', $permiso)->exists(), "Falta {$permiso}");
        }
    }
}
