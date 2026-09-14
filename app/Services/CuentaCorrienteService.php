<?php

namespace App\Services;

use App\Exceptions\CuentaCorrienteException;
use App\Models\Cliente;
use App\Models\Devolucion;
use App\Models\MovimientoCuentaCorriente;
use App\Models\PuntoDeVenta;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\Log;

/**
 * Movimientos de cuenta corriente: ventas a cuenta y devoluciones que llegan de las cajas,
 * cobros hechos en una caja y pagos o ajustes cargados en el Manager.
 *
 * La caja usa el mismo criterio para su saldo local (ver POS `CuentaCorrienteService`):
 * si cambian las reglas de acá, hay que cambiarlas allá.
 */
class CuentaCorrienteService
{
    public const MEDIOS_DE_COBRO = ['efectivo', 'debito', 'credito', 'transferencia', 'qr'];

    /** Venta con parte cobrada "a cuenta": suma deuda al cliente. */
    public function registrarVenta(Venta $venta): void
    {
        $aCuenta = round((float) $venta->pagos()->where('medio', 'cuenta_corriente')->sum('importe'), 2);

        if ($aCuenta <= 0) {
            return;
        }

        if (! $venta->cliente_id) {
            // La caja no deja vender a cuenta sin cliente; si llega así (cliente borrado en el
            // Manager mientras la caja estaba offline) queda registrado para revisarlo.
            Log::warning('Venta a cuenta corriente sin cliente', ['venta' => $venta->uuid, 'importe' => $aCuenta]);

            return;
        }

        MovimientoCuentaCorriente::firstOrCreate(
            ['venta_id' => $venta->id, 'tipo' => 'venta'],
            [
                'cliente_id' => $venta->cliente_id,
                'importe' => $aCuenta,
                'punto_de_venta_id' => $venta->punto_de_venta_id,
                'descripcion' => 'Venta '.($venta->numero_venta ?? $venta->uuid),
                'fecha' => $venta->fecha,
            ]
        );
    }

    /**
     * Devolución con reintegro "al medio original" de una venta hecha a cuenta: baja la
     * deuda, como mucho lo que se cargó a cuenta en esa venta.
     */
    public function registrarDevolucion(Devolucion $devolucion): void
    {
        $venta = $devolucion->venta;

        if ($devolucion->reintegro !== 'medio_original' || ! $venta?->cliente_id) {
            return;
        }

        $cargado = (float) MovimientoCuentaCorriente::where('venta_id', $venta->id)->where('tipo', 'venta')->sum('importe');
        $yaAcreditado = -(float) MovimientoCuentaCorriente::where('venta_id', $venta->id)->where('tipo', 'devolucion')->sum('importe');
        $credito = round(min((float) $devolucion->total, $cargado - $yaAcreditado), 2);

        if ($credito <= 0) {
            return;
        }

        MovimientoCuentaCorriente::firstOrCreate(
            ['devolucion_id' => $devolucion->id],
            [
                'cliente_id' => $venta->cliente_id,
                'tipo' => 'devolucion',
                'importe' => -$credito,
                'venta_id' => $venta->id,
                'punto_de_venta_id' => $devolucion->punto_de_venta_id,
                'descripcion' => "Devolución {$devolucion->numero}",
                'fecha' => $devolucion->fecha,
            ]
        );
    }

    /**
     * Cobro hecho en una caja. Idempotente por uuid.
     *
     * @param  array{uuid: string, cliente_id: int, importe: float|int|string, medio: string, fecha: string, cajero?: ?string, numero?: ?string}  $cobro
     */
    public function registrarCobroDeCaja(PuntoDeVenta $pdv, array $cobro): string
    {
        if (MovimientoCuentaCorriente::where('uuid', $cobro['uuid'])->exists()) {
            return 'duplicado';
        }

        MovimientoCuentaCorriente::create([
            'uuid' => $cobro['uuid'],
            'cliente_id' => $cobro['cliente_id'],
            'tipo' => 'pago',
            'importe' => -round((float) $cobro['importe'], 2),
            'punto_de_venta_id' => $pdv->id,
            'medio' => $cobro['medio'],
            'descripcion' => trim('Cobro en '.$pdv->nombre.' '.($cobro['numero'] ?? '').(($cobro['cajero'] ?? null) ? " ({$cobro['cajero']})" : '')),
            'fecha' => $cobro['fecha'],
        ]);

        return 'creado';
    }

    /**
     * Pago o ajuste cargado en el Manager. Un ajuste positivo suma deuda; negativo, la baja.
     */
    public function registrarManual(Cliente $cliente, string $tipo, float $importe, ?string $medio, string $descripcion, User $usuario): MovimientoCuentaCorriente
    {
        if (! in_array($tipo, ['pago', 'ajuste'], true)) {
            throw new CuentaCorrienteException('Solo se cargan pagos o ajustes a mano.');
        }

        if ($importe == 0 || ($tipo === 'pago' && $importe < 0)) {
            throw new CuentaCorrienteException('Indicá un importe válido.');
        }

        if ($tipo === 'pago' && ! in_array($medio, self::MEDIOS_DE_COBRO, true)) {
            throw new CuentaCorrienteException('Elegí el medio del pago.');
        }

        if (trim($descripcion) === '') {
            throw new CuentaCorrienteException('Indicá el motivo.');
        }

        return MovimientoCuentaCorriente::create([
            'cliente_id' => $cliente->id,
            'tipo' => $tipo,
            'importe' => $tipo === 'pago' ? -round($importe, 2) : round($importe, 2),
            'user_id' => $usuario->id,
            'medio' => $tipo === 'pago' ? $medio : null,
            'descripcion' => mb_substr(trim($descripcion), 0, 200),
            'fecha' => now(),
        ]);
    }
}
