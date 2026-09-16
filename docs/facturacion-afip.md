# Facturación electrónica (AFIP)

> Extraído de CLAUDE.md el 2026-09-16 para aliviar el context raíz. Ver también CLAUDE.md.

## Facturación electrónica (AFIP) — configuración

Centralizada en el Manager: **un emisor** (`ConfiguracionFiscal`, fila única id 1, `actual()`) con su certificado, y **un punto de venta de AFIP por sucursal** (`sucursales.afip_punto_venta`, único). Las cajas le van a pedir el CAE al Manager (la emisión todavía no está implementada). Pantalla `Facturacion\Configuracion` (`/facturacion/configuracion`, permiso `facturacion.configurar`, solo admin).

- **Certificado**: `Services\Afip\Certificados` genera clave RSA 2048 y CSR con `serialNumber = "CUIT nnnnnnnnnnn"` (lo exige AFIP) y lee/valida el `.crt` (corresponde a la clave, es del CUIT configurado, no está vencido; detecta homologación por emisor "Computadores Test"). **Siempre pasar `'config' => Certificados::opensslCnf()`** (`resources/openssl/openssl.cnf`): en Windows `openssl_pkey_new` falla sin un cnf ("system library::No such process").
- **Cifrado**: `clave_privada`, `certificado`, `ta_token`, `ta_sign` con cast `encrypted` (APP_KEY). Cambiar la APP_KEY obliga a volver a cargar el certificado.
- **WSAA** (`Services\Afip\Wsaa`): firma el TRA con `openssl_cms_sign` en DER y lo manda por `Http` (sin ext-soap ni WSDL). **El ticket se guarda y se reusa**: AFIP rechaza un login nuevo mientras hay uno vigente (`coe.alreadyAuthenticated`). Cambiar CUIT o entorno lo invalida y desactiva la facturación. Los errores de AFIP vienen con el código en `faultcode` (`Soap::fault()` devuelve `código: texto`).
- **WSFEv1** (`Services\Afip\Wsfe`): `dummy()`, `ultimoAutorizado()`, `puntosDeVenta()` (602 = sin resultados, normal en homologación). "Probar conexión" corre esos pasos en orden y corta al primero que falla; no se puede activar la facturación sin faltantes ni sin una prueba OK.
- Tests (`FacturacionConfiguracionTest`) usan un certificado autofirmado con la misma clave y respuestas SOAP con `Http::fake` + `Http::preventStrayRequests()`: nunca pegan a AFIP.
- `ConfiguracionFiscal::actual()` fuerza `id = 1` con `forceFill`: `firstOrCreate(['id' => 1])` ignoraba el id (no fillable) y creaba filas nuevas.

### Emisión (`Services\Facturacion\EmisionComprobantes`, tabla `comprobantes`)

- **La caja nunca habla con AFIP.** Una venta trae el bloque `factura` (receptor) solo si la caja la marcó para facturar; sin ese bloque (cajas viejas) no se factura. `RegistroVentasPos` registra la venta (lo usan `sync/ventas` y `pos/facturas`) y crea el comprobante **pendiente** si la facturación está activa y la sucursal tiene punto de venta. Un receptor inválido deja el comprobante **rechazado** en vez de rechazar la venta (no puede trabar el sync).
- `POST pos/facturas`: registra y autoriza **en el momento** (el ticket sale con CAE). Si AFIP no contesta responde 200 con el comprobante pendiente y encola `AutorizarComprobante`. `POST pos/comprobantes/estado` (acotado a la caja) y `GET pos/facturacion` (datos del emisor) completan el contrato. `paraCaja()` es lo que la caja guarda e imprime, incluido el QR en SVG (`chillerlan/php-qrcode`), para reimprimir sin conexión.
- **Tipo**: emisor RI → A a inscriptos/monotributistas, B al resto; monotributo/exento → C. Importes: descuentos prorrateados por línea en centavos, neto/IVA por alícuota con `products.iva`, cierra exacto con el total. Fecha del comprobante = día de autorización (AFIP no acepta fechas viejas: una venta offline se factura el día que llega).
- **Numeración sin duplicar**: lock por `entorno:pv:tipo` (`Cache::lock`, necesita un store con locks). El número se **guarda antes** de llamar a `FECAESolicitar`; si la respuesta se pierde (`AfipSinRespuestaException`) queda reservado, y el próximo intento de ese pv/tipo primero lo consulta con `FECompConsultar`: si AFIP lo tiene (mismo importe y documento) se toma ese CAE, si no se libera. Un error que AFIP **contestó** libera el número al instante. Nunca borrar esa reserva a mano.
- Notas de crédito: `sync/devoluciones` crea la NC (3/8/13) asociada a la factura (`CbtesAsoc`); se autoriza recién cuando la factura tiene CAE. Cambiar de entorno deja rechazado lo generado en el anterior.
- **Operación**: hacen falta `queue:work` (jobs `AutorizarComprobante`, backoff hasta 15 min) y el scheduler (`facturacion:autorizar-pendientes` cada 5 minutos, re-encola lo que agotó intentos). Pantalla `Facturacion\Comprobantes` (`facturacion.ver`, admin y supervisor); **Reintentar** (también rechazados) requiere `facturacion.configurar`.
- Tests: `FacturacionEmisionTest` simula AFIP con estado (numera, emite, responde consultas, corta la respuesta con `Http::failedConnection()`).
