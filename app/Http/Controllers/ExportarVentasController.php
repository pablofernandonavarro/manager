<?php

namespace App\Http\Controllers;

use App\Models\PagoVenta;
use App\Services\ReporteVentasService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta las ventas del período en CSV (una fila por venta, con el cobro resumido),
 * con los mismos filtros que la pantalla de reportes.
 */
class ExportarVentasController extends Controller
{
    public function __invoke(Request $request, ReporteVentasService $reportes): StreamedResponse
    {
        $datos = $request->validate([
            'desde' => 'required|date_format:Y-m-d',
            'hasta' => 'required|date_format:Y-m-d|after_or_equal:desde',
            'sucursal' => 'nullable|integer',
            'caja' => 'nullable|integer',
            'cajero' => 'nullable|string|max:100',
        ]);

        $filtros = [
            'desde' => $datos['desde'],
            'hasta' => $datos['hasta'],
            'sucursal_id' => $datos['sucursal'] ?? null,
            'punto_de_venta_id' => $datos['caja'] ?? null,
            'cajero' => $datos['cajero'] ?? null,
        ];

        $zona = config('app.display_timezone');
        $nombre = "ventas_{$filtros['desde']}_{$filtros['hasta']}.csv";

        return response()->streamDownload(function () use ($reportes, $filtros, $zona) {
            $salida = fopen('php://output', 'w');
            // BOM para que Excel abra bien los acentos.
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Fecha', 'Número', 'Sucursal', 'Caja', 'Cajero', 'Cliente', 'Documento', 'Subtotal', 'Descuento', 'Desc. manual', 'Autorizó', 'Total', 'Devuelto', 'Cobro'], ';');

            $reportes->ventas($filtros)
                ->with(['sucursal:id,nombre', 'puntoDeVenta:id,nombre', 'pagos'])
                ->withSum('devoluciones as devuelto', 'total')
                ->orderBy('fecha')
                ->chunk(500, function ($ventas) use ($salida, $zona) {
                    foreach ($ventas as $v) {
                        fputcsv($salida, [
                            $v->fecha->timezone($zona)->format('d/m/Y H:i'),
                            $v->numero_venta,
                            $v->sucursal?->nombre,
                            $v->puntoDeVenta?->nombre,
                            $v->cajero,
                            $v->cliente_nombre,
                            $v->cliente_documento,
                            self::numero($v->subtotal),
                            self::numero($v->descuento),
                            self::numero($v->descuento_manual),
                            $v->descuento_autorizado_por,
                            self::numero($v->total),
                            self::numero($v->devuelto ?? 0),
                            $v->pagos->map(fn ($p) => (PagoVenta::MEDIOS[$p->medio] ?? $p->medio).' '.self::numero($p->importe))->implode(' + '),
                        ], ';');
                    }
                });

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Formato numérico de Excel en español: coma decimal, sin separador de miles. */
    private static function numero(mixed $valor): string
    {
        return number_format((float) $valor, 2, ',', '');
    }
}
