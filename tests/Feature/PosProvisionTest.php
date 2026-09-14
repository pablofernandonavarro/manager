<?php

namespace Tests\Feature;

use App\Models\CodigoInstalacion;
use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PosProvisionTest extends TestCase
{
    use RefreshDatabase;

    private PuntoDeVenta $pdv;

    protected function setUp(): void
    {
        parent::setUp();

        $sucursal = Sucursal::create(['nombre' => 'Sucursal Centro']);

        $this->pdv = PuntoDeVenta::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Caja 1',
            'secret' => Hash::make('secret-viejo'),
        ]);
    }

    public function test_canjear_un_codigo_devuelve_las_credenciales(): void
    {
        $codigo = CodigoInstalacion::generarPara($this->pdv);

        $r = $this->postJson('/api/v1/pos/provision', ['codigo' => $codigo->codigo]);

        $r->assertOk()
            ->assertJsonStructure(['punto_de_venta_id', 'secret', 'pdv_nombre', 'sucursal_id', 'sucursal_nombre']);

        $this->assertSame($this->pdv->id, $r->json('punto_de_venta_id'));
        $this->assertSame('Caja 1', $r->json('pdv_nombre'));
        $this->assertSame('Sucursal Centro', $r->json('sucursal_nombre'));
    }

    public function test_el_secret_entregado_sirve_para_autenticarse(): void
    {
        $codigo = CodigoInstalacion::generarPara($this->pdv);

        $secret = $this->postJson('/api/v1/pos/provision', ['codigo' => $codigo->codigo])->json('secret');

        $this->postJson('/api/v1/pos/auth', [
            'punto_de_venta_id' => $this->pdv->id,
            'secret' => $secret,
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_canjear_rota_el_secret_anterior(): void
    {
        $codigo = CodigoInstalacion::generarPara($this->pdv);

        $this->postJson('/api/v1/pos/provision', ['codigo' => $codigo->codigo])->assertOk();

        // El secret con el que estaba instalada la máquina vieja ya no sirve.
        $this->postJson('/api/v1/pos/auth', [
            'punto_de_venta_id' => $this->pdv->id,
            'secret' => 'secret-viejo',
        ])->assertStatus(401);
    }

    public function test_canjear_revoca_los_tokens_existentes(): void
    {
        $this->pdv->createToken('pos-sync');
        $this->assertSame(1, $this->pdv->tokens()->count());

        $codigo = CodigoInstalacion::generarPara($this->pdv);
        $this->postJson('/api/v1/pos/provision', ['codigo' => $codigo->codigo])->assertOk();

        $this->assertSame(0, $this->pdv->fresh()->tokens()->count());
    }

    public function test_un_codigo_no_se_puede_usar_dos_veces(): void
    {
        $codigo = CodigoInstalacion::generarPara($this->pdv);

        $this->postJson('/api/v1/pos/provision', ['codigo' => $codigo->codigo])->assertOk();
        $this->postJson('/api/v1/pos/provision', ['codigo' => $codigo->codigo])->assertStatus(409);
    }

    public function test_un_codigo_vencido_se_rechaza(): void
    {
        $codigo = CodigoInstalacion::generarPara($this->pdv);
        $codigo->update(['expira_at' => now()->subMinute()]);

        $this->postJson('/api/v1/pos/provision', ['codigo' => $codigo->codigo])->assertStatus(410);
    }

    public function test_generar_un_codigo_nuevo_invalida_el_anterior(): void
    {
        $viejo = CodigoInstalacion::generarPara($this->pdv);
        CodigoInstalacion::generarPara($this->pdv);

        $this->postJson('/api/v1/pos/provision', ['codigo' => $viejo->codigo])->assertStatus(410);
    }

    public function test_un_codigo_inexistente_se_rechaza(): void
    {
        $this->postJson('/api/v1/pos/provision', ['codigo' => 'AAAA-BBBB'])->assertStatus(404);
    }

    public function test_un_pos_inactivo_no_se_puede_aprovisionar(): void
    {
        $codigo = CodigoInstalacion::generarPara($this->pdv);
        $this->pdv->update(['activo' => false]);

        $this->postJson('/api/v1/pos/provision', ['codigo' => $codigo->codigo])->assertStatus(403);
    }

    public function test_acepta_el_codigo_tipeado_sin_guion_y_en_minusculas(): void
    {
        $codigo = CodigoInstalacion::generarPara($this->pdv);
        $tipeado = strtolower(str_replace('-', '', $codigo->codigo));

        $this->postJson('/api/v1/pos/provision', ['codigo' => $tipeado])->assertOk();
    }

    public function test_el_codigo_no_usa_caracteres_ambiguos(): void
    {
        // Se dicta por teléfono: 0/O y 1/I/L se confunden.
        foreach (range(1, 30) as $ignorado) {
            $codigo = CodigoInstalacion::generarPara($this->pdv);

            $this->assertDoesNotMatchRegularExpression('/[01OIL]/', $codigo->codigo);
            $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $codigo->codigo);
        }
    }
}
