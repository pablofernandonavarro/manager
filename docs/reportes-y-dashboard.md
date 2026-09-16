# Dashboard y reportes de ventas

> Extraído de CLAUDE.md el 2026-09-16 para aliviar el context raíz. Ver también CLAUDE.md.

## Dashboard

`App\Livewire\Dashboard` (`/dashboard`) con los datos de `App\Services\DashboardService`; filtro por sucursal (`?sucursal=`) y `wire:poll.60s`. Las ventas salen de `ReporteVentasService` (mismos días locales y devoluciones que el reporte: los números tienen que coincidir). Bloques según permiso: `reportes.ver` (hoy vs. ayer, mes vs. mismos días del mes anterior, 14 días, medios de pago, por caja, más vendidos), `terminales.ver`/`cajas.ver` (salud con `SaludCaja`, cajas "instaladas" = canjearon un código, turnos abiertos), `productos.ver` (stock crítico: vendibles con `stock_sucursal.cantidad <= stock_critico`), `remitos.ver`, `facturacion.ver`, `clientes.gestionar`. En tests, `Carbon::setTestNow` en UTC: con otra zona Carbon lee las fechas de la base en esa zona.

## Reportes de ventas

`App\Services\ReporteVentasService` (pantalla `Reportes\Ventas`, exportación `ExportarVentasController`, permiso `reportes.ver` para admin y supervisor): indicadores (bruto, descuentos, manuales, cobrado, devoluciones, neto, ticket promedio, unidades), por medio de pago, por cajero, por sucursal/caja, por día, tarjetas/QR para conciliar y promociones.

- **Todo se guarda en UTC; los filtros son días locales** (`config('app.display_timezone')`, por defecto Argentina). `rangoUtc()` convierte el día local a un rango UTC: sin eso una venta de las 23:30 caía en el día siguiente (hay test). `porDia()` agrupa en PHP y no con `CONVERT_TZ` porque MySQL necesita las tablas de zonas cargadas.
- Ventas de cajas anteriores a 1.1 no tienen `pagos_venta`: aparecen como medio `sin_detalle` para que la suma por medio cierre contra el total.
- Las devoluciones cuentan por **su** fecha, y el filtro por cajero usa el cajero de la venta original.
- CSV con `;`, coma decimal y BOM UTF-8 para que Excel en español lo abra bien; se genera con `chunk(500)`.
