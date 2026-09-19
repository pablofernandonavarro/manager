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

    /** Está bajando el stock ahora mismo: no es un problema, pero tampoco "todo bien". */
    public const EN_PROCESO = 'en_proceso';

    public const SIN_DATOS = 'sin_datos';

    public const INACTIVA = 'inactiva';

    /** Minutos sin bajar stock que ya indican un sync trabado (corre cada minuto). */
    private const STOCK_TRABADO_MIN = 10;

    private const VENTA_SIN_ENVIAR_MIN = 15;

    private const FACTURA_SIN_CAE_MIN = 30;

    private const JOBS_ACUMULADOS = 20;

    /** Minutos sin pedir la página siguiente a partir de los cuales una descarga de stock ya no se da por en curso. */
    private const DESCARGA_STOCK_SIN_AVANCE_MIN = 3;

    /** Minutos que se espera el próximo reporte de la caja después de que termina de bajar el stock. */
    private const DESCARGA_STOCK_ESPERA_REPORTE_MIN = 3;

    /** Hasta cuántos minutos sin conexión se presume que una caja de versión vieja se está actualizando. */
    private const ACTUALIZACION_SIN_CONEXION_MAX_MIN = 20;

    /** Minutos que se espera el primer reporte de una caja que acaba de reiniciar con otra versión. */
    private const REINICIO_ESPERA_REPORTE_MIN = 10;

    /**
     * Colores y label de cada nivel, para no duplicar este mapping en cada vista que
     * muestra el badge de salud de una caja.
     *
     * @return array{0: string, 1: string, 2: string} [clases del badge, clase del punto, label]
     */
    public static function estilo(string $nivel): array
    {
        return match ($nivel) {
            self::CRITICO => ['bg-red-100 text-red-800', 'bg-red-500', 'Con problemas'],
            self::ALERTA => ['bg-amber-100 text-amber-800', 'bg-amber-500', 'Revisar'],
            self::OK => ['bg-green-100 text-green-800', 'bg-green-500', 'Todo bien'],
            self::EN_PROCESO => ['bg-blue-100 text-blue-800', 'bg-blue-500', 'En proceso'],
            self::SIN_DATOS => ['bg-gray-100 text-gray-600', 'bg-gray-400', 'Sin datos'],
            self::INACTIVA => ['bg-gray-100 text-gray-500', 'bg-gray-300', 'Inactiva'],
            default => ['bg-gray-100 text-gray-600', 'bg-gray-400', $nivel],
        };
    }

    /**
     * @return array{nivel: string, problemas: array<int, array{nivel: string, texto: string}>}
     */
    public static function evaluar(PuntoDeVenta $pdv, ?string $ultimaVersion = null, ?CarbonInterface $ahora = null, ?CarbonInterface $publicadaAt = null): array
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

        $actualizandose = self::actualizandose($pdv, $ultimaVersion, $publicadaAt, $ahora, $minutosSinConexion);

        if ($actualizandose !== null) {
            $problemas[] = [self::EN_PROCESO, $actualizandose];
        } else {
            if ($minutosSinConexion > 10) {
                $problemas[] = [self::CRITICO, 'Sin conexión hace '.self::duracion($pdv->ultima_conexion_at, $ahora)];
            } elseif ($minutosSinConexion > 3) {
                $problemas[] = [self::ALERTA, 'Sin conexión hace '.self::duracion($pdv->ultima_conexion_at, $ahora)];
            }

            if ($ultimaVersion && $pdv->version_pos && version_compare($pdv->version_pos, $ultimaVersion, '<')) {
                $problemas[] = [self::ALERTA, "Versión {$pdv->version_pos} desactualizada (última {$ultimaVersion})"];
            }
        }

        $estado = $pdv->estado_caja;

        if (! $estado || ! $pdv->estado_reportado_at) {
            $problemas[] = [self::SIN_DATOS, 'No informa su estado (versión anterior a la 1.4.2)'];

            return self::resultado($problemas);
        }

        $reinicio = self::reinicioTrasActualizar($pdv, $ahora);

        if ($reinicio !== null) {
            $problemas[] = [self::EN_PROCESO, $reinicio];
        } elseif ($actualizandose === null && $pdv->estado_reportado_at->diffInMinutes($ahora) > 5 && $minutosSinConexion <= 10) {
            $problemas[] = [self::ALERTA, 'Dejó de informar su estado hace '.self::duracion($pdv->estado_reportado_at, $ahora)];
        }

        $generado = self::fecha($estado['generado_at'] ?? null) ?? $pdv->estado_reportado_at;
        $stock = self::fecha($estado['ultima_sincronizacion_stock'] ?? null);

        $descarga = self::descargaDeStock($pdv, $ahora);

        if ($descarga !== null) {
            $problemas[] = [self::EN_PROCESO, $descarga];
        } elseif (! empty($estado['catalogo_pendiente'])) {
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
            in_array(self::EN_PROCESO, $niveles, true) => self::EN_PROCESO,
            in_array(self::SIN_DATOS, $niveles, true) => self::SIN_DATOS,
            default => self::OK,
        };

        return [
            'nivel' => $nivel,
            'problemas' => array_map(fn ($p) => ['nivel' => $p[0], 'texto' => $p[1]], $problemas),
        ];
    }

    /**
     * Texto si una caja de escritorio parece estar reemplazándose por la versión publicada, o null.
     *
     * El escritorio se actualiza cerrando el POS y reemplazando la carpeta, y el Manager no se
     * entera: solo ve una caja que dejó de hablar. Se presume una actualización si tiene una
     * versión anterior a la publicada, estaba conectada después de la publicación y lleva pocos
     * minutos sin conexión. Es una presunción: pasados ACTUALIZACION_SIN_CONEXION_MAX_MIN minutos
     * vuelve a evaluarse como caída.
     */
    private static function actualizandose(PuntoDeVenta $pdv, ?string $ultimaVersion, ?CarbonInterface $publicadaAt, CarbonInterface $ahora, float|int $minutosSinConexion): ?string
    {
        if (
            $pdv->tipo_instalacion !== 'escritorio' || ! $ultimaVersion || ! $publicadaAt || ! $pdv->version_pos
            || version_compare($pdv->version_pos, $ultimaVersion, '>=')
            || $minutosSinConexion <= 3 || $minutosSinConexion > self::ACTUALIZACION_SIN_CONEXION_MAX_MIN
            || $pdv->ultima_conexion_at->lt($publicadaAt)
        ) {
            return null;
        }

        return 'Sin conexión hace '.self::duracion($pdv->ultima_conexion_at, $ahora).": probablemente se está actualizando a {$ultimaVersion}";
    }

    /**
     * Texto si la caja acaba de abrir con otra versión y todavía no mandó su primer reporte, o
     * null. Sin esto, ese rato se veía como "dejó de informar su estado": el último reporte es
     * de antes de cerrarla.
     */
    private static function reinicioTrasActualizar(PuntoDeVenta $pdv, CarbonInterface $ahora): ?string
    {
        $cambio = $pdv->version_actualizada_at;

        if (
            ! $cambio || ! $pdv->version_anterior
            || $cambio->diffInMinutes($ahora) > self::REINICIO_ESPERA_REPORTE_MIN
            || $pdv->estado_reportado_at?->gte($cambio)
        ) {
            return null;
        }

        return "Reinició con la versión {$pdv->version_pos} (antes {$pdv->version_anterior}): esperando su primer reporte";
    }

    /**
     * Texto si la caja está bajando el stock, o null si no. El Manager la ve pedir las páginas
     * (`SyncController::stock`), así que no depende de lo que ella informe: la caja anota su
     * última sincronización recién al terminar, y hasta entonces parecería trabada.
     *
     * Solo cuenta si la última página se pidió hace poco: una caja que se apaga o pierde la red
     * a mitad de la descarga vuelve a evaluarse como trabada. Al terminar hay una espera corta
     * hasta que la caja informa el stock nuevo (reporta cada minuto).
     */
    private static function descargaDeStock(PuntoDeVenta $pdv, CarbonInterface $ahora): ?string
    {
        $iniciada = $pdv->stock_descarga_iniciada_at;
        $avance = $pdv->stock_descarga_avance_at;
        $terminada = $pdv->stock_descarga_terminada_at;

        if (! $iniciada || ! $avance) {
            return null;
        }

        if ($terminada === null) {
            return $avance->diffInMinutes($ahora) <= self::DESCARGA_STOCK_SIN_AVANCE_MIN
                ? 'Descargando stock desde hace '.self::duracion($iniciada, $ahora)
                : null;
        }

        $sinReportarAun = ! $pdv->estado_reportado_at || $pdv->estado_reportado_at->lt($terminada);

        return $sinReportarAun && $terminada->diffInMinutes($ahora) <= self::DESCARGA_STOCK_ESPERA_REPORTE_MIN
            ? 'Terminando de actualizar el stock'
            : null;
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
