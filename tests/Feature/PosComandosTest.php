<?php

namespace Tests\Feature;

use App\Enums\ComandoPos as ComandoPosEnum;
use App\Models\ComandoPos;
use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PosComandosTest extends TestCase
{
    use RefreshDatabase;

    private PuntoDeVenta $caja1;

    private PuntoDeVenta $caja2;

    private string $token1;

    protected function setUp(): void
    {
        parent::setUp();

        $sucursal = Sucursal::create(['nombre' => 'Sucursal Centro']);

        $this->caja1 = PuntoDeVenta::create([
            'sucursal_id' => $sucursal->id, 'nombre' => 'Caja 1', 'secret' => Hash::make('x'),
        ]);

        $this->caja2 = PuntoDeVenta::create([
            'sucursal_id' => $sucursal->id, 'nombre' => 'Caja 2', 'secret' => Hash::make('x'),
        ]);

        $this->token1 = $this->caja1->createToken('pos-sync')->plainTextToken;
    }

    private function comoCaja1(string $metodo, string $uri, array $datos = []): TestResponse
    {
        return $this->withToken($this->token1)->json($metodo, $uri, $datos);
    }

    public function test_la_caja_recibe_solo_sus_ordenes_pendientes(): void
    {
        ComandoPos::encolar($this->caja1, ComandoPosEnum::Resincronizar);
        ComandoPos::encolar($this->caja2, ComandoPosEnum::LimpiarCache);

        $r = $this->comoCaja1('GET', '/api/v1/pos/comandos');

        $r->assertOk();
        $this->assertCount(1, $r->json('data'));
        $this->assertSame('resincronizar', $r->json('data.0.comando'));
    }

    public function test_una_orden_entregada_no_vuelve_a_salir(): void
    {
        ComandoPos::encolar($this->caja1, ComandoPosEnum::Resincronizar);

        $this->assertCount(1, $this->comoCaja1('GET', '/api/v1/pos/comandos')->json('data'));
        $this->assertCount(0, $this->comoCaja1('GET', '/api/v1/pos/comandos')->json('data'));
    }

    public function test_encolar_dos_veces_la_misma_orden_no_la_duplica(): void
    {
        ComandoPos::encolar($this->caja1, ComandoPosEnum::Resincronizar);
        ComandoPos::encolar($this->caja1, ComandoPosEnum::Resincronizar);

        $this->assertSame(1, ComandoPos::where('punto_de_venta_id', $this->caja1->id)->count());
    }

    public function test_la_caja_reporta_exito(): void
    {
        $comando = ComandoPos::encolar($this->caja1, ComandoPosEnum::Resincronizar);

        $this->comoCaja1('POST', "/api/v1/pos/comandos/{$comando->id}/resultado", [
            'exito' => true,
            'resultado' => 'Productos: 108',
        ])->assertOk();

        $comando->refresh();
        $this->assertSame(ComandoPos::COMPLETADO, $comando->estado);
        $this->assertSame('Productos: 108', $comando->resultado);
        $this->assertNotNull($comando->finalizado_at);
    }

    public function test_la_caja_reporta_fallo(): void
    {
        $comando = ComandoPos::encolar($this->caja1, ComandoPosEnum::Resincronizar);

        $this->comoCaja1('POST', "/api/v1/pos/comandos/{$comando->id}/resultado", [
            'exito' => false,
            'resultado' => 'Sin conexión',
        ])->assertOk();

        $this->assertSame(ComandoPos::FALLIDO, $comando->refresh()->estado);
    }

    public function test_una_caja_no_puede_cerrar_la_orden_de_otra(): void
    {
        $ajeno = ComandoPos::encolar($this->caja2, ComandoPosEnum::LimpiarCache);

        $this->comoCaja1('POST', "/api/v1/pos/comandos/{$ajeno->id}/resultado", ['exito' => true])
            ->assertStatus(404);

        $this->assertSame(ComandoPos::PENDIENTE, $ajeno->refresh()->estado);
    }

    public function test_el_endpoint_exige_autenticacion(): void
    {
        $this->getJson('/api/v1/pos/comandos')->assertStatus(401);
    }

    public function test_solo_se_aceptan_comandos_de_la_lista_cerrada(): void
    {
        // El enum es la frontera: un valor arbitrario no puede persistirse como comando.
        $this->assertNull(ComandoPosEnum::tryFrom('rm -rf /'));
        $this->assertNull(ComandoPosEnum::tryFrom('shell_exec'));
        $this->assertNotNull(ComandoPosEnum::tryFrom('resincronizar'));
    }
}
