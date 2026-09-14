<?php

namespace App\Support;

use App\Models\PuntoDeVenta;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Diagnóstico de una caja a partir de lo que informa cada minuto.
 *
 * Las antigüedades de lo que pasa adentro de la caja (último stock, venta sin enviar) se
 * miden contra el `generado_at` del mismo reporte, con el reloj de la caja: un reloj
 * atrasado no dispara alertas falsas.
 */
final class SaludCaja
{
    public const CRITICO = 'critico';

    public const ALERTA = 'alerta';

    public const OK = 'ok';

    public const SIN_DATOS = 'sin_datos';

    public const INACTIVA = 'inactiva';

    /** Minutos sin bajar stock que ya indican un sync trabado (corre cada minuto). */
    private const STOCK_TRABADO_MIN = 10;

    private const VENTA_SIN_ENVIAR_MIN = 15;

    private const FACTURA_SIN_CAE_MIN = 30;

    private const JOBS_ACUMULADOS = 20;

    /**
     * @return array{nivel: string, problemas: array<int, array{nivel: string, texto: string}>}
     */
    public static function evaluar(PuntoDeVenta $pdv, ?string $ultimaVersion = null, ?CarbonInterface $ahora = null): array
    {
        $ahora ??= now();

        // === false: un modelo recién creado no trae el default de la base (activo = 1).
        if ($pdv->activo === false) {
            return ['nivel' => self::INACTIVA, 'problemas' => []];
        }

        if ($pdv->ultima_conexion_at === null) {
            return ['nivel' => self::SIN_DATOS, 'problemas' => [['nivel' => self::SIN_DATOS, 'texto' => 'Nunca se conectó']]];
        }

        $problemas = [];
        $minutosSinConexion = $pdv->ultima_conexion_at->diffInMinutes($ahora);

        if ($minutosSinConexion > 10) {
            $problemas[] = [self::CRITICO, 'Sin conexión hace '.self::duracion($pdv->ultima_conexion_at, $ahora)];
        } elseif ($minutosSinConexion > 3) {
            $problemas[] = [self::ALERTA, 'Sin conexión hace '.self::duracion($pdv->ultima_conexion_at, $ahora)];
        }

        if ($ultimaVersion && $pdv->version_pos && version_compare($pdv->version_pos, $ultimaVersion, '<')) {
            $problemas[] = [self::ALERTA, "Versión {$pdv->version_pos} desactualizada (última {$ultimaVersion})"];
        }

        $estado = $pdv->estado_caja;

        if (! $estado || ! $pdv->estado_reportado_at) {
            $problemas[] = [self::SIN_DATOS, 'No informa su estado (versión anterior a la 1.4.2)'];

            return self::resultado($problemas);
        }

        if ($pdv->estado_reportado_at->diffInMinutes($ahora) > 5 && $minutosSinConexion <= 10) {
            $problemas[] = [self::ALERTA, 'Dejó de informar su estado hace '.self::duracion($pdv->estado_reportado_at, $ahora)];
        }

        $generado = self::fecha($estado['generado_at'] ?? null) ?? $pdv->estado_reportado_at;
        $stock = self::fecha($estado['ultima_sincronizacion_stock'] ?? null);

        if (! empty($estado['catalogo_pendiente'])) {
            $problemas[] = [self::ALERTA, 'Todavía está bajando el catálogo: no envía ventas'];
        } elseif ($stock === null) {
            $problemas[] = [self::CRITICO, 'Nunca bajó el stock'];
        } elseif ($stock->diffInMinutes($generado) > self::STOCK_TRABADO_MIN) {
            $problemas[] = [self::CRITICO, 'No baja stock hace '.self::duracion($stock, $generado).' (sync trabado)'];
        }

        $ventas = (int) ($estado['ventas_pendientes'] ?? 0);
        $ventaVieja = self::fecha($estado['venta_pendiente_mas_vieja'] ?? null);

        if ($ventas > 0 && $ventaVieja && $ventaVieja->diffInMinutes($generado) > self::VENTA_SIN_ENVIAR_MIN) {
            $problemas[] = [self::CRITICO, "{$ventas} venta(s) sin enviar, la más vieja de hace ".self::duracion($ventaVieja, $generado)];
        }

        if (($fallidos = (int) ($estado['jobs_fallidos'] ?? 0)) > 0) {
            $problemas[] = [self::ALERTA, "{$fallidos} envío(s) fallidos en la cola de la caja"];
        }

        if (($enCola = (int) ($estado['jobs_en_cola'] ?? 0)) > self::JOBS_ACUMULADOS) {
            $problemas[] = [self::ALERTA, "{$enCola} trabajos acumulados en la cola (¿el worker está caído?)"];
        }

        $facturas = (int) ($estado['facturas_pendientes'] ?? 0);
        $facturaVieja = self::fecha($estado['factura_pendiente_mas_vieja'] ?? null);

        if ($facturas > 0 && $facturaVieja && $facturaVieja->diffInMinutes($generado) > self::FACTURA_SIN_CAE_MIN) {
            $problemas[] = [self::ALERTA, "{$facturas} factura(s) sin CAE hace más de ".self::FACTURA_SIN_CAE_MIN.' minutos'];
        }

        if (($rechazadas = (int) ($estado['facturas_rechazadas'] ?? 0)) > 0) {
            $problemas[] = [self::ALERTA, "{$rechazadas} factura(s) rechazada(s)"];
        }

        return self::resultado($problemas);
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $problemas
     * @return array{nivel: string, problemas: array<int, array{nivel: string, texto: string}>}
     */
    private static function resultado(array $problemas): array
    {
        $niveles = array_column($problemas, 0);

        $nivel = match (true) {
            in_array(self::CRITICO, $niveles, true) => self::CRITICO,
            in_array(self::ALERTA, $niveles, true) => self::ALERTA,
            in_array(self::SIN_DATOS, $niveles, true) => self::SIN_DATOS,
            default => self::OK,
        };

        return [
            'nivel' => $nivel,
            'problemas' => array_map(fn ($p) => ['nivel' => $p[0], 'texto' => $p[1]], $problemas),
        ];
    }

    /** "25 min", "11 h", "3 días": sin depender del locale de la app (está en inglés). */
    private static function duracion(CarbonInterface $desde, CarbonInterface $hasta): string
    {
        $minutos = (int) floor(abs($desde->diffInMinutes($hasta)));

        return match (true) {
            $minutos < 60 => "{$minutos} min",
            $minutos < 48 * 60 => intdiv($minutos, 60).' h',
            default => intdiv($minutos, 24 * 60).' días',
        };
    }

    private static function fecha(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }

        try {
            return Carbon::parse($valor);
        } catch (\Throwable) {
            return null;
        }
    }
}
