<?php

namespace Tests\Feature;

use App\Livewire\PuntosDeVenta\Index;
use App\Models\CodigoInstalacion;
use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\AppEscritorio;
use App\Support\SaludCaja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SaludCajasTest extends TestCase
{
    use RefreshDatabase;

    private PuntoDeVenta $caja;

    protected function setUp(): void
    {
        parent::setUp();

        $sucursal = Sucursal::create(['nombre' => 'Villa Bosh']);
        $this->caja = PuntoDeVenta::create(['sucursal_id' => $sucursal->id, 'nombre' => 'caja 2', 'secret' => Hash::make('x')]);
    }

    /** @param array<string, mixed> $extra */
    private function reporte(array $extra = []): array
    {
        return array_merge([
            'generado_at' => now()->toIso8601String(),
            'ultima_sincronizacion_stock' => now()->subSeconds(40)->toIso8601String(),
            'ultima_sincronizacion_productos' => now()->subHour()->toIso8601String(),
            'catalogo_pendiente' => false,
            'ventas_pendientes' => 0,
            'venta_pendiente_mas_vieja' => null,
            'movimientos_pendientes' => 0,
            'devoluciones_pendientes' => 0,
            'facturas_pendientes' => 0,
            'factura_pendiente_mas_vieja' => null,
            'facturas_rechazadas' => 0,
            'jobs_en_cola' => 0,
            'jobs_fallidos' => 0,
            'turno_abierto' => ['numero' => 3, 'cajero' => 'Ana', 'abierto_at' => now()->subHours(2)->toIso8601String()],
        ], $extra);
    }

    private function reportar(array $datos): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($this->caja->createToken('pos-sync')->plainTextToken)
            ->postJson('/api/v1/pos/estado', $datos)
            ->assertOk();
        $this->caja->refresh();
    }

    private function llamarConVersion(string $version): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($this->caja->createToken('pos-sync')->plainTextToken)
            ->withHeaders(['X-POS-Version' => $version, 'X-POS-Tipo' => 'escritorio'])
            ->getJson('/api/v1/pos/remitos')
            ->assertOk();
    }

    public function test_la_caja_informa_su_estado_y_si_esta_todo_bien_no_hay_problemas(): void
    {
        $this->reportar($this->reporte());

        $this->assertSame('Ana', $this->caja->estado_caja['turno_abierto']['cajero']);
        $this->assertNotNull($this->caja->ultima_conexion_at, 'el middleware registra la conexión');
        $this->assertSame(['nivel' => 'ok', 'problemas' => []], $this->caja->salud());
    }

    public function test_detecta_el_sync_de_stock_trabado_aunque_la_caja_este_en_linea(): void
    {
        // Lo que le pasó a la caja 2: pos:comandos seguía respondiendo, el stock no.
        $this->reportar($this->reporte(['ultima_sincronizacion_stock' => now()->subHours(11)->toIso8601String()]));

        $salud = $this->caja->salud();

        $this->assertTrue($this->caja->estaConectada());
        $this->assertSame('critico', $salud['nivel']);
        $this->assertStringContainsString('No baja stock hace 11 h', $salud['problemas'][0]['texto']);
    }

    /** @param array<string, \DateTimeInterface|null> $descarga */
    private function descargandoStock(array $descarga): void
    {
        $this->caja->forceFill($descarga)->save();
        $this->caja->refresh();
    }

    public function test_una_descarga_de_stock_en_curso_es_un_estado_en_proceso_y_no_un_error(): void
    {
        $this->reportar($this->reporte(['ultima_sincronizacion_stock' => now()->subMinutes(30)->toIso8601String()]));
        $this->assertSame('critico', $this->caja->salud()['nivel'], 'sin descarga en curso, es un sync trabado');

        $this->descargandoStock([
            'stock_descarga_iniciada_at' => now()->subMinutes(12),
            'stock_descarga_avance_at' => now()->subMinute(),
        ]);

        $salud = $this->caja->salud();

        $this->assertSame('en_proceso', $salud['nivel']);
        $this->assertSame('Descargando stock desde hace 12 min', $salud['problemas'][0]['texto']);
    }

    public function test_una_caja_nueva_que_todavia_no_bajo_stock_no_es_critica_mientras_lo_baja(): void
    {
        $this->reportar($this->reporte(['ultima_sincronizacion_stock' => null]));
        $this->assertStringContainsString('Nunca bajó el stock', $this->caja->salud()['problemas'][0]['texto']);

        $this->descargandoStock([
            'stock_descarga_iniciada_at' => now()->subMinutes(2),
            'stock_descarga_avance_at' => now()->subSeconds(20),
        ]);

        $this->assertSame('en_proceso', $this->caja->salud()['nivel']);
    }

    public function test_si_la_descarga_deja_de_avanzar_vuelve_a_ser_un_sync_trabado(): void
    {
        $this->reportar($this->reporte(['ultima_sincronizacion_stock' => now()->subMinutes(30)->toIso8601String()]));

        $this->descargandoStock([
            'stock_descarga_iniciada_at' => now()->subMinutes(20),
            'stock_descarga_avance_at' => now()->subMinutes(5),
        ]);

        $salud = $this->caja->salud();

        $this->assertSame('critico', $salud['nivel']);
        $this->assertStringContainsString('sync trabado', $salud['problemas'][0]['texto']);
    }

    public function test_un_problema_real_no_queda_tapado_por_la_descarga_en_curso(): void
    {
        $this->reportar($this->reporte(['jobs_fallidos' => 2]));
        $this->descargandoStock([
            'stock_descarga_iniciada_at' => now()->subMinutes(4),
            'stock_descarga_avance_at' => now()->subSeconds(10),
        ]);

        $salud = $this->caja->salud();

        $this->assertSame('alerta', $salud['nivel']);
        $this->assertSame(['en_proceso', 'alerta'], array_column($salud['problemas'], 'nivel'));
    }

    public function test_al_terminar_de_bajar_espera_el_proximo_reporte_y_despues_vuelve_a_evaluar(): void
    {
        $this->reportar($this->reporte(['ultima_sincronizacion_stock' => now()->subMinutes(30)->toIso8601String()]));

        $this->travel(30)->seconds();
        $this->descargandoStock([
            'stock_descarga_iniciada_at' => now()->subMinutes(15),
            'stock_descarga_avance_at' => now(),
            'stock_descarga_terminada_at' => now(),
        ]);

        $this->assertSame('en_proceso', $this->caja->salud()['nivel']);
        $this->assertSame('Terminando de actualizar el stock', $this->caja->salud()['problemas'][0]['texto']);

        $this->travel(20)->seconds();
        $this->reportar($this->reporte());
        $this->assertSame('ok', $this->caja->salud()['nivel']);
    }

    public function test_si_la_caja_no_informa_tras_terminar_la_descarga_vuelve_a_ser_un_sync_trabado(): void
    {
        $this->reportar($this->reporte(['ultima_sincronizacion_stock' => now()->subMinutes(30)->toIso8601String()]));

        $this->travel(30)->seconds();
        $this->descargandoStock([
            'stock_descarga_iniciada_at' => now()->subMinutes(15),
            'stock_descarga_avance_at' => now(),
            'stock_descarga_terminada_at' => now(),
        ]);

        $this->travel(4)->minutes();

        $this->assertSame('critico', $this->caja->salud()['nivel']);
    }

    public function test_puntos_de_venta_muestra_en_proceso_con_spinner_y_no_lo_cuenta_como_problema(): void
    {
        CodigoInstalacion::generarPara($this->caja, null)->update(['usado_at' => now()]);
        $this->reportar($this->reporte(['ultima_sincronizacion_stock' => now()->subMinutes(30)->toIso8601String()]));
        $this->descargandoStock([
            'stock_descarga_iniciada_at' => now()->subMinutes(12),
            'stock_descarga_avance_at' => now()->subMinute(),
        ]);

        $usuario = User::factory()->create();
        $usuario->assignRole(tap(Role::findOrCreate('terminales-'.uniqid(), 'web'))->givePermissionTo('terminales.ver'));
        $this->actingAs($usuario);

        Livewire::test(Index::class)
            ->assertSee('En proceso')
            ->assertSee('Descargando stock desde hace 12 min')
            ->assertSee('animate-spin', false)
            ->assertDontSee('Con problemas')
            ->assertDontSee('necesitan atención');
    }

    /** Caja de escritorio 1.9.10 conectada por última vez hace `$minutosSinConexion`, con su último reporte al día. */
    private function cajaDeEscritorioSinConexion(int $minutosSinConexion, string $version = '1.9.10'): void
    {
        $this->reportar($this->reporte());
        $this->caja->forceFill([
            'tipo_instalacion' => 'escritorio',
            'version_pos' => $version,
            'ultima_conexion_at' => now()->subMinutes($minutosSinConexion),
            'estado_reportado_at' => now()->subMinutes($minutosSinConexion),
        ])->save();
    }

    public function test_una_caja_de_escritorio_sin_conexion_tras_publicarse_una_version_nueva_se_ve_actualizandose(): void
    {
        $this->cajaDeEscritorioSinConexion(8);

        $salud = SaludCaja::evaluar($this->caja, '1.9.11', publicadaAt: now()->subMinutes(30));

        $this->assertSame('en_proceso', $salud['nivel']);
        $this->assertSame('Sin conexión hace 8 min: probablemente se está actualizando a 1.9.11', $salud['problemas'][0]['texto']);
        $this->assertCount(1, $salud['problemas'], 'ni la falta de conexión ni la versión vieja se cuentan como problema');
    }

    public function test_sin_conexion_no_se_presume_actualizacion_si_no_hay_motivo(): void
    {
        // Ya estaba desconectada cuando se publicó la versión nueva: está caída, no actualizándose.
        $this->cajaDeEscritorioSinConexion(5);
        $this->assertSame('alerta', SaludCaja::evaluar($this->caja, '1.9.11', publicadaAt: now()->subMinutes(2))['nivel']);

        // Lleva demasiado rato: vuelve a ser una caída.
        $this->cajaDeEscritorioSinConexion(25);
        $this->assertSame('critico', SaludCaja::evaluar($this->caja, '1.9.11', publicadaAt: now()->subHour())['nivel']);

        // Ya tiene la última versión: no hay nada que actualizar.
        $this->cajaDeEscritorioSinConexion(5, '1.9.11');
        $this->assertSame('alerta', SaludCaja::evaluar($this->caja, '1.9.11', publicadaAt: now()->subHour())['nivel']);

        // Instalación clásica: se actualiza por orden del Manager, no reemplazando la carpeta.
        $this->cajaDeEscritorioSinConexion(5);
        $this->caja->forceFill(['tipo_instalacion' => 'clasica'])->save();
        $this->assertSame('alerta', SaludCaja::evaluar($this->caja, '1.9.11', publicadaAt: now()->subHour())['nivel']);
    }

    public function test_al_reabrir_con_otra_version_espera_el_primer_reporte_en_vez_de_alertar(): void
    {
        $this->reportar($this->reporte());
        $this->llamarConVersion('1.9.10');
        $this->assertNull($this->caja->refresh()->version_anterior, 'la primera versión que informa no es un cambio');

        // Estuvo cerrada 8 minutos y al abrir informa otra versión; su último reporte es de antes.
        $this->travel(8)->minutes();
        $this->llamarConVersion('1.9.11');

        $salud = $this->caja->refresh()->salud();

        $this->assertSame('1.9.10', $this->caja->version_anterior);
        $this->assertNotNull($this->caja->version_actualizada_at);
        $this->assertSame('en_proceso', $salud['nivel']);
        $this->assertSame('Reinició con la versión 1.9.11 (antes 1.9.10): esperando su primer reporte', $salud['problemas'][0]['texto']);

        $this->reportar($this->reporte());
        $this->assertSame('ok', $this->caja->salud()['nivel']);
    }

    public function test_repetir_la_misma_version_no_cuenta_como_actualizacion(): void
    {
        $this->reportar($this->reporte());
        $this->llamarConVersion('1.9.10');
        $this->travel(2)->minutes();
        $this->llamarConVersion('1.9.10');

        $this->assertNull($this->caja->refresh()->version_actualizada_at);
    }

    public function test_la_fecha_de_publicacion_sale_del_archivo_publicado(): void
    {
        Storage::fake('local');
        $this->assertNull(AppEscritorio::fechaDePublicacion());

        Storage::disk('local')->put('pos-escritorio/pos-escritorio.json', json_encode(['version' => '1.9.11', 'formato' => 'zip', 'generado_at' => '2026-09-19T13:03:59+00:00']));
        Storage::disk('local')->put('pos-escritorio/pos-escritorio.zip', 'zip');

        $this->assertSame('2026-09-19 13:03:59', AppEscritorio::fechaDePublicacion()->utc()->format('Y-m-d H:i:s'));
    }

    public function test_las_antiguedades_se_miden_con_el_reloj_de_la_caja(): void
    {
        // Reloj de la caja 3 horas atrasado, pero el stock se bajó hace 1 minuto para ella.
        $reloj = now()->subHours(3);
        $this->reportar($this->reporte([
            'generado_at' => $reloj->toIso8601String(),
            'ultima_sincronizacion_stock' => $reloj->copy()->subMinute()->toIso8601String(),
        ]));

        $this->assertSame('ok', $this->caja->salud()['nivel']);
    }

    public function test_ventas_sin_enviar_facturas_y_cola(): void
    {
        $this->reportar($this->reporte([
            'ventas_pendientes' => 4,
            'venta_pendiente_mas_vieja' => now()->subHours(2)->toIso8601String(),
            'facturas_pendientes' => 2,
            'factura_pendiente_mas_vieja' => now()->subHour()->toIso8601String(),
            'facturas_rechazadas' => 1,
            'jobs_fallidos' => 3,
            'jobs_en_cola' => 50,
        ]));

        $textos = array_column($this->caja->salud()['problemas'], 'texto');

        $this->assertSame('critico', $this->caja->salud()['nivel']);
        foreach (['4 venta(s) sin enviar', '2 factura(s) sin CAE', '1 factura(s) rechazada(s)', '3 envío(s) fallidos', '50 trabajos acumulados'] as $esperado) {
            $this->assertNotEmpty(array_filter($textos, fn ($t) => str_contains($t, $esperado)), "falta «{$esperado}»");
        }
    }

    public function test_sin_conexion_version_vieja_y_cajas_que_no_informan(): void
    {
        $this->assertSame('sin_datos', $this->caja->salud()['nivel'], 'nunca se conectó');

        $this->caja->forceFill(['ultima_conexion_at' => now(), 'version_pos' => '1.3.0'])->save();
        $salud = $this->caja->salud('1.4.2');
        $this->assertSame('alerta', $salud['nivel']);
        $this->assertStringContainsString('1.3.0 desactualizada', $salud['problemas'][0]['texto']);
        $this->assertStringContainsString('No informa su estado', $salud['problemas'][1]['texto']);

        $this->reportar($this->reporte());
        $this->caja->forceFill(['ultima_conexion_at' => now()->subHours(2)])->save();
        $this->assertSame('critico', SaludCaja::evaluar($this->caja->fresh())['nivel']);

        $this->caja->forceFill(['activo' => false])->save();
        $this->assertSame('inactiva', $this->caja->fresh()->salud()['nivel']);
    }

    public function test_reporte_invalido_se_rechaza_y_sin_token_no_entra(): void
    {
        $this->postJson('/api/v1/pos/estado', $this->reporte())->assertUnauthorized();

        $this->withToken($this->caja->createToken('pos-sync')->plainTextToken)
            ->postJson('/api/v1/pos/estado', ['ventas_pendientes' => -1])
            ->assertUnprocessable();
    }

    public function test_puntos_de_venta_muestra_el_estado_y_avisa(): void
    {
        CodigoInstalacion::generarPara($this->caja, null)->update(['usado_at' => now()]);
        $this->reportar($this->reporte(['ultima_sincronizacion_stock' => now()->subHours(11)->toIso8601String()]));

        $usuario = User::factory()->create();
        $usuario->assignRole(tap(Role::findOrCreate('terminales-'.uniqid(), 'web'))->givePermissionTo('terminales.ver'));
        $this->actingAs($usuario);

        Livewire::test(Index::class)
            ->assertSee('1 caja(s) necesitan atención')
            ->assertSee('Con problemas')
            ->assertSee('No baja stock hace 11 h')
            ->assertSee('caja abierta (Ana)');
    }
}
