<?php

namespace App\Http\Controllers\Sucursales;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AjusteStockController extends Controller
{
    public function plantilla(Request $request): StreamedResponse
    {
        $sucursalId = $request->integer('sucursal_id') ?: null;
        $sucursal = $sucursalId ? Sucursal::find($sucursalId) : null;
        $nombreSucursal = $sucursal?->nombre ?? 'Todas las sucursales';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ajuste de Stock');

        // — Encabezados —
        $headers = ['codigo_interno', 'codigo_barras', 'nombre', 'nueva_cantidad', 'stock_actual (referencia)'];
        foreach ($headers as $col => $label) {
            $cell = chr(65 + $col).'1';
            $sheet->setCellValue($cell, $label);
        }

        // Estilo encabezados
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Columna D (nueva_cantidad) en amarillo para destacarla
        $sheet->getStyle('D1')->getFont()->getColor()->setRGB('1D4ED8');
        $sheet->getStyle('D1')->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FDE047');
        $sheet->getStyle('D1')->getFont()->setBold(true);
        $sheet->getStyle('D1')->getFont()->getColor()->setRGB('000000');

        // Columna E (referencia) en gris
        $sheet->getStyle('E1')->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('9CA3AF');

        // — Datos —
        $stockMap = $sucursalId
            ? StockSucursal::where('sucursal_id', $sucursalId)->pluck('cantidad', 'product_id')
            : collect();

        $productos = Product::whereNull('deleted_at')
            ->where('es_vendible', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo_interno', 'codigo_barras', 'nombre']);

        $row = 2;
        foreach ($productos as $producto) {
            $sheet->setCellValue("A{$row}", $producto->codigo_interno ?? '');
            $sheet->setCellValue("B{$row}", $producto->codigo_barras ?? '');
            $sheet->setCellValue("C{$row}", $producto->nombre);
            $sheet->setCellValue("D{$row}", '');
            $sheet->setCellValue("E{$row}", (int) ($stockMap[$producto->id] ?? 0));
            $row++;
        }

        // Anchos de columna
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(42);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(26);

        // Proteger columna E (solo informativa)
        $sheet->getStyle('E2:E'.(max($row - 1, 2)))->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
            'font' => ['color' => ['rgb' => '6B7280']],
        ]);

        // Instrucciones en celda G1
        $sheet->setCellValue('G1', "INSTRUCCIONES: Complete la columna D 'nueva_cantidad' con el stock físico contado. La columna E es solo referencia. Sucursal: {$nombreSucursal}");
        $sheet->getStyle('G1')->getFont()->setItalic(true)->setColor((new Font)->getColor()->setRGB('6B7280'));

        $filename = 'ajuste_stock_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
