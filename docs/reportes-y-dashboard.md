# Dashboard y reportes de ventas

> Extraído de CLAUDE.md el 2026-09-16 para aliviar el context raíz. Ver también CLAUDE.md.

## Dashboard

`App\Livewire\Dashboard` (`/dashboard`) con los datos de `App\Services\DashboardService`; filtro por sucursal (`?sucursal=`) y `wire:poll.60s`. Livewire baja el poll al 5 % con la pestaña en segundo plano, así que además refresca al volver a la pestaña (`visibilitychange`, si estuvo oculta más de 15 s); no usar `.keep-alive`, que haría renderizar las pestañas ocultas todo el día. La Auditoría de sincronización tiene el poll en un `<div wire:key="poll-N">` aparte: cambiar el intervalo sobre la raíz acumulaba polls. Las ventas salen de `ReporteVentasService` (mismos días locales y devoluciones que el reporte: los números tienen que coincidir). Bloques según permiso: `reportes.ver` (hoy vs. ayer, mes vs. mismos días del mes anterior, 14 días, medios de pago, por caja, más vendidos), `terminales.ver`/`cajas.ver` (salud con `SaludCaja`, cajas "instaladas" = canjearon un código, turnos abiertos), `productos.ver` (stock crítico: vendibles con `stock_sucursal.cantidad <= stock_critico`), `remitos.ver`, `facturacion.ver`, `clientes.gestionar`. En tests, `Carbon::setTestNow` en UTC: con otra zona Carbon lee las fechas de la base en esa zona.

## Reportes de ventas

`App\Services\ReporteVentasService` (pantalla `Reportes\Ventas`, exportación `ExportarVentasController`, permiso `reportes.ver` para admin y supervisor): indicadores (bruto, descuentos, manuales, cobrado, devoluciones, neto, ticket promedio, unidades), por medio de pago, por cajero, por sucursal/caja, por día, tarjetas/QR para conciliar y promociones.

- **Todo se guarda en UTC; los filtros son días locales** (`config('app.display_timezone')`, por defecto Argentina). `rangoUtc()` convierte el día local a un rango UTC: sin eso una venta de las 23:30 caía en el día siguiente (hay test). `porDia()` agrupa en SQL con `CONVERT_TZ` usando el desplazamiento numérico de la zona (`-03:00`), que MySQL resuelve sin las tablas de zonas cargadas: antes hidrataba cada venta como modelo y con 150.000 ventas el tablero tardaba unos 5 s por refresco (ahora ~50 ms). Vale mientras la zona no cambie de desplazamiento dentro del rango (Argentina no tiene horario de verano). `ventas.fecha` y `devoluciones.fecha` tienen índice propio: el compuesto `(sucursal_id, fecha)` no sirve en "Todas las sucursales".
- Ventas de cajas anteriores a 1.1 no tienen `pagos_venta`: aparecen como medio `sin_detalle` para que la suma por medio cierre contra el total.
- Las devoluciones cuentan por **su** fecha, y el filtro por cajero usa el cajero de la venta original.
- CSV con `;`, coma decimal y BOM UTF-8 para que Excel en español lo abra bien; se genera con `chunk(500)`.

## Zonas horarias: reglas para no repetir el bug

Se corrigió el 2026-09-19 después de que una venta de las 23:04 apareciera como "del día siguiente" en la pantalla de stock del POS y pareciera que faltaba una venta (los datos estaban bien: era solo presentación).

- **Todo se guarda en UTC; se muestra y se filtra en hora argentina.** Manager: `config('app.display_timezone')`. POS: `config('pos.zona_horaria')`. En una vista, `->timezone(...)` va **antes** de `->format(...)` en cualquier `*_at`/`fecha` con hora; sin eso sale 3 h de más.
- **Filtros por día: nunca `whereDate('col', $diaLocal)` ni `today()`** (cortan a las 21:00 locales). Se convierte el día local a un rango UTC: `ReporteVentasService::rangoUtc()`, `PorArticulo`/`Cierres::diaLocalEnUtc()`, y en el POS `now()->timezone(config('pos.zona_horaria'))->startOfDay()->utc()`.
- **Excepción: columnas `date` sin hora** (`comprobantes.fecha`, que además viaja a AFIP como `CbteFch`): ahí `whereDate` con la fecha local es lo correcto y convertir de zona correría el día. Tampoco se convierte un día ya local, como `$d['dia']` de `porDia()`.
- Los filtros que llegan por la URL (`#[Url]`, como `desde`/`hasta` de Cierres) se validan antes de `Carbon::parse`: una fecha inválida se ignora en vez de romper la pantalla.
- **Tests**: `travelTo()`/`setTestNow()` con un instante en zona Argentina hace que Carbon parsee después los strings sin zona *en Argentina*: pasarle el instante con `->utc()` y escribir los fixtures en UTC.
- **Pendiente**: `diaLocalEnUtc()` está duplicado en `PorArticulo` y `Cierres` (moverlo a un lugar común) y el POS no tiene un helper equivalente.

## Escala: dashboard con muchas sucursales

Medido el 2026-09-19 con una simulación local (22 sucursales, 55 cajas, 3.000 ventas por día durante 50 días = 150.000 ventas) antes de los cambios: **~5,3 s por refresco** en "Todas las sucursales" (63 % el gráfico de 14 días, `porDia`, que cargaba ~42.000 ventas como modelos; con una sola sucursal ~350 ms). Cada refresco son ~29 consultas y no hay N+1: lo caro era cada consulta, no la cantidad. Se aplicó `porDia` en SQL (~54 ms, mismo resultado) y los índices `ventas(fecha)` y `devoluciones(fecha)`; **no se volvió a medir en producción**, la mejora a ~0,2–0,5 s es una estimación.

- El refresco de cada usuario con el Dashboard abierto es 1 request por minuto; con 5–10 usuarios en una VM e2-micro (1 GB, 6 workers de PHP-FPM que también atienden el sync de las cajas) el costo se nota si el render tarda segundos, no por la cantidad de queries.
- **No usar `wire:poll.keep-alive`**: haría renderizar las pestañas ocultas todo el día. Livewire baja el poll al 5 % con la pestaña en segundo plano; por eso el Dashboard y la Auditoría refrescan al volver a la pestaña (si estuvo oculta más de 15 s).
- **Pendiente si el tablero se siente pesado**: caché de `masVendidos` (5–10 min) y de `ayer`/`mes_anterior` (10–15 min; corto porque una caja offline puede sincronizar tarde). **No cachear "hoy"** ni el tablero entero: rompe que una venta nueva aparezca (hay un test que lo cuida). Las 4 series (hoy/ayer/mes/mes anterior) se pueden unificar en la suma pero no en las unidades vendidas: ese join con `detalle_ventas` es lo caro.
- Después de una venta, el número llega al Dashboard en ≤60 s si la pestaña está visible (ver la demora caja → Manager en `pos-integracion.md`).
