<?php

namespace Tests\Feature;

use App\Livewire\Clientes\CuentaCorriente;
use App\Livewire\Clientes\Index;
use App\Models\Cliente;
use App\Models\MovimientoCuentaCorriente;
use App\Models\Product;
use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientesCuentaCorrienteTest extends TestCase
{
    use RefreshDatabase;

    private PuntoDeVenta $caja;

    private Product $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $sucursal = Sucursal::create(['nombre' => 'Villa Bosh']);
        $this->caja = PuntoDeVenta::create(['sucursal_id' => $sucursal->id, 'nombre' => 'caja 2', 'secret' => Hash::make('x')]);
        $this->producto = Product::factory()->create();
    }

    private function como(string $metodo, string $uri, array $datos = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->caja->createToken('pos-sync')->plainTextToken)->json($metodo, $uri, $datos);
    }

    private function usuario(array $permisos): User
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(tap(Role::findOrCreate('rol-'.uniqid(), 'web'))->givePermissionTo($permisos));

        return $usuario;
    }

    private function cliente(array $extra = []): Cliente
    {
        return Cliente::create(array_merge([
            'nombre' => 'Kiosco Don José', 'doc_tipo' => 80, 'documento' => '30712345671', 'condicion_iva' => 6,
            'cuenta_corriente' => true, 'limite_credito' => 50000,
        ], $extra));
    }

    private function ventaACuenta(Cliente $cliente, float $total, float $aCuenta): array
    {
        $pagos = [['medio' => 'cuenta_corriente', 'monto' => $aCuenta, 'importe' => $aCuenta]];

        if ($total > $aCuenta) {
            $pagos[] = ['medio' => 'efectivo', 'monto' => $total - $aCuenta, 'importe' => $total - $aCuenta];
        }

        return [
            'uuid' => (string) Str::uuid(), 'numero_venta' => 'PDV04-000077', 'fecha' => now()->toIso8601String(),
            'subtotal' => $total, 'total' => $total, 'cliente_id' => $cliente->id, 'cliente_nombre' => $cliente->nombre,
            'metodo_pago' => 'cuenta_corriente',
            'items' => [['product_id' => $this->producto->id, 'cantidad' => 1, 'precio_unitario' => $total, 'subtotal' => $total]],
            'pagos' => $pagos,
        ];
    }

    // ---- API de las cajas ------------------------------------------------------------

    public function test_la_caja_recibe_clientes_activos_con_saldo(): void
    {
        $conDeuda = $this->cliente();
        MovimientoCuentaCorriente::create(['cliente_id' => $conDeuda->id, 'tipo' => 'ajuste', 'importe' => 1500, 'descripcion' => 'Saldo inicial', 'fecha' => now()]);
        $this->cliente(['nombre' => 'Inactivo', 'documento' => '20123456786', 'activo' => false]);

        $r = $this->como('GET', '/api/v1/sync/clientes')->assertOk();

        $this->assertCount(1, $r->json('data'));
        $r->assertJsonPath('data.0.saldo', 1500)
            ->assertJsonPath('data.0.limite_credito', 50000)
            ->assertJsonPath('data.0.cuenta_corriente', true)
            ->assertJsonPath('data.0.condicion_iva', 6);
    }

    public function test_venta_a_cuenta_suma_deuda_una_sola_vez_y_la_devolucion_la_baja(): void
    {
        $cliente = $this->cliente();
        $venta = $this->ventaACuenta($cliente, 10000, 6000);

        $this->como('POST', '/api/v1/sync/ventas', ['ventas' => [$venta]])->assertOk();
        $this->como('POST', '/api/v1/sync/ventas', ['ventas' => [$venta]])->assertOk();

        $this->assertSame(6000.0, $cliente->saldo(), 'solo la parte a cuenta, y el reenvío no duplica');
        $this->assertSame($cliente->id, Venta::sole()->cliente_id);

        $devolucion = [
            'uuid' => (string) Str::uuid(), 'venta_uuid' => $venta['uuid'], 'numero' => 'DEV04-000001', 'tipo' => 'parcial',
            'motivo' => 'Falla', 'reintegro' => 'medio_original', 'total' => 8000, 'autorizado_por' => 'Sup',
            'fecha' => now()->toIso8601String(), 'items' => [['product_id' => $this->producto->id, 'cantidad' => 1, 'importe' => 8000]],
        ];
        $this->como('POST', '/api/v1/sync/devoluciones', ['devoluciones' => [$devolucion]])->assertOk();

        $this->assertSame(0.0, $cliente->saldo(), 'se acredita como mucho lo que se cargó a cuenta');

        // Reintegro en efectivo: no toca la cuenta.
        $otra = $this->ventaACuenta($cliente, 1000, 1000);
        $this->como('POST', '/api/v1/sync/ventas', ['ventas' => [$otra]]);
        $this->como('POST', '/api/v1/sync/devoluciones', ['devoluciones' => [[...$devolucion, 'uuid' => (string) Str::uuid(), 'venta_uuid' => $otra['uuid'], 'reintegro' => 'efectivo', 'total' => 1000]]]);
        $this->assertSame(1000.0, $cliente->saldo());
    }

    public function test_venta_con_cliente_borrado_entra_igual(): void
    {
        $cliente = $this->cliente();
        $venta = $this->ventaACuenta($cliente, 500, 500);
        $cliente->delete();

        $this->como('POST', '/api/v1/sync/ventas', ['ventas' => [$venta]])->assertOk()->assertJsonPath('resultados.0.status', 'creada');

        $this->assertNull(Venta::sole()->cliente_id);
        $this->assertSame(0, MovimientoCuentaCorriente::count());
    }

    public function test_cobros_de_la_caja_bajan_la_deuda_y_son_idempotentes(): void
    {
        $cliente = $this->cliente();
        $this->como('POST', '/api/v1/sync/ventas', ['ventas' => [$this->ventaACuenta($cliente, 5000, 5000)]]);

        $cobro = ['uuid' => (string) Str::uuid(), 'cliente_id' => $cliente->id, 'importe' => 3000, 'medio' => 'transferencia', 'fecha' => now()->toIso8601String(), 'cajero' => 'Ana', 'numero' => 'COB04-000001'];

        $this->como('POST', '/api/v1/sync/cobros-cuenta-corriente', ['cobros' => [$cobro]])->assertOk()->assertJsonPath('resultados.0.status', 'creado');
        $this->como('POST', '/api/v1/sync/cobros-cuenta-corriente', ['cobros' => [$cobro]])->assertOk()->assertJsonPath('resultados.0.status', 'duplicado');

        $this->assertSame(2000.0, $cliente->saldo());
        $pago = MovimientoCuentaCorriente::where('tipo', 'pago')->sole();
        $this->assertSame('transferencia', $pago->medio);
        $this->assertStringContainsString('COB04-000001', $pago->descripcion);

        $this->como('POST', '/api/v1/sync/cobros-cuenta-corriente', ['cobros' => [[...$cobro, 'uuid' => (string) Str::uuid(), 'medio' => 'cuenta_corriente']]])->assertUnprocessable();

        // Un cliente que ya no existe rechaza ese cobro, no la tanda.
        $bueno = [...$cobro, 'uuid' => (string) Str::uuid(), 'importe' => 500];
        $this->como('POST', '/api/v1/sync/cobros-cuenta-corriente', ['cobros' => [[...$cobro, 'uuid' => (string) Str::uuid(), 'cliente_id' => 999], $bueno]])
            ->assertOk()
            ->assertJsonPath('resultados.0.status', 'cliente_inexistente')
            ->assertJsonPath('resultados.1.status', 'creado');
        $this->assertSame(1500.0, $cliente->saldo());
    }

    // ---- Pantallas --------------------------------------------------------------------

    public function test_alta_de_cliente_valida_documento_y_no_repite(): void
    {
        $this->actingAs($this->usuario(['clientes.gestionar']));

        Livewire::test(Index::class)
            ->call('crear')
            ->set('nombre', 'Cliente SRL')->set('condicionIva', '1')->set('documento', '12345678')
            ->call('guardar')->assertHasErrors(['documento']);

        Livewire::test(Index::class)
            ->call('crear')
            ->set('nombre', 'Cliente SRL')->set('condicionIva', '1')->set('documento', '30-71234567-1')
            ->set('cuentaCorriente', true)->set('limiteCredito', '20000')
            ->call('guardar')->assertHasNoErrors();

        $cliente = Cliente::sole();
        $this->assertSame([80, '30712345671', 1, true, '20000.00'], [$cliente->doc_tipo, $cliente->documento, $cliente->condicion_iva, $cliente->cuenta_corriente, $cliente->limite_credito]);

        Livewire::test(Index::class)
            ->call('crear')
            ->set('nombre', 'Otro')->set('documento', '30712345671')
            ->call('guardar')->assertHasErrors(['documento']);

        Livewire::test(Index::class)->set('buscar', '3071234')->assertSee('Cliente SRL');
    }

    public function test_filtro_de_clientes_con_deuda(): void
    {
        $deudor = $this->cliente(['nombre' => 'Deudor']);
        MovimientoCuentaCorriente::create(['cliente_id' => $deudor->id, 'tipo' => 'ajuste', 'importe' => 700, 'descripcion' => 'x', 'fecha' => now()]);
        $alDia = $this->cliente(['nombre' => 'Al día', 'documento' => '20123456786']);
        MovimientoCuentaCorriente::create(['cliente_id' => $alDia->id, 'tipo' => 'ajuste', 'importe' => 0.01, 'descripcion' => 'x', 'fecha' => now()]);
        MovimientoCuentaCorriente::create(['cliente_id' => $alDia->id, 'tipo' => 'pago', 'importe' => -0.01, 'descripcion' => 'x', 'fecha' => now()]);

        $this->actingAs($this->usuario(['clientes.gestionar']));

        Livewire::test(Index::class)
            ->set('soloConDeuda', true)
            ->assertSee('Deudor')->assertDontSee('Al día')
            ->assertSee('$700,00');
    }

    public function test_estado_de_cuenta_con_saldo_acumulado_y_pagos_solo_con_permiso(): void
    {
        $cliente = $this->cliente();
        MovimientoCuentaCorriente::create(['cliente_id' => $cliente->id, 'tipo' => 'venta', 'importe' => 4000, 'descripcion' => 'Venta 1', 'fecha' => now()->subDays(2)]);
        MovimientoCuentaCorriente::create(['cliente_id' => $cliente->id, 'tipo' => 'venta', 'importe' => 1000, 'descripcion' => 'Venta 2', 'fecha' => now()->subDay()]);

        $this->actingAs($this->usuario(['clientes.gestionar']));
        Livewire::test(CuentaCorriente::class, ['cliente' => $cliente])
            ->assertSee('$5.000,00')->assertSee('$4.000,00')
            ->assertDontSee('Registrar pago o ajuste')
            ->set('importe', '100')->set('descripcion', 'x')->call('registrar')->assertForbidden();

        $this->actingAs($this->usuario(['clientes.gestionar', 'clientes.cuenta_corriente']));
        Livewire::test(CuentaCorriente::class, ['cliente' => $cliente])
            ->set('tipo', 'pago')->set('importe', '1500')->set('medio', 'efectivo')->set('descripcion', 'Pagó en el local')
            ->call('registrar')->assertSet('mensaje', 'Pago registrado.')
            ->set('tipo', 'ajuste')->set('importe', '-500')->set('descripcion', 'Bonificación')
            ->call('registrar')->assertSet('mensaje', 'Ajuste registrado.')
            ->set('tipo', 'pago')->set('importe', '0')->call('registrar')->assertSet('error', 'Indicá un importe válido.');

        $this->assertSame(3000.0, $cliente->saldo());

        $this->actingAs(User::factory()->create());
        $this->get(route('clientes.index'))->assertForbidden();
    }
}
