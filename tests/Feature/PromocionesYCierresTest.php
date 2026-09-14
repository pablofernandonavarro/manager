<?php

namespace Tests\Feature;

use App\Livewire\Cajas\Cierres;
use App\Livewire\Promociones\Index as Promociones;
use App\Models\MovimientoCaja;
use App\Models\PromocionBancaria;
use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PromocionesYCierresTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $villaBosh;

    /** @param array<int, string> $permisos */
    private function usuarioCon(array $permisos): User
    {
        $rol = Role::findOrCreate('rol-'.uniqid(), 'web');
        $rol->givePermissionTo($permisos);
        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->villaBosh = Sucursal::create(['nombre' => 'Villa Bosh']);
    }

    // ---- Promociones ----------------------------------------------------------

    public function test_se_crea_una_promocion_completa(): void
    {
        $this->actingAs($this->usuarioCon(['promociones.gestionar']));

        Livewire::test(Promociones::class)
            ->call('crear')
            ->set('nombre', 'Galicia jueves 20%')
            ->set('banco', 'Galicia')
            ->set('medios', ['credito', 'debito'])
            ->set('tarjetas', ['visa'])
            ->set('diasSemana', ['4'])
            ->set('sucursales', [(string) $this->villaBosh->id])
            ->set('porcentaje', '20')
            ->set('tope', '5000')
            ->set('cuotasSinInteres', '6')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('modalAbierto', false);

        $p = PromocionBancaria::sole();
        $this->assertSame(['credito', 'debito'], $p->medios);
        $this->assertSame([4], $p->dias_semana);
        $this->assertSame([$this->villaBosh->id], $p->sucursales);
        $this->assertSame('5000.00', $p->tope);
        $this->assertSame(6, $p->cuotas_sin_interes);
    }

    public function test_listas_vacias_se_guardan_como_todas(): void
    {
        $this->actingAs($this->usuarioCon(['promociones.gestionar']));

        Livewire::test(Promociones::class)
            ->set('nombre', 'QR 10%')->set('medios', ['qr'])->set('porcentaje', '10')
            ->call('guardar')->assertHasNoErrors();

        $p = PromocionBancaria::sole();
        $this->assertNull($p->tarjetas);
        $this->assertNull($p->dias_semana);
        $this->assertNull($p->sucursales);
    }

    public function test_validaciones_de_la_promocion(): void
    {
        $this->actingAs($this->usuarioCon(['promociones.gestionar']));

        Livewire::test(Promociones::class)
            ->set('nombre', '')->set('medios', [])->call('guardar')
            ->assertHasErrors(['nombre', 'medios']);

        Livewire::test(Promociones::class)
            ->set('nombre', 'Sin beneficio')->set('medios', ['credito'])->call('guardar')
            ->assertHasErrors(['porcentaje']);

        Livewire::test(Promociones::class)
            ->set('nombre', 'Fechas al revés')->set('medios', ['credito'])->set('porcentaje', '10')
            ->set('vigenciaDesde', '2026-10-10')->set('vigenciaHasta', '2026-10-01')->call('guardar')
            ->assertHasErrors(['vigenciaHasta']);

        Livewire::test(Promociones::class)
            ->set('nombre', 'Medio inventado')->set('medios', ['bitcoin'])->set('porcentaje', '10')->call('guardar')
            ->assertHasErrors(['medios.0']);

        $this->assertSame(0, PromocionBancaria::count());
    }

    public function test_editar_activar_y_eliminar(): void
    {
        $this->actingAs($this->usuarioCon(['promociones.gestionar']));
        $p = PromocionBancaria::create(['nombre' => 'Vieja', 'medios' => ['credito'], 'porcentaje' => 10, 'activa' => true]);

        Livewire::test(Promociones::class)
            ->call('editar', $p->id)
            ->assertSet('nombre', 'Vieja')
            ->set('nombre', 'Nueva')
            ->call('guardar')
            ->call('alternarActiva', $p->id);

        $this->assertSame('Nueva', $p->fresh()->nombre);
        $this->assertFalse($p->fresh()->activa);
        $this->assertSame(1, PromocionBancaria::count());

        Livewire::test(Promociones::class)->call('eliminar', $p->id);
        $this->assertSame(0, PromocionBancaria::count());
    }

    public function test_sin_permiso_no_se_gestionan_promociones(): void
    {
        $this->actingAs($this->usuarioCon(['cajas.ver']));

        $this->get(route('promociones.index'))->assertForbidden();
        Livewire::test(Promociones::class)->assertForbidden();
    }

    // ---- Cierres --------------------------------------------------------------

    private function turno(PuntoDeVenta $caja, array $datos = []): TurnoCaja
    {
        return TurnoCaja::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'punto_de_venta_id' => $caja->id,
            'sucursal_id' => $caja->sucursal_id,
            'numero' => 1,
            'cajero' => 'Ana',
            'estado' => 'cerrado',
            'fondo_inicial' => 5000,
            'abierto_at' => now()->subHours(8),
            'cerrado_at' => now(),
            'cantidad_ventas' => 4,
            'total_ventas' => 12000,
            'efectivo_esperado' => 9000,
            'efectivo_contado' => 8800,
            'diferencia' => -200,
            'resumen' => [
                'ventas' => ['cantidad' => 4, 'descuentos' => 500, 'ticket_promedio' => 3000],
                'por_medio' => ['efectivo' => 4000, 'credito' => 8000],
                'promociones' => [['nombre' => 'Galicia 20%', 'cantidad' => 1, 'descuento' => 500]],
                'efectivo' => ['ventas' => 4000, 'ingresos' => 0, 'retiros' => 0, 'gastos' => 0, 'esperado' => 9000],
                'numeracion' => ['desde' => 'PDV04-000001', 'hasta' => 'PDV04-000004'],
            ],
        ], $datos));
    }

    public function test_la_pantalla_de_cierres_lista_filtra_y_muestra_el_z(): void
    {
        $this->actingAs($this->usuarioCon(['cajas.ver']));

        $centro = Sucursal::create(['nombre' => 'Centro']);
        $cajaVb = PuntoDeVenta::create(['sucursal_id' => $this->villaBosh->id, 'nombre' => 'caja 2', 'secret' => Hash::make('x')]);
        $cajaCentro = PuntoDeVenta::create(['sucursal_id' => $centro->id, 'nombre' => 'caja centro', 'secret' => Hash::make('x')]);

        $cerrado = $this->turno($cajaVb);
        MovimientoCaja::create(['uuid' => (string) Str::uuid(), 'turno_caja_id' => $cerrado->id, 'tipo' => 'retiro', 'monto' => 1000, 'motivo' => 'Depósito banco', 'fecha' => now()]);
        $this->turno($cajaCentro, ['estado' => 'abierto', 'cerrado_at' => null, 'efectivo_contado' => null, 'diferencia' => null, 'numero' => 7]);

        $this->get(route('cajas.cierres'))->assertOk()->assertSee('Falta')->assertSee('caja centro');

        // Se compara contra las filas y no contra el HTML: el desplegable del filtro "Caja"
        // lista todas las cajas y haría pasar o fallar un assertSee por la razón equivocada.
        $cajasListadas = fn ($componente) => collect($componente->viewData('turnos')->items())->pluck('puntoDeVenta.nombre')->all();

        $this->assertSame(['caja 2'], $cajasListadas(Livewire::test(Cierres::class)->set('sucursal', $this->villaBosh->id)));
        $this->assertSame(['caja centro'], $cajasListadas(Livewire::test(Cierres::class)->set('estado', 'abierto')));

        Livewire::test(Cierres::class)
            ->call('verDetalle', $cerrado->id)
            ->assertSee('Cierre Z')
            ->assertSee('Galicia 20%')
            ->assertSee('Depósito banco')
            ->assertSee('PDV04-000004');
    }

    public function test_sin_permiso_no_se_ven_los_cierres(): void
    {
        $this->actingAs($this->usuarioCon(['promociones.gestionar']));

        $this->get(route('cajas.cierres'))->assertForbidden();
    }

    public function test_la_migracion_crea_los_permisos(): void
    {
        $this->assertTrue(\Spatie\Permission\Models\Permission::where('name', 'promociones.gestionar')->exists());
        $this->assertTrue(\Spatie\Permission\Models\Permission::where('name', 'cajas.ver')->exists());
    }
}
