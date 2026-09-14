<?php

namespace Tests\Feature;

use App\Models\MovimientoStock;
use App\Models\Product;
use App\Models\PuntoDeVenta;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SyncIdempotenciaTest extends TestCase
{
    use RefreshDatabase;

    private PuntoDeVenta $pdv;

    private Product $producto;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $sucursal = Sucursal::create(['nombre' => 'Sucursal Test']);

        $this->pdv = PuntoDeVenta::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Caja Test',
            'secret' => Hash::make('secreto'),
        ]);

        $this->producto = Product::factory()->create(['stock' => 100]);

        StockSucursal::create([
            'sucursal_id' => $sucursal->id,
            'product_id' => $this->producto->id,
            'cantidad' => 100,
        ]);

        // PuntoDeVenta no es Authenticatable, así que se usa un token real como en producción.
        $this->token = $this->pdv->createToken('pos-sync')->plainTextToken;
    }

    /** @param array<string, mixed> $payload */
    private function postComoPos(string $uri, array $payload): TestResponse
    {
        return $this->withToken($this->token)->postJson($uri, $payload);
    }

    /** @return array<string, mixed> */
    private function payloadVenta(string $uuid): array
    {
        return [
            'ventas' => [[
                'uuid' => $uuid,
                'numero_venta' => 'PDV01-000001',
                'fecha' => now()->toIso8601String(),
                'subtotal' => 500,
                'descuento' => 0,
                'total' => 500,
                'items' => [[
                    'product_id' => $this->producto->id,
                    'cantidad' => 3,
                    'precio_unitario' => 166.67,
                    'subtotal' => 500,
                ]],
            ]],
        ];
    }

    public function test_reenviar_la_misma_venta_no_la_duplica(): void
    {
        $uuid = (string) Str::uuid();

        $primera = $this->postComoPos('/api/v1/sync/ventas', $this->payloadVenta($uuid));
        $primera->assertOk();
        $this->assertSame('creada', $primera->json('resultados.0.status'));

        // Mismo uuid otra vez: simula el retry de ManagerApiService tras un timeout.
        $segunda = $this->postComoPos('/api/v1/sync/ventas', $this->payloadVenta($uuid));
        $segunda->assertOk();
        $this->assertSame('duplicada', $segunda->json('resultados.0.status'));

        $this->assertSame(1, Venta::where('uuid', $uuid)->count());
        $this->assertSame(1, Venta::count());
    }

    public function test_reenviar_la_misma_venta_no_descuenta_el_stock_dos_veces(): void
    {
        $uuid = (string) Str::uuid();

        $this->postComoPos('/api/v1/sync/ventas', $this->payloadVenta($uuid))->assertOk();
        $this->postComoPos('/api/v1/sync/ventas', $this->payloadVenta($uuid))->assertOk();

        $stock = StockSucursal::where('sucursal_id', $this->pdv->sucursal_id)
            ->where('product_id', $this->producto->id)
            ->value('cantidad');

        // 100 - 3 una sola vez, no 100 - 6.
        $this->assertSame(97, $stock);
    }

    public function test_ventas_distintas_si_se_crean(): void
    {
        $this->postComoPos('/api/v1/sync/ventas', $this->payloadVenta((string) Str::uuid()))->assertOk();
        $this->postComoPos('/api/v1/sync/ventas', $this->payloadVenta((string) Str::uuid()))->assertOk();

        $this->assertSame(2, Venta::count());
    }

    public function test_venta_sin_uuid_es_rechazada(): void
    {
        $payload = $this->payloadVenta((string) Str::uuid());
        unset($payload['ventas'][0]['uuid']);

        $this->postComoPos('/api/v1/sync/ventas', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('ventas.0.uuid');
    }

    public function test_reenviar_el_mismo_movimiento_no_lo_duplica(): void
    {
        $uuid = (string) Str::uuid();

        $payload = [
            'movimientos' => [[
                'uuid' => $uuid,
                'product_id' => $this->producto->id,
                'tipo' => 'ajuste',
                'cantidad' => 10,
                'referencia' => 'AJUSTE-1',
                'fecha' => now()->toIso8601String(),
            ]],
        ];

        $primera = $this->postComoPos('/api/v1/sync/movimientos', $payload);
        $primera->assertOk();
        $this->assertSame('creado', $primera->json('resultados.0.status'));

        $segunda = $this->postComoPos('/api/v1/sync/movimientos', $payload);
        $segunda->assertOk();
        $this->assertSame('duplicado', $segunda->json('resultados.0.status'));

        $this->assertSame(1, MovimientoStock::where('uuid', $uuid)->count());

        $stock = StockSucursal::where('sucursal_id', $this->pdv->sucursal_id)
            ->where('product_id', $this->producto->id)
            ->value('cantidad');

        // +10 una sola vez.
        $this->assertSame(110, $stock);
    }
}
