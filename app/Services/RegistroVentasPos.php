<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\DetalleVenta;
use App\Models\MovimientoStock;
use App\Models\Product;
use App\Models\PromocionBancaria;
use App\Models\PuntoDeVenta;
use App\Models\StockSucursal;
use App\Models\Venta;
use App\Services\Facturacion\EmisionComprobantes;
use Carbon\CarbonInterface;

/**
 * Registra en el Manager una venta que mandó una caja: la venta, sus pagos, el descuento
 * de stock y, si la caja la marcó para facturar, el comprobante pendiente. Idempotente por
 * uuid. Tiene que correr dentro de una transacción.
 */
class RegistroVentasPos
{
    public function __construct(
        private readonly EmisionComprobantes $emision,
        private readonly CuentaCorrienteService $cuentas,
    ) {}

    /**
     * @param  array<string, mixed>  $datos  Una venta tal como la valida SyncVentasRequest.
     * @return array{status: string, venta: Venta, comprobante: ?Comprobante}
     */
    public function registrar(PuntoDeVenta $pdv, array $datos, CarbonInterface $sincronizadoAt): array
    {
        $venta = Venta::where('uuid', $datos['uuid'])->first();
        $status = 'duplicada';

        if (! $venta) {
            $venta = $this->crearVenta($pdv, $datos, $sincronizadoAt);
            $status = 'creada';
        } elseif ($venta->punto_de_venta_id !== $pdv->id) {
            // Un uuid de otra caja: se confirma para que no reintente, pero no se toca.
            return ['status' => $status, 'venta' => $venta, 'comprobante' => null];
        }

        // También para una duplicada: si la caja reintenta, la factura se crea una sola vez.
        $comprobante = array_key_exists('factura', $datos) && is_array($datos['factura'])
            ? $this->emision->crearParaVenta($venta, $pdv, $datos['factura'])
            : Comprobante::where('venta_id', $venta->id)->first();

        return ['status' => $status, 'venta' => $venta, 'comprobante' => $comprobante];
    }

    /** @param  array<string, mixed>  $datos */
    private function crearVenta(PuntoDeVenta $pdv, array $datos, CarbonInterface $sincronizadoAt): Venta
    {
        $venta = Venta::create([
            'uuid' => $datos['uuid'],
            'punto_de_venta_id' => $pdv->id,
            'sucursal_id' => $pdv->sucursal_id,
            'lista_precio_id' => $datos['lista_precio_id'] ?? null,
            'turno_uuid' => $datos['turno_uuid'] ?? null,
            'cajero' => $datos['cajero'] ?? null,
            'numero_venta' => $datos['numero_venta'] ?? null,
            'fecha' => $datos['fecha'],
            'subtotal' => $datos['subtotal'],
            'descuento' => $datos['descuento'] ?? 0,
            'descuento_manual' => $datos['descuento_manual'] ?? 0,
            'descuento_autorizado_por' => $datos['descuento_autorizado_por'] ?? null,
            'total' => $datos['total'],
            'metodo_pago' => $datos['metodo_pago'] ?? null,
            'cliente_nombre' => $datos['cliente_nombre'] ?? null,
            'cliente_documento' => $datos['cliente_documento'] ?? null,
            // Sin exists en la validación: un cliente borrado mientras la caja estaba offline no
            // puede trabar la venta. Se vincula solo si existe.
            'cliente_id' => isset($datos['cliente_id']) && Cliente::whereKey($datos['cliente_id'])->exists() ? $datos['cliente_id'] : null,
            'sincronizado_at' => $sincronizadoAt,
        ]);

        $this->guardarPagos($venta, $datos['pagos'] ?? []);
        $this->cuentas->registrarVenta($venta);

        $productIds = [];

        foreach ($datos['items'] as $item) {
            DetalleVenta::create([
                'venta_id' => $venta->id,
                'product_id' => $item['product_id'],
                'cantidad' => $item['cantidad'],
                'precio_unitario' => $item['precio_unitario'],
                'subtotal' => $item['subtotal'],
            ]);

            MovimientoStock::create([
                'punto_de_venta_id' => $pdv->id,
                'sucursal_id' => $pdv->sucursal_id,
                'product_id' => $item['product_id'],
                'tipo' => 'venta',
                'cantidad' => -abs($item['cantidad']),
                'referencia' => $venta->numero_venta,
                'fecha' => $datos['fecha'],
                'sincronizado_at' => $sincronizadoAt,
            ]);

            StockSucursal::aplicarDelta($pdv->sucursal_id, (int) $item['product_id'], -abs((int) $item['cantidad']));

            $productIds[] = $item['product_id'];
        }

        // Stock global = suma de stock_sucursal, sin tocar el updated_at del catálogo.
        Product::recalcularStock($productIds);

        return $venta;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pagos
     */
    private function guardarPagos(Venta $venta, array $pagos): void
    {
        $promocionesExistentes = PromocionBancaria::whereIn('id', array_filter(array_column($pagos, 'promocion_id')))->pluck('id')->all();

        foreach ($pagos as $pago) {
            $promocionId = $pago['promocion_id'] ?? null;

            $venta->pagos()->create([
                'medio' => $pago['medio'],
                'monto' => $pago['monto'],
                'descuento' => $pago['descuento'] ?? 0,
                'importe' => $pago['importe'],
                'tarjeta' => $pago['tarjeta'] ?? null,
                'banco' => $pago['banco'] ?? null,
                'cuotas' => $pago['cuotas'] ?? null,
                'promocion_bancaria_id' => in_array($promocionId, $promocionesExistentes, true) ? $promocionId : null,
                'promocion_nombre' => $pago['promocion_nombre'] ?? null,
                'referencia' => $pago['referencia'] ?? null,
            ]);
        }
    }
}
