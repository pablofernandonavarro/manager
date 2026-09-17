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
    public function crear(int $origenId, int $destinoId, array $items, ?User $user = null, ?string $observaciones = null, ?PuntoDeVenta $caja = null): Remito
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

        return DB::transaction(function () use ($origenId, $destinoId, $cantidades, $productos, $user, $observaciones, $caja) {
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
                'creado_por_punto_de_venta_id' => $caja?->id,
                'estado' => EstadoRemito::Remitido,
                'observaciones' => $observaciones ?: null,
                'remitido_at' => now(),
            ]);

            foreach ($cantidades as $productId => $cantidad) {
                $stock[$productId]->decrement('cantidad', $cantidad);

                $remito->detalles()->create(['product_id' => $productId, 'cantidad' => $cantidad]);

                $this->registrarMovimiento($remito, $origenId, $productId, -$cantidad, $caja);
            }

            $this->recalcularTotales($cantidades->keys()->all());

            return $remito->load('detalles');
        });
    }

    /**
     * Lo recibe un usuario del Manager o la caja de la sucursal destino.
     *
     * @param  array<int, int>|null  $cantidadesRecibidas  product_id => cantidad recibida. null = recibir todo.
     */
    public function confirmar(Remito $remito, ?User $usuario = null, ?PuntoDeVenta $caja = null, ?array $cantidadesRecibidas = null, ?int $destinoRechazadosId = null): Remito
    {
        // Validar early: si hay rechazos y config='elegir', destinoRechazadosId es obligatorio.
        // Esto evita excepciones dentro de transacciones anidadas.
        if ($cantidadesRecibidas) {
            $hayRechazos = collect($cantidadesRecibidas)->some(function ($recibidas, $productId) use ($remito) {
                $item = collect($remito->detalles)->firstWhere('product_id', (int)$productId);
                return $item && $recibidas < $item['cantidad'];
            });

            if ($hayRechazos) {
                $config = \App\Models\ConfiguracionRemitos::actual();
                if ($config->destino_rechazados === 'elegir' && !$destinoRechazadosId) {
                    throw new RemitoException("Elegí a dónde va la mercadería no recibida del remito #{$remito->id}.");
                }
            }
        }

        return $this->cerrar($remito, EstadoRemito::Confirmado, $usuario, $caja, $cantidadesRecibidas, $destinoRechazadosId);
    }

    public function cancelar(Remito $remito): Remito
    {
        return $this->cerrar($remito, EstadoRemito::Cancelado);
    }

    /**
     * Confirmar y cancelar son el mismo movimiento con distinto destino del stock: al
     * destino del remito o de vuelta al origen. En confirmación con recepción parcial,
     * acredita SIEMPRE la cantidad total del detalle (recibida + rechazada), porque lo
     * rechazado también llegó físicamente. El remito hijo que se genera automáticamente
     * descuenta de ahí lo que se reenvía.
     *
     * @param  array<int, int>|null  $cantidadesRecibidas  product_id => cantidad recibida. null = recibir todo.
     */
    private function cerrar(Remito $remito, EstadoRemito $nuevoEstado, ?User $usuario = null, ?PuntoDeVenta $caja = null, ?array $cantidadesRecibidas = null, ?int $destinoRechazadosId = null): Remito
    {
        return DB::transaction(function () use ($remito, $nuevoEstado, $usuario, $caja, $cantidadesRecibidas, $destinoRechazadosId) {
            // Bloqueo del remito: dos clics seguidos (o dos usuarios) no pueden acreditar
            // la misma mercadería dos veces. El estado se relee dentro del bloqueo.
            $remito = Remito::with('detalles')->lockForUpdate()->findOrFail($remito->id);

            if ($remito->estado !== EstadoRemito::Remitido) {
                throw new RemitoException("El remito #{$remito->id} ya está {$remito->estado->label()}.");
            }

            $esConfirmacion = $nuevoEstado === EstadoRemito::Confirmado;
            $sucursalId = $esConfirmacion ? $remito->sucursal_destino_id : $remito->sucursal_origen_id;
            $rechazos = [];

            foreach ($remito->detalles as $detalle) {
                $recibida = $esConfirmacion
                    ? max(0, min($detalle->cantidad, (int) ($cantidadesRecibidas[$detalle->product_id] ?? $detalle->cantidad)))
                    : $detalle->cantidad;

                // Se acredita SIEMPRE la cantidad total del detalle, no solo $recibida. Lo
                // rechazado también llegó físicamente a esta sucursal — solo que no se acepta
                // y se reenvía enseguida más abajo, vía el remito hijo. Si acreditáramos solo
                // $recibida, crear() (llamado abajo para el hijo) intentaría descontar de
                // stock_sucursal una cantidad que nunca se cargó ahí: o revienta la excepción
                // de "no hay stock suficiente" (perdiendo también lo aceptado, por estar todo
                // en la misma transacción) o, si había stock previo de otro origen, descuenta
                // stock bueno para financiar el envío de lo defectuoso. Acreditando el total,
                // el neto para esta sucursal queda igual (+$recibida) una vez que el remito
                // hijo saca lo rechazado, y crear() siempre encuentra stock real que descontar.
                StockSucursal::firstOrCreate(
                    ['sucursal_id' => $sucursalId, 'product_id' => $detalle->product_id],
                    ['cantidad' => 0]
                )->increment('cantidad', $detalle->cantidad);

                $this->registrarMovimiento($remito, $sucursalId, $detalle->product_id, $detalle->cantidad, $caja);

                if ($esConfirmacion) {
                    $rechazada = $detalle->cantidad - $recibida;
                    $detalle->update(['cantidad_recibida' => $recibida, 'cantidad_rechazada' => $rechazada]);
                    if ($rechazada > 0) {
                        $rechazos[$detalle->product_id] = $rechazada;
                    }
                }
            }

            $this->recalcularTotales($remito->detalles->pluck('product_id')->all());

            $remito->update([
                'estado' => $nuevoEstado,
                'confirmado_at' => $esConfirmacion ? now() : null,
                'confirmado_por_user_id' => $esConfirmacion ? $usuario?->id : null,
                'confirmado_por_punto_de_venta_id' => $esConfirmacion ? $caja?->id : null,
            ]);

            // Remito hijo por lo rechazado, dentro de la misma transacción.
            if ($esConfirmacion && $rechazos !== []) {
                // Validar destino ANTES de crear (fuera de transacción anidada) para evitar
                // problemas de rollback a savepoints que no existen en SQLite.
                try {
                    $destinoHijoId = $this->resolverDestinoRechazados($remito, $sucursalId, $destinoRechazadosId);
                } catch (RemitoException $e) {
                    throw $e;
                }

                $hijo = $this->crear(
                    $sucursalId,
                    $destinoHijoId,
                    $rechazos,
                    $usuario,
                    "Mercadería no recibida del remito #{$remito->id}.",
                    $caja
                );

                $hijo->update(['remito_origen_id' => $remito->id]);
            }

            return $remito->fresh('detalles');
        });
    }

    /**
     * Resuelve el destino de lo rechazado según la configuración global.
     */
    private function resolverDestinoRechazados(Remito $remito, int $sucursalQueRechaza, ?int $destinoElegido): int
    {
        $config = \App\Models\ConfiguracionRemitos::actual();

        $destinoId = match ($config->destino_rechazados) {
            'origen' => $remito->sucursal_origen_id,
            'manager' => Sucursal::where('is_central', true)->value('id'),
            'elegir' => $destinoElegido ?? throw new RemitoException("Elegí a dónde va la mercadería no recibida del remito #{$remito->id}."),
            default => $remito->sucursal_origen_id,
        };

        // Con 'elegir', si piden mandarlo a la propia sucursal que está rechazando, es un
        // error de quien llama (UI o API mal usada) — se avisa explícito, no se corrige en
        // silencio como el fallback de abajo.
        if ($config->destino_rechazados === 'elegir' && $destinoId === $sucursalQueRechaza) {
            throw new RemitoException('El destino de lo rechazado no puede ser la misma sucursal que lo está rechazando.');
        }

        // Caso borde solo posible con config="manager": si quien rechaza YA ES la Central
        // (el remito iba directo a la Central y ahí mismo se detecta el defecto), no hay
        // remito posible hacia sí misma. Cae al origen del remito padre, que por
        // construcción siempre es distinto (crear() exige origen != destino al crear el
        // padre). Con config="origen" este fallback nunca se activa: sucursal_origen_id y
        // sucursal_destino_id (quien rechaza) ya son distintos por la misma razón.
        return $destinoId !== $sucursalQueRechaza ? $destinoId : $remito->sucursal_origen_id;
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
        Product::recalcularStock($productIds);
    }
}
