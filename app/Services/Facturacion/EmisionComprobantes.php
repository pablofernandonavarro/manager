<?php

namespace App\Services\Facturacion;

use App\Exceptions\AfipException;
use App\Exceptions\AfipSinRespuestaException;
use App\Models\Comprobante;
use App\Models\ConfiguracionFiscal;
use App\Models\Devolucion;
use App\Models\PuntoDeVenta;
use App\Models\Venta;
use App\Services\Afip\Wsfe;
use App\Support\Cuit;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Arma los comprobantes de las ventas y devoluciones de las cajas y los autoriza en AFIP.
 *
 * La numeración es de AFIP (último autorizado + 1) y se serializa con un lock por punto de
 * venta y tipo. Antes de pedir un número se resuelven las reservas que quedaron de intentos
 * sin respuesta: se consulta en AFIP si ese comprobante existe, así un corte de red nunca
 * termina en dos facturas por la misma venta ni en un salto de numeración.
 */
class EmisionComprobantes
{
    public function __construct(
        private readonly Wsfe $wsfe,
    ) {}

    /**
     * Si la facturación está activa y la sucursal tiene punto de venta, deja la factura
     * pendiente. Con datos de receptor inválidos queda rechazada (visible en el Manager)
     * en vez de frenar la sincronización de la venta.
     *
     * @param  array{doc_tipo?: int|string|null, doc_nro?: ?string, nombre?: ?string, condicion_iva?: int|string|null}  $receptor
     */
    public function crearParaVenta(Venta $venta, PuntoDeVenta $pdv, array $receptor): ?Comprobante
    {
        $config = ConfiguracionFiscal::actual();
        $puntoVenta = $pdv->sucursal?->afip_punto_venta;

        if (! $config->facturacion_activa || ! $puntoVenta) {
            return null;
        }

        if ($existente = Comprobante::where('venta_id', $venta->id)->first()) {
            return $existente;
        }

        [$receptorNormalizado, $errorReceptor] = $this->normalizarReceptor($receptor);
        $tipo = self::tipoFactura($config->condicion_iva, $receptorNormalizado['condicion_iva']);

        if ($errorReceptor === null && in_array($tipo, [1], true) && $receptorNormalizado['doc_tipo'] !== 80) {
            $errorReceptor = 'Una factura A necesita el CUIT del cliente.';
        }

        $venta->loadMissing('items.product');

        $lineas = $venta->items->map(fn ($item) => [
            'centavos' => self::centavos($item->subtotal),
            'iva' => (float) ($item->product?->iva ?? 21),
        ])->all();

        $comprobante = Comprobante::create([
            'venta_id' => $venta->id,
            'sucursal_id' => $venta->sucursal_id,
            'punto_de_venta_id' => $pdv->id,
            'entorno' => $config->entorno,
            'afip_punto_venta' => $puntoVenta,
            'tipo' => $tipo,
            'fecha' => self::hoy(),
            ...$this->columnasReceptor($receptorNormalizado),
            ...self::importes($lineas, self::centavos($venta->total), $tipo),
            'estado' => $errorReceptor ? 'rechazado' : 'pendiente',
            'error' => $errorReceptor,
        ]);

        return $comprobante;
    }

    /**
     * Nota de crédito por una devolución de una venta facturada. Queda pendiente aunque la
     * factura todavía no tenga CAE: se autoriza después que ella.
     */
    public function crearNotaDeCredito(Devolucion $devolucion): ?Comprobante
    {
        $factura = Comprobante::whereHas('venta', fn ($q) => $q->where('uuid', $devolucion->venta_uuid))
            ->whereNull('devolucion_id')
            ->first();

        if (! $factura || $factura->estado === 'rechazado') {
            return null;
        }

        if ($existente = Comprobante::where('devolucion_id', $devolucion->id)->first()) {
            return $existente;
        }

        $devolucion->loadMissing('items.product');

        $lineas = $devolucion->items->map(fn ($item) => [
            'centavos' => self::centavos($item->importe),
            'iva' => (float) ($item->product?->iva ?? 21),
        ])->all();

        $tipo = Comprobante::NOTA_DE_CREDITO_DE[$factura->tipo];

        return Comprobante::create([
            'devolucion_id' => $devolucion->id,
            'comprobante_asociado_id' => $factura->id,
            'sucursal_id' => $factura->sucursal_id,
            'punto_de_venta_id' => $devolucion->punto_de_venta_id,
            'entorno' => $factura->entorno,
            'afip_punto_venta' => $factura->afip_punto_venta,
            'tipo' => $tipo,
            'fecha' => self::hoy(),
            'receptor_doc_tipo' => $factura->receptor_doc_tipo,
            'receptor_doc_nro' => $factura->receptor_doc_nro,
            'receptor_nombre' => $factura->receptor_nombre,
            'receptor_condicion_iva' => $factura->receptor_condicion_iva,
            ...self::importes($lineas, self::centavos($devolucion->total), $tipo),
            'estado' => 'pendiente',
        ]);
    }

    /**
     * Pide el CAE. Lanza AfipException si hay que reintentar más tarde (sin conexión,
     * factura original sin autorizar, otro proceso numerando); un rechazo de AFIP no se
     * reintenta y queda en el comprobante.
     */
    public function autorizar(Comprobante $comprobante): Comprobante
    {
        $comprobante->refresh();

        if ($comprobante->estado !== 'pendiente') {
            return $comprobante;
        }

        $config = ConfiguracionFiscal::actual();

        if (! $config->facturacion_activa) {
            $this->anotarError($comprobante, 'La facturación electrónica está desactivada en el Manager.');

            throw new AfipException($comprobante->error);
        }

        if ($config->entorno !== $comprobante->entorno) {
            $comprobante->update([
                'estado' => 'rechazado',
                'error' => "Se generó en {$comprobante->entorno} y el emisor ahora está en {$config->entorno}: no se autoriza.",
            ]);

            return $comprobante;
        }

        if ($comprobante->comprobante_asociado_id && ! $comprobante->asociado?->estaAutorizado()) {
            $this->anotarError($comprobante, 'Espera a que se autorice la factura original.');

            throw new AfipException($comprobante->error);
        }

        $clave = "afip-numeracion:{$comprobante->entorno}:{$comprobante->afip_punto_venta}:{$comprobante->tipo}";

        try {
            return Cache::lock($clave, 120)->block(60, fn () => $this->autorizarConNumeracionTomada($config, $comprobante));
        } catch (LockTimeoutException) {
            throw new AfipException('Otro proceso está numerando comprobantes de este punto de venta. Se reintenta.');
        }
    }

    private function autorizarConNumeracionTomada(ConfiguracionFiscal $config, Comprobante $comprobante): Comprobante
    {
        $comprobante->refresh();

        if ($comprobante->estado !== 'pendiente') {
            return $comprobante;
        }

        $comprobante->increment('intentos');

        try {
            $this->resolverReservas($config, $comprobante);

            if ($comprobante->refresh()->estado !== 'pendiente') {
                return $comprobante;
            }

            $numero = $this->wsfe->ultimoAutorizado($config, $comprobante->afip_punto_venta, $comprobante->tipo) + 1;

            // Se guarda antes de llamar: si la respuesta se pierde, este número se consulta
            // en el próximo intento.
            $comprobante->update(['numero' => $numero, 'fecha' => self::hoy(), 'error' => null]);

            $respuesta = $this->wsfe->solicitarCae($config, $comprobante);
        } catch (AfipSinRespuestaException $e) {
            $this->anotarError($comprobante, $e->getMessage());

            throw $e;
        } catch (AfipException $e) {
            // AFIP contestó con un error: el número no se usó.
            $comprobante->update(['numero' => null, 'error' => $e->getMessage()]);

            throw $e;
        }

        if ($respuesta['resultado'] === 'A' && $respuesta['cae']) {
            $comprobante->update([
                'estado' => 'autorizado',
                'cae' => $respuesta['cae'],
                'cae_vencimiento' => $respuesta['cae_vencimiento'],
                'observaciones' => $respuesta['observaciones'] ?: null,
                'error' => null,
                'autorizado_at' => now(),
            ]);

            return $comprobante;
        }

        $comprobante->update([
            'estado' => 'rechazado',
            'numero' => null,
            'observaciones' => $respuesta['observaciones'] ?: null,
            'error' => implode(' | ', [...$respuesta['errores'], ...$respuesta['observaciones']]) ?: 'AFIP rechazó el comprobante.',
        ]);

        return $comprobante;
    }

    /**
     * Comprobantes de este punto de venta y tipo con número reservado y sin respuesta de
     * AFIP: si AFIP lo tiene, quedó autorizado; si no, se libera el número.
     */
    private function resolverReservas(ConfiguracionFiscal $config, Comprobante $actual): void
    {
        $reservados = Comprobante::where('estado', 'pendiente')
            ->where('entorno', $actual->entorno)
            ->where('afip_punto_venta', $actual->afip_punto_venta)
            ->where('tipo', $actual->tipo)
            ->whereNotNull('numero')
            ->orderBy('numero')
            ->get();

        foreach ($reservados as $reservado) {
            $emitido = $this->wsfe->consultar($config, $reservado->afip_punto_venta, $reservado->tipo, $reservado->numero);

            if ($emitido
                && abs($emitido['importe_total'] - (float) $reservado->importe_total) < 0.005
                && ltrim($emitido['doc_nro'], '0') === ltrim((string) $reservado->receptor_doc_nro, '0')) {
                $reservado->update([
                    'estado' => 'autorizado',
                    'cae' => $emitido['cae'],
                    'cae_vencimiento' => $emitido['cae_vencimiento'],
                    'fecha' => $emitido['fecha'] ?? $reservado->fecha,
                    'error' => null,
                    'autorizado_at' => now(),
                ]);

                continue;
            }

            $reservado->update(['numero' => null]);
        }
    }

    private function anotarError(Comprobante $comprobante, string $error): void
    {
        $comprobante->update(['error' => $error]);
    }

    /**
     * Emisor responsable inscripto: A a inscriptos y monotributistas, B al resto.
     * Monotributo o exento: siempre C.
     */
    public static function tipoFactura(?string $condicionEmisor, int $condicionReceptor): int
    {
        if ($condicionEmisor !== 'responsable_inscripto') {
            return 11;
        }

        return in_array($condicionReceptor, [1, 6], true) ? 1 : 6;
    }

    /**
     * Reparte el total entre las líneas (los descuentos de la venta van prorrateados) y
     * separa neto e IVA por alícuota. Todo en centavos para que cierre exacto con el total.
     *
     * @param  array<int, array{centavos: int, iva: float}>  $lineas
     * @return array{importe_total: float, importe_neto: float, importe_iva: float, alicuotas: array<int, array{id: int, porcentaje: float, base: float, importe: float}>}
     */
    public static function importes(array $lineas, int $totalCentavos, int $tipo): array
    {
        $esC = in_array($tipo, [11, 13], true);

        if ($esC) {
            return [
                'importe_total' => self::pesos($totalCentavos),
                'importe_neto' => self::pesos($totalCentavos),
                'importe_iva' => 0.0,
                'alicuotas' => [],
            ];
        }

        $bruto = array_sum(array_column($lineas, 'centavos'));
        $porAlicuota = [];
        $asignado = 0;

        foreach (array_values($lineas) as $i => $linea) {
            $parte = $i === count($lineas) - 1
                ? $totalCentavos - $asignado
                : ($bruto > 0 ? intdiv($linea['centavos'] * $totalCentavos, $bruto) : 0);

            $asignado += $parte;
            $clave = (string) (float) $linea['iva'];
            $porAlicuota[$clave] = ($porAlicuota[$clave] ?? 0) + $parte;
        }

        $alicuotas = [];
        $neto = $iva = 0;

        foreach ($porAlicuota as $porcentaje => $centavos) {
            $id = Comprobante::ALICUOTAS_IVA[$porcentaje] ?? 5;
            $base = (int) round($centavos / (1 + (float) $porcentaje / 100));
            $impuesto = $centavos - $base;

            $alicuotas[] = ['id' => $id, 'porcentaje' => (float) $porcentaje, 'base' => self::pesos($base), 'importe' => self::pesos($impuesto)];
            $neto += $base;
            $iva += $impuesto;
        }

        return [
            'importe_total' => self::pesos($totalCentavos),
            'importe_neto' => self::pesos($neto),
            'importe_iva' => self::pesos($iva),
            'alicuotas' => $alicuotas,
        ];
    }

    /**
     * @param  array<string, mixed>  $receptor
     * @return array{0: array{doc_tipo: int, doc_nro: string, nombre: ?string, condicion_iva: int}, 1: ?string}
     */
    private function normalizarReceptor(array $receptor): array
    {
        $condicion = (int) ($receptor['condicion_iva'] ?? 5);
        $docTipo = (int) ($receptor['doc_tipo'] ?? 99);
        $docNro = preg_replace('/\D/', '', (string) ($receptor['doc_nro'] ?? ''));
        $nombre = trim((string) ($receptor['nombre'] ?? '')) ?: null;
        $error = null;

        if (! array_key_exists($condicion, Comprobante::CONDICIONES_IVA)) {
            [$condicion, $error] = [5, 'Condición frente al IVA del cliente inválida.'];
        }

        if (! array_key_exists($docTipo, Comprobante::DOC_TIPOS)) {
            [$docTipo, $error] = [99, 'Tipo de documento del cliente inválido.'];
        }

        if (in_array($docTipo, [80, 86], true) && ! Cuit::valido($docNro)) {
            $error = 'El CUIT/CUIL del cliente no es válido.';
        }

        if ($docTipo === 96 && ! preg_match('/^\d{6,8}$/', $docNro)) {
            $error = 'El DNI del cliente no es válido.';
        }

        if ($docTipo === 99) {
            $docNro = '0';
        }

        if ($condicion !== 5 && ! in_array($docTipo, [80, 86], true)) {
            $error ??= 'Un cliente que no es consumidor final necesita CUIT.';
        }

        return [['doc_tipo' => $docTipo, 'doc_nro' => $docNro, 'nombre' => $nombre ? mb_substr($nombre, 0, 150) : null, 'condicion_iva' => $condicion], $error];
    }

    /** @return array<string, mixed> */
    private function columnasReceptor(array $receptor): array
    {
        return [
            'receptor_doc_tipo' => $receptor['doc_tipo'],
            'receptor_doc_nro' => $receptor['doc_nro'],
            'receptor_nombre' => $receptor['nombre'],
            'receptor_condicion_iva' => $receptor['condicion_iva'],
        ];
    }

    private static function pesos(int $centavos): float
    {
        return $centavos / 100.0;
    }

    private static function centavos(mixed $pesos): int
    {
        return (int) round((float) $pesos * 100);
    }

    /** Fecha del comprobante: el día de hoy en Argentina (AFIP no acepta fechas de días atrás). */
    private static function hoy(): string
    {
        return now(config('app.display_timezone'))->toDateString();
    }
}
