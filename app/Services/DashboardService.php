<?php

namespace App\Services;

use App\Enums\EstadoRemito;
use App\Models\Comprobante;
use App\Models\PuntoDeVenta;
use App\Models\Remito;
use App\Models\StockSucursal;
use App\Models\TurnoCaja;
use App\Models\VersionPos;
use App\Support\SaludCaja;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Datos del tablero de inicio. Las ventas salen de ReporteVentasService (mismos criterios de
 * fecha local y devoluciones que los reportes): el número del tablero y el del reporte
 * tienen que coincidir.
 */
class DashboardService
{
    public function __construct(
        private readonly ReporteVentasService $reportes,
    ) {}

    /**
     * Hoy contra ayer y el mes en curso contra los mismos días del mes anterior.
     *
     * @return array{hoy: array<string, float|int>, ayer: array<string, float|int>, mes: array<string, float|int>, mes_anterior: array<string, float|int>}
     */
    public function ventas(?int $sucursalId, Carbon $hoy): array
    {
        $dia = fn (Carbon $d) => ['desde' => $d->toDateString(), 'hasta' => $d->toDateString(), 'sucursal_id' => $sucursalId];
        $inicioMes = $hoy->copy()->startOfMonth();
        $inicioMesAnterior = $inicioMes->copy()->subMonthNoOverflow();
        $finMesAnterior = $inicioMesAnterior->copy()->addDays($hoy->day - 1)->min($inicioMesAnterior->copy()->endOfMonth());

        return [
            'hoy' => $this->reportes->indicadores($dia($hoy)),
            'ayer' => $this->reportes->indicadores($dia($hoy->copy()->subDay())),
            'mes' => $this->reportes->indicadores(['desde' => $inicioMes->toDateString(), 'hasta' => $hoy->toDateString(), 'sucursal_id' => $sucursalId]),
            'mes_anterior' => $this->reportes->indicadores(['desde' => $inicioMesAnterior->toDateString(), 'hasta' => $finMesAnterior->toDateString(), 'sucursal_id' => $sucursalId]),
        ];
    }

    /**
     * Últimos días con los que no tuvieron ventas en 0, para que el gráfico no salte días.
     *
     * @return list<array{dia: string, etiqueta: string, cantidad: int, total: float}>
     */
    public function evolucion(?int $sucursalId, Carbon $hoy, int $dias = 14): array
    {
        $desde = $hoy->copy()->subDays($dias - 1);
        $porDia = collect($this->reportes->porDia(['desde' => $desde->toDateString(), 'hasta' => $hoy->toDateString(), 'sucursal_id' => $sucursalId]))->keyBy('dia');
        $nombres = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];

        return collect(range(0, $dias - 1))->map(function (int $i) use ($desde, $porDia, $nombres) {
            $fecha = $desde->copy()->addDays($i);
            $dato = $porDia[$fecha->toDateString()] ?? null;

            return [
                'dia' => $fecha->toDateString(),
                'etiqueta' => $nombres[$fecha->dayOfWeek].' '.$fecha->day,
                'cantidad' => $dato['cantidad'] ?? 0,
                'total' => $dato['total'] ?? 0.0,
            ];
        })->all();
    }

    /** @return array{por_caja: array<int, array<string, mixed>>, por_medio: array<int, array<string, mixed>>} */
    public function distribucionDeHoy(?int $sucursalId, Carbon $hoy): array
    {
        $filtros = ['desde' => $hoy->toDateString(), 'hasta' => $hoy->toDateString(), 'sucursal_id' => $sucursalId];

        return [
            'por_caja' => $this->reportes->porCaja($filtros),
            'por_medio' => $this->reportes->porMedioDePago($filtros),
        ];
    }

    /**
     * Lo más vendido del mes, por variante (lo que se vende es la variante).
     *
     * @return list<array{codigo: ?string, nombre: string, unidades: int, importe: float}>
     */
    public function masVendidos(?int $sucursalId, Carbon $hoy, int $limite = 5): array
    {
        $ventas = $this->reportes->ventas(['desde' => $hoy->copy()->startOfMonth()->toDateString(), 'hasta' => $hoy->toDateString(), 'sucursal_id' => $sucursalId]);

        return DB::table('detalle_ventas')
            ->joinSub($ventas->select('ventas.id'), 'v', 'v.id', '=', 'detalle_ventas.venta_id')
            ->join('products', 'products.id', '=', 'detalle_ventas.product_id')
            ->groupBy('products.id', 'products.codigo_interno', 'products.nombre')
            ->selectRaw('products.codigo_interno as codigo, products.nombre as nombre, SUM(detalle_ventas.cantidad) as unidades, SUM(detalle_ventas.subtotal) as importe')
            ->orderByDesc('unidades')
            ->limit($limite)
            ->get()
            ->map(fn ($f) => ['codigo' => $f->codigo, 'nombre' => $f->nombre, 'unidades' => (int) $f->unidades, 'importe' => round((float) $f->importe, 2)])
            ->all();
    }

    /**
     * Cajas instaladas con su salud (misma evaluación que Puntos de venta) y turnos abiertos.
     *
     * @return array{total: int, conteo: array<string, int>, problemas: list<array{caja: string, sucursal: string, nivel: string, texto: string}>, abiertas: list<array{caja: string, sucursal: string, cajero: ?string, desde: Carbon}>}
     */
    public function cajas(?int $sucursalId): array
    {
        // Instalada = canjeó un código alguna vez (mismo criterio que Puntos de venta).
        $cajas = PuntoDeVenta::with('sucursal:id,nombre')
            ->whereHas('codigosInstalacion', fn ($q) => $q->whereNotNull('usado_at'))
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->orderBy('nombre')
            ->get();

        // Contra qué versión se compara cada caja: igual que Puntos de venta.
        $escritorio = json_decode(@file_get_contents(Storage::disk('local')->path('pos-escritorio/pos-escritorio.json')) ?: 'null', true);
        $ultima = ['clasica' => VersionPos::vigente()?->version, 'escritorio' => $escritorio['version'] ?? null];
        $conteo = [SaludCaja::OK => 0, SaludCaja::ALERTA => 0, SaludCaja::CRITICO => 0, SaludCaja::SIN_DATOS => 0, SaludCaja::INACTIVA => 0];
        $problemas = [];

        foreach ($cajas as $caja) {
            $salud = $caja->salud($ultima[$caja->tipo_instalacion] ?? null);
            $conteo[$salud['nivel']] = ($conteo[$salud['nivel']] ?? 0) + 1;

            foreach ($salud['problemas'] as $problema) {
                if ($problema['nivel'] === SaludCaja::CRITICO || $problema['nivel'] === SaludCaja::ALERTA) {
                    $problemas[] = ['caja' => $caja->nombre, 'sucursal' => $caja->sucursal?->nombre ?? '', 'nivel' => $problema['nivel'], 'texto' => $problema['texto']];
                }
            }
        }

        $abiertas = TurnoCaja::with('puntoDeVenta.sucursal:id,nombre')
            ->where('estado', 'abierto')
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->orderBy('abierto_at')
            ->get()
            ->map(fn (TurnoCaja $t) => [
                'caja' => $t->puntoDeVenta?->nombre ?? '—',
                'sucursal' => $t->puntoDeVenta?->sucursal?->nombre ?? '',
                'cajero' => $t->cajero,
                'desde' => $t->abierto_at,
            ])->all();

        return ['total' => $cajas->count(), 'conteo' => $conteo, 'problemas' => $problemas, 'abiertas' => $abiertas];
    }

    /** @return array{cantidad: int, ultimos: list<array{id: int, origen: string, destino: string, remitido_at: ?Carbon}>} */
    public function remitosEnTransito(?int $sucursalId): array
    {
        $query = Remito::with(['sucursalOrigen:id,nombre', 'sucursalDestino:id,nombre'])
            ->where('estado', EstadoRemito::Remitido)
            ->when($sucursalId, fn ($q) => $q->where(fn ($r) => $r->where('sucursal_origen_id', $sucursalId)->orWhere('sucursal_destino_id', $sucursalId)));

        return [
            'cantidad' => (clone $query)->count(),
            'ultimos' => $query->orderByDesc('remitido_at')->limit(4)->get()->map(fn (Remito $r) => [
                'id' => $r->id,
                'origen' => $r->sucursalOrigen?->nombre ?? '—',
                'destino' => $r->sucursalDestino?->nombre ?? '—',
                'remitido_at' => $r->remitido_at,
            ])->all(),
        ];
    }

    /**
     * Variantes y productos vendibles con stock en o por debajo de su stock crítico.
     *
     * @return array{cantidad: int, items: list<array{codigo: ?string, nombre: string, sucursal: string, cantidad: int, critico: int}>}
     */
    public function stockCritico(?int $sucursalId, int $limite = 8): array
    {
        $query = StockSucursal::query()
            ->join('products', 'products.id', '=', 'stock_sucursal.product_id')
            ->join('sucursales', 'sucursales.id', '=', 'stock_sucursal.sucursal_id')
            ->whereNull('products.deleted_at')
            ->where('products.es_vendible', true)
            ->where('products.stock_critico', '>', 0)
            ->whereColumn('stock_sucursal.cantidad', '<=', 'products.stock_critico')
            ->where('sucursales.activo', true)
            ->when($sucursalId, fn ($q) => $q->where('stock_sucursal.sucursal_id', $sucursalId));

        return [
            'cantidad' => (clone $query)->count(),
            'items' => $query->orderBy('stock_sucursal.cantidad')->orderBy('products.nombre')->limit($limite)
                ->get(['products.codigo_interno as codigo', 'products.nombre', 'sucursales.nombre as sucursal', 'stock_sucursal.cantidad', 'products.stock_critico as critico'])
                ->map(fn ($f) => ['codigo' => $f->codigo, 'nombre' => $f->nombre, 'sucursal' => $f->sucursal, 'cantidad' => (int) $f->cantidad, 'critico' => (int) $f->critico])
                ->all(),
        ];
    }

    /** @return array{pendientes: int, rechazados: int} */
    public function facturacion(): array
    {
        return [
            'pendientes' => Comprobante::where('estado', 'pendiente')->count(),
            'rechazados' => Comprobante::where('estado', 'rechazado')->count(),
        ];
    }

    /** @return array{clientes: int, total: float} */
    public function cuentasCorrientes(): array
    {
        $deudores = DB::table('movimientos_cuenta_corriente')
            ->groupBy('cliente_id')
            ->havingRaw('SUM(importe) > 0.004')
            ->selectRaw('cliente_id, SUM(importe) as saldo');

        $fila = DB::query()->fromSub($deudores, 'd')->selectRaw('COUNT(*) as clientes, COALESCE(SUM(saldo), 0) as total')->first();

        return ['clientes' => (int) $fila->clientes, 'total' => round((float) $fila->total, 2)];
    }
}
