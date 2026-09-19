<?php

namespace App\Services;

use App\Models\Devolucion;
use App\Models\PagoVenta;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reportes de ventas para el Manager: indicadores, medios de pago, cajeros, sucursales,
 * evolución diaria, conciliación de tarjetas y promociones.
 *
 * Los filtros de fecha son días LOCALES (config app.display_timezone) y se convierten a un
 * rango UTC, que es como se guarda todo. Sin esto, las ventas de la noche (después de las
 * 21 en Argentina) caían en el día siguiente.
 *
 * @phpstan-type Filtros array{desde: string, hasta: string, sucursal_id?: ?int, punto_de_venta_id?: ?int, cajero?: ?string}
 */
class ReporteVentasService
{
    /**
     * @param  Filtros  $filtros
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function rangoUtc(array $filtros): array
    {
        $zona = config('app.display_timezone');

        return [
            Carbon::parse($filtros['desde'], $zona)->startOfDay()->utc(),
            Carbon::parse($filtros['hasta'], $zona)->endOfDay()->utc(),
        ];
    }

    /** @param Filtros $filtros */
    public function ventas(array $filtros): Builder
    {
        [$desde, $hasta] = self::rangoUtc($filtros);

        return Venta::query()
            ->whereBetween('ventas.fecha', [$desde, $hasta])
            ->when($filtros['sucursal_id'] ?? null, fn ($q, $id) => $q->where('ventas.sucursal_id', $id))
            ->when($filtros['punto_de_venta_id'] ?? null, fn ($q, $id) => $q->where('ventas.punto_de_venta_id', $id))
            ->when($filtros['cajero'] ?? null, fn ($q, $cajero) => $q->where('ventas.cajero', $cajero));
    }

    /** @param Filtros $filtros */
    public function devoluciones(array $filtros): Builder
    {
        [$desde, $hasta] = self::rangoUtc($filtros);

        return Devolucion::query()
            ->whereBetween('devoluciones.fecha', [$desde, $hasta])
            ->when($filtros['sucursal_id'] ?? null, fn ($q, $id) => $q->where('devoluciones.sucursal_id', $id))
            ->when($filtros['punto_de_venta_id'] ?? null, fn ($q, $id) => $q->where('devoluciones.punto_de_venta_id', $id))
            // Una devolución no guarda cajero: se filtra por el cajero de la venta original.
            ->when($filtros['cajero'] ?? null, fn ($q, $cajero) => $q->whereHas('venta', fn ($v) => $v->where('cajero', $cajero)));
    }

    /**
     * @param  Filtros  $filtros
     * @return array{cantidad: int, unidades: int, bruto: float, descuentos: float, descuentos_manuales: float, total: float, devoluciones: float, neto: float, ticket_promedio: float}
     */
    public function indicadores(array $filtros): array
    {
        $ventas = $this->ventas($filtros)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(subtotal),0) as bruto, COALESCE(SUM(descuento),0) as descuentos, COALESCE(SUM(descuento_manual),0) as manuales, COALESCE(SUM(total),0) as total')
            ->first();

        $unidades = (int) DB::table('detalle_ventas')
            ->joinSub($this->ventas($filtros)->select('ventas.id'), 'v', 'v.id', '=', 'detalle_ventas.venta_id')
            ->sum('detalle_ventas.cantidad');

        $devuelto = (float) $this->devoluciones($filtros)->sum('total');
        $cantidad = (int) $ventas->cantidad;
        $total = (float) $ventas->total;

        return [
            'cantidad' => $cantidad,
            'unidades' => $unidades,
            'bruto' => round((float) $ventas->bruto, 2),
            'descuentos' => round((float) $ventas->descuentos, 2),
            'descuentos_manuales' => round((float) $ventas->manuales, 2),
            'total' => round($total, 2),
            'devoluciones' => round($devuelto, 2),
            'neto' => round($total - $devuelto, 2),
            'ticket_promedio' => $cantidad > 0 ? round($total / $cantidad, 2) : 0.0,
        ];
    }

    /**
     * Ventas de cajas viejas que no mandaban el detalle del cobro aparecen como "sin_detalle",
     * para que la suma por medio cierre contra el total.
     *
     * @param  Filtros  $filtros
     * @return array<int, array{medio: string, cantidad: int, importe: float}>
     */
    public function porMedioDePago(array $filtros): array
    {
        $conPagos = DB::table('pagos_venta')
            ->joinSub($this->ventas($filtros)->select('ventas.id'), 'v', 'v.id', '=', 'pagos_venta.venta_id')
            ->groupBy('pagos_venta.medio')
            ->selectRaw('pagos_venta.medio as medio, COUNT(*) as cantidad, SUM(pagos_venta.importe) as importe')
            ->orderByDesc('importe')
            ->get()
            ->map(fn ($f) => ['medio' => $f->medio, 'cantidad' => (int) $f->cantidad, 'importe' => round((float) $f->importe, 2)])
            ->all();

        $sinDetalle = $this->ventas($filtros)->whereDoesntHave('pagos')
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total),0) as importe')->first();

        if ((int) $sinDetalle->cantidad > 0) {
            $conPagos[] = ['medio' => 'sin_detalle', 'cantidad' => (int) $sinDetalle->cantidad, 'importe' => round((float) $sinDetalle->importe, 2)];
        }

        return $conPagos;
    }

    /**
     * @param  Filtros  $filtros
     * @return array<int, array{cajero: string, cantidad: int, total: float, ticket_promedio: float, descuentos_manuales: float, devoluciones: float}>
     */
    public function porCajero(array $filtros): array
    {
        $devoluciones = $this->devoluciones($filtros)
            ->join('ventas', 'ventas.uuid', '=', 'devoluciones.venta_uuid')
            ->groupBy('ventas.cajero')
            ->selectRaw('ventas.cajero as cajero, SUM(devoluciones.total) as total')
            ->pluck('total', 'cajero');

        return $this->ventas($filtros)
            ->groupBy('ventas.cajero')
            ->selectRaw('ventas.cajero as cajero, COUNT(*) as cantidad, SUM(total) as total, SUM(descuento_manual) as manuales')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($f) => [
                'cajero' => $f->cajero ?? 'Sin cajero',
                'cantidad' => (int) $f->cantidad,
                'total' => round((float) $f->total, 2),
                'ticket_promedio' => $f->cantidad > 0 ? round($f->total / $f->cantidad, 2) : 0.0,
                'descuentos_manuales' => round((float) $f->manuales, 2),
                'devoluciones' => round((float) ($devoluciones[$f->cajero] ?? 0), 2),
            ])
            ->all();
    }

    /**
     * @param  Filtros  $filtros
     * @return array<int, array{sucursal: string, caja: string, cantidad: int, total: float}>
     */
    public function porCaja(array $filtros): array
    {
        return $this->ventas($filtros)
            ->join('puntos_de_venta', 'puntos_de_venta.id', '=', 'ventas.punto_de_venta_id')
            ->join('sucursales', 'sucursales.id', '=', 'ventas.sucursal_id')
            ->groupBy('sucursales.nombre', 'puntos_de_venta.nombre')
            ->selectRaw('sucursales.nombre as sucursal, puntos_de_venta.nombre as caja, COUNT(*) as cantidad, SUM(ventas.total) as total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($f) => ['sucursal' => $f->sucursal, 'caja' => $f->caja, 'cantidad' => (int) $f->cantidad, 'total' => round((float) $f->total, 2)])
            ->all();
    }

    /**
     * Agrupado por día local, en SQL: hidratar cada venta como modelo para agruparla en PHP
     * tardaba segundos con decenas de miles de ventas (el tablero lo pide en cada refresco).
     *
     * Se convierte con el desplazamiento numérico de la zona (`-03:00`), que MySQL resuelve sin
     * las tablas de zonas horarias. Vale mientras la zona no cambie de desplazamiento dentro del
     * rango pedido: Argentina no tiene horario de verano. Si volviera, habría que agrupar por
     * tramos.
     *
     * @param  Filtros  $filtros
     * @return array<int, array{dia: string, cantidad: int, total: float}>
     */
    public function porDia(array $filtros): array
    {
        $desplazamiento = Carbon::now(config('app.display_timezone'))->format('P');

        return $this->ventas($filtros)->toBase()
            ->selectRaw("DATE(CONVERT_TZ(ventas.fecha, '+00:00', ?)) as dia, COUNT(*) as cantidad, SUM(ventas.total) as total", [$desplazamiento])
            ->groupBy('dia')
            ->orderBy('dia')
            ->get()
            ->map(fn ($f) => ['dia' => $f->dia, 'cantidad' => (int) $f->cantidad, 'total' => round((float) $f->total, 2)])
            ->all();
    }

    /**
     * Para conciliar contra las liquidaciones de las tarjetas.
     *
     * @param  Filtros  $filtros
     * @return array<int, array{medio: string, tarjeta: ?string, banco: ?string, cuotas: ?int, cantidad: int, importe: float}>
     */
    public function tarjetas(array $filtros): array
    {
        return DB::table('pagos_venta')
            ->joinSub($this->ventas($filtros)->select('ventas.id'), 'v', 'v.id', '=', 'pagos_venta.venta_id')
            ->whereIn('pagos_venta.medio', ['debito', 'credito', 'qr'])
            ->groupBy('pagos_venta.medio', 'pagos_venta.tarjeta', 'pagos_venta.banco', 'pagos_venta.cuotas')
            ->selectRaw('pagos_venta.medio as medio, pagos_venta.tarjeta as tarjeta, pagos_venta.banco as banco, pagos_venta.cuotas as cuotas, COUNT(*) as cantidad, SUM(pagos_venta.importe) as importe')
            ->orderBy('medio')->orderByDesc('importe')
            ->get()
            ->map(fn ($f) => [
                'medio' => $f->medio, 'tarjeta' => $f->tarjeta, 'banco' => $f->banco,
                'cuotas' => $f->cuotas !== null ? (int) $f->cuotas : null,
                'cantidad' => (int) $f->cantidad, 'importe' => round((float) $f->importe, 2),
            ])
            ->all();
    }

    /**
     * @param  Filtros  $filtros
     * @return array<int, array{promocion: string, usos: int, descuento: float, cobrado: float}>
     */
    public function promociones(array $filtros): array
    {
        return DB::table('pagos_venta')
            ->joinSub($this->ventas($filtros)->select('ventas.id'), 'v', 'v.id', '=', 'pagos_venta.venta_id')
            ->whereNotNull('pagos_venta.promocion_nombre')
            ->groupBy('pagos_venta.promocion_nombre')
            ->selectRaw('pagos_venta.promocion_nombre as promocion, COUNT(*) as usos, SUM(pagos_venta.descuento) as descuento, SUM(pagos_venta.importe) as cobrado')
            ->orderByDesc('descuento')
            ->get()
            ->map(fn ($f) => ['promocion' => $f->promocion, 'usos' => (int) $f->usos, 'descuento' => round((float) $f->descuento, 2), 'cobrado' => round((float) $f->cobrado, 2)])
            ->all();
    }

    /** Nombre legible de un medio, incluido el de ventas sin detalle. */
    public static function nombreMedio(string $medio): string
    {
        return $medio === 'sin_detalle' ? 'Sin detalle (caja anterior a 1.1)' : (PagoVenta::MEDIOS[$medio] ?? $medio);
    }
}
