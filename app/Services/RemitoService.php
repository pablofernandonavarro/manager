<?php

namespace App\Services;

use App\Enums\EstadoRemito;
use App\Enums\TipoMovimiento;
use App\Exceptions\RemitoException;
use App\Models\MovimientoStock;
use App\Models\Product;
use App\Models\PuntoDeVenta;
use App\Models\Remito;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Único lugar donde un remito toca stock. Las pantallas (Nuevo remito, Stock por
 * sucursal, Remitos) solo arman los datos y llaman acá.
 *
 * Ciclo: crear descuenta del origen (la mercadería queda "en tránsito"), confirmar la
 * acredita en el destino, cancelar la devuelve al origen. Cada paso deja un movimiento
 * de stock de tipo transferencia y recalcula products.stock, que es la suma de
 * stock_sucursal (lo en tránsito no está en ninguna sucursal, así que no suma).
 */
class RemitoService
{
    /**
     * @param  array<int|string, int|string>  $items  product_id => cantidad
     */
    public function crear(int $origenId, int $destinoId, array $items, ?User $user = null, ?string $observaciones = null): Remito
    {
        if ($origenId === $destinoId) {
            throw new RemitoException('El origen y el destino tienen que ser sucursales distintas.');
        }

        $sucursales = Sucursal::whereIn('id', [$origenId, $destinoId])->where('activo', true)->pluck('id');

        if ($sucursales->count() !== 2) {
            throw new RemitoException('El origen o el destino no existe o está inactivo.');
        }

        $cantidades = collect($items)
            ->mapWithKeys(fn ($cantidad, $productId) => [(int) $productId => (int) $cantidad])
            ->filter(fn (int $cantidad) => $cantidad > 0);

        if ($cantidades->isEmpty()) {
            throw new RemitoException('Agregá al menos un artículo con cantidad mayor a 0.');
        }

        $productos = Product::whereIn('id', $cantidades->keys())->get()->keyBy('id');

        if ($productos->count() !== $cantidades->count()) {
            throw new RemitoException('Uno de los artículos ya no existe.');
        }

        return DB::transaction(function () use ($origenId, $destinoId, $cantidades, $productos, $user, $observaciones) {
            // Se bloquean las filas del origen: dos remitos simultáneos no pueden sacar las
            // mismas unidades. El disponible se lee acá, nunca de lo que mandó la pantalla.
            $stock = StockSucursal::where('sucursal_id', $origenId)
                ->whereIn('product_id', $cantidades->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            $faltantes = $cantidades
                ->filter(fn (int $cantidad, int $productId) => $cantidad > ($stock[$productId]->cantidad ?? 0))
                ->map(fn (int $cantidad, int $productId) => sprintf(
                    '%s: pedís %d, hay %d',
                    $productos[$productId]->codigo_interno ?: $productos[$productId]->nombre,
                    $cantidad,
                    $stock[$productId]->cantidad ?? 0
                ));

            if ($faltantes->isNotEmpty()) {
                throw new RemitoException('No hay stock suficiente en el origen. '.$faltantes->implode('; ').'.');
            }

            $remito = Remito::create([
                'sucursal_origen_id' => $origenId,
                'sucursal_destino_id' => $destinoId,
                'user_id' => $user?->id,
                'estado' => EstadoRemito::Remitido,
                'observaciones' => $observaciones ?: null,
                'remitido_at' => now(),
            ]);

            foreach ($cantidades as $productId => $cantidad) {
                $stock[$productId]->decrement('cantidad', $cantidad);

                $remito->detalles()->create(['product_id' => $productId, 'cantidad' => $cantidad]);

                $this->registrarMovimiento($remito, $origenId, $productId, -$cantidad);
            }

            $this->recalcularTotales($cantidades->keys()->all());

            return $remito->load('detalles');
        });
    }

    /**
     * Lo recibe un usuario del Manager o la caja de la sucursal destino.
     */
    public function confirmar(Remito $remito, ?User $usuario = null, ?PuntoDeVenta $caja = null): Remito
    {
        return $this->cerrar($remito, EstadoRemito::Confirmado, $usuario, $caja);
    }

    public function cancelar(Remito $remito): Remito
    {
        return $this->cerrar($remito, EstadoRemito::Cancelado);
    }

    /**
     * Confirmar y cancelar son el mismo movimiento con distinto destino del stock: al
     * destino del remito o de vuelta al origen.
     */
    private function cerrar(Remito $remito, EstadoRemito $nuevoEstado, ?User $usuario = null, ?PuntoDeVenta $caja = null): Remito
    {
        return DB::transaction(function () use ($remito, $nuevoEstado, $usuario, $caja) {
            // Bloqueo del remito: dos clics seguidos (o dos usuarios) no pueden acreditar
            // la misma mercadería dos veces. El estado se relee dentro del bloqueo.
            $remito = Remito::with('detalles')->lockForUpdate()->findOrFail($remito->id);

            if ($remito->estado !== EstadoRemito::Remitido) {
                throw new RemitoException("El remito #{$remito->id} ya está {$remito->estado->label()}.");
            }

            $sucursalId = $nuevoEstado === EstadoRemito::Confirmado
                ? $remito->sucursal_destino_id
                : $remito->sucursal_origen_id;

            foreach ($remito->detalles as $detalle) {
                StockSucursal::firstOrCreate(
                    ['sucursal_id' => $sucursalId, 'product_id' => $detalle->product_id],
                    ['cantidad' => 0]
                )->increment('cantidad', $detalle->cantidad);

                $this->registrarMovimiento($remito, $sucursalId, $detalle->product_id, $detalle->cantidad, $caja);
            }

            $this->recalcularTotales($remito->detalles->pluck('product_id')->all());

            $esConfirmacion = $nuevoEstado === EstadoRemito::Confirmado;

            $remito->update([
                'estado' => $nuevoEstado,
                'confirmado_at' => $esConfirmacion ? now() : null,
                'confirmado_por_user_id' => $esConfirmacion ? $usuario?->id : null,
                'confirmado_por_punto_de_venta_id' => $esConfirmacion ? $caja?->id : null,
            ]);

            return $remito;
        });
    }

    private function registrarMovimiento(Remito $remito, int $sucursalId, int $productId, int $cantidad, ?PuntoDeVenta $caja = null): void
    {
        MovimientoStock::create([
            'punto_de_venta_id' => $caja?->id,
            'sucursal_id' => $sucursalId,
            'product_id' => $productId,
            'tipo' => TipoMovimiento::Transferencia,
            'cantidad' => $cantidad,
            'referencia' => 'Remito #'.str_pad((string) $remito->id, 6, '0', STR_PAD_LEFT),
            'fecha' => now(),
        ]);
    }

    /**
     * @param  array<int, int>  $productIds
     */
    private function recalcularTotales(array $productIds): void
    {
        foreach (array_unique($productIds) as $productId) {
            Product::whereKey($productId)->update([
                'stock' => StockSucursal::where('product_id', $productId)->sum('cantidad'),
            ]);
        }
    }
}
