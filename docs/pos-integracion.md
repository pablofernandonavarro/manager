# POS: instalación, versión, comandos, actualización y sync

> Extraído de CLAUDE.md el 2026-09-16 para aliviar el context raíz. Ver también CLAUDE.md.

## Instalación de cajas (código de instalación)

Un usuario del Manager da de alta el POS y aprieta **Código** en `Puntos de venta`: se genera un código corto de un solo uso (`ABCD-1234`, vence a las 24h) que el técnico tipea en la máquina destino, donde `php artisan pos:provision <codigo>` lo canjea contra `POST /api/v1/pos/provision`.

El botón se llama "Código" y no "Instalar" a propósito: el Manager **no instala nada a distancia**, y nombrarlo así hacía que se esperara una instalación remota que no ocurre. El modal lo dice explícitamente y la columna **Instalación** de la tabla cierra el lazo — `Sin instalar` / `Código sin usar` / `Instalada <fecha>`, calculado con `withMax('codigosInstalacion as instalado_at', 'usado_at')` y un conteo de códigos vigentes. Sin esa columna no hay forma de saber desde el Manager en qué estado quedó cada terminal.

Decisiones que no conviene deshacer:

- **El secret no se guarda en ningún lado.** Se genera recién al canjear el código y se entrega una sola vez. Por eso una reinstalación rota la credencial sola, y por eso el canje además borra los tokens Sanctum del PDV: si la máquina vieja se perdió o se rompió, deja de poder sincronizar.
- El endpoint **no lleva auth** — el código *es* la credencial. De ahí que sea de un solo uso, venza, se resuelva bajo `lockForUpdate()` y tenga `throttle:10,1`.
- Generar un código nuevo vence los anteriores del mismo PDV, para que no queden códigos viejos sirviendo.
- El alfabeto del código excluye `0/O/1/I/L` porque se dicta por teléfono. `CodigoInstalacion::normalizar()` acepta que lo tipeen sin guion y en minúsculas.

Cubierto por `tests/Feature/PosProvisionTest.php`.

**Ojo del lado POS**: `ManagerApiService` lee url y token de la tabla `configuracion` *en su constructor*. En `ProvisionCommand` los servicios no se inyectan, se resuelven con `app()->make()` después de escribir esos valores — inyectarlos da una instancia con las credenciales viejas y la sincronización inicial falla con un error de conexión engañoso (el `retry()` de Http convierte el 401 en excepción).

## Versión instalada y conexión de cada caja

Cada llamada autenticada de una caja trae `X-POS-Version` y `X-POS-Tipo` (`escritorio`/`clasica`). El middleware `RegistrarConexionPos` (en el grupo `auth:sanctum` de `api/v1`) los guarda en `puntos_de_venta.version_pos`, `tipo_instalacion` y `ultima_conexion_at`. Escribe solo si cambió algo o pasó más de un minuto, porque las cajas llaman varias veces por minuto. Los valores se validan (versión `[\w.-]`, tipo de lista cerrada): vienen de afuera y se muestran en pantalla. `Puntos de venta` compara contra la última publicada según el tipo (`pos-escritorio.json` o `VersionPos::vigente()`) y muestra "En línea" si habló en los últimos 3 minutos. Cubierto por `tests/Feature/PosVersionInstaladaTest.php`.

## Salud de las cajas

- Cada caja (desde 1.4.2) manda cada minuto `POST api/v1/pos/estado` (`PosEstadoController`) → `puntos_de_venta.estado_caja` + `estado_reportado_at`. `App\Support\SaludCaja::evaluar()` arma el diagnóstico al mostrar (Puntos de venta → columna Estado y aviso arriba): sin conexión, stock sin bajar >10 min (**sync trabado**), ventas sin enviar >15 min, facturas sin CAE >30 min o rechazadas, jobs fallidos/acumulados, versión vieja, caja que no informa.
- **"En línea" no alcanza**: la caja 2 estuvo 11 horas sin bajar stock con la última conexión al día (seguía respondiendo `pos:comandos`). Por eso el reporte sale de ese proceso y el stock se evalúa aparte.
- Las antigüedades internas se miden contra `generado_at` del reporte (reloj de la caja): un reloj desfasado no dispara alertas. Textos de duración propios en español (`duracion()`): el locale de la app es `en` y `diffForHumans` saldría en inglés.

## Instalador descargable

### App de escritorio (camino principal)

El POS se reparte como app de escritorio NativePHP, compilada desde el mismo repo del POS con `compilar-escritorio.bat -Version X.Y.Z` (arma una copia limpia en `C:\MisLaravel\pos-build-escritorio`; `-Manager C:\MisLaravel\manager` además la publica). Ya no existe la copia `pos-native`. `php artisan pos:publicar-escritorio <carpeta win-unpacked | Setup.exe>` la deja en `storage/app/private/pos-escritorio/` (zip con carpeta raíz `POS-Escritorio` —no `POS`, que es la de la instalación clásica—, o el `.exe`, más `pos-escritorio.json` con versión y sha256). Se baja desde `Puntos de venta` → **Descargar POS de escritorio**, ruta `pdv.instalador-escritorio`, con `can:terminales.instalar`. El comando rechaza la carpeta si trae `.sqlite`, `.pos-info` o `.env.escritorio` (el `.env` de compilación con la APP_KEY; `pos:kit` y `pos:empaquetar` también lo excluyen): en la app de escritorio los datos viven en `%APPDATA%\pos-system`, así que una compilación sana nunca los lleva.

En la caja no hay consola: al abrir el exe sin configurar, el middleware `RequiereCajaConfigurada` manda a `/configuracion`, que pide dirección del Manager + código (`ProvisionService`, el mismo que usa `pos:provision`). Al instalar, `EscritorioService` activa el inicio con Windows (`App::openAtLogin`) y crea el acceso directo. Versión nueva = reemplazar la carpeta; la orden **Actualizar** (`pos:actualizar`) no sirve dentro del exe. Hoy sale zip y no Setup porque NSIS falla creando symlinks sin Modo de desarrollador de Windows.

### Kit clásico (PHP + navegador)

`php artisan pos:kit C:\ruta\a\un\pos` arma el instalador completo (código + `vendor` + `node_modules`, ~48 MB comprimido, ~2 minutos) en `storage/app/private/pos-kit/`. El admin lo baja desde `Puntos de venta` → **Descargar instalador**, ruta `pdv.instalador`, protegida con `can:terminales.instalar` porque el kit lleva el código completo del POS.

**El comando excluye todo lo que identifica a la caja de origen** — `.env`, `.pos-info`, `database.sqlite`, logs, respaldos, `iniciar-*.bat`. Sin eso, la máquina que instale el kit arrancaría creyendo que *es* esa caja y sincronizaría con su token: dos terminales con la misma identidad mandando ventas.

Hay también `preparar-kit.bat` del lado POS, que hace lo mismo pero como carpeta en disco (para pendrive, sin pasar por el Manager).

## Permisos sobre terminales

Tres permisos (creados por migración, no por seeder, para que apliquen en instalaciones existentes):

| Permiso | Quién | Qué habilita |
|---|---|---|
| `terminales.ver` | admin, supervisor | Entrar a `Puntos de venta` |
| `terminales.instalar` | admin | Crear/eliminar cajas, generar códigos, regenerar secret y tokens |
| `terminales.comandos` | admin | Mandar órdenes, incluida *Actualizar* |

**Los `authorize()` van en cada método del componente Livewire, no solo escondiendo botones.** Cada método público es un endpoint que un usuario autenticado puede invocar con un request armado a mano; ocultar el botón no protege nada. Esconder los botones es comodidad, no seguridad.

Al testear esto: **Livewire convierte `AuthorizationException` en un 403, no la propaga**. Se verifica con `->assertForbidden()`, y además comprobando que la acción no haya tenido efecto. Cubierto por `tests/Feature/PermisosTerminalesTest.php`.

## Canal de órdenes (reparar una caja a distancia)

Botón **Reparar** en `Puntos de venta`: encola una orden en `comandos_pos` que la caja recoge en su próxima consulta (`pos:comandos`, cada minuto) y ejecuta sola. Permite arreglar una terminal sin ir físicamente, mientras siga encendida y con el POS instalado.

- **`App\Enums\ComandoPos` es la frontera de seguridad.** Se manda un identificador de una lista cerrada, nunca texto que la caja pueda interpretar como shell. Del lado POS, `ComandosCommand::ejecutar()` lo traduce con un `match` que **lanza excepción ante un valor desconocido**. No agregar un comando "genérico" que reciba parámetros libres: eso convertiría al Manager en ejecución remota arbitraria sobre todas las terminales.
- Las órdenes se marcan **tomadas al entregarlas**, así que no vuelven a salir solas si la caja se cuelga a mitad. Queda visible como colgada en el Manager, que es preferible a reejecutar algo que quizás ya corrió.
- `ComandoPos::encolar()` no duplica: si ya hay una igual sin ejecutar, devuelve esa.
- El reporte de resultado está acotado al PDV autenticado — una caja no puede cerrar órdenes de otra.

Cubierto por `tests/Feature/PosComandosTest.php`.

`recrear_acceso_directo` es el único que ejecuta algo fuera de PHP. No rompe el modelo de seguridad porque la ruta del script es fija y dentro de la propia instalación, y los argumentos van en un array de `Process` (no pasa por shell, no hay concatenación). Del Manager solo viaja la etiqueta.

## Actualizar el código de las cajas

`php artisan pos:empaquetar C:\ruta\al\pos --notas="..."` arma un zip, guarda su sha256 en `versiones_pos` y lo marca vigente. La orden **Actualizar el POS** hace que la caja lo baje (`GET /api/v1/pos/version` y `/pos/paquete`), verifique el hash, respalde lo que va a pisar, aplique y migre. Si algo falla, restaura el respaldo sola.

- El paquete **incluye `public/build` ya compilado**: la caja no necesita Node para actualizarse, y se evita el problema de assets viejos que ya mordió dos veces.
- Incluye los `*.bat`/`*.ps1`/`*.vbs` de la raíz. Sin eso los scripts quedan congelados en la versión con la que se instaló la caja.
- **Nunca** viajan `.env`, `database.sqlite`, `storage/`, `vendor/` ni `node_modules/` — ni en el paquete ni al aplicar (lista `INTOCABLE` en `ActualizarCommand`).
- La opción del comando es `--etiqueta`, no `--version`: Artisan ya reserva ese nombre.
- **Bootstrapping**: una caja no puede actualizarse si todavía no tiene `ActualizarCommand`. La primera vez hay que copiar ese archivo (y `ManagerApiService`) a mano; de ahí en adelante es automático.
- **Un paquete con el actualizador roto rompe las actualizaciones futuras.** Pasó de verdad: se publicó una versión cuyo `ActualizarCommand` tenía un bug, las cajas se lo aplicaron encima del bueno y quedaron sin poder actualizarse solas. El rollback las dejó sanas pero hubo que copiar el archivo a mano. **Antes de publicar, probar el paquete en una caja de prueba** — es el único componente que, si sale mal, no se puede arreglar a distancia.
- Los respaldos rotan solos: se conservan los últimos 3 (`rotarRespaldos()`). Sin eso cada actualización dejaba ~700 KB para siempre en el disco de la caja.

Dos cosas que costaron encontrar y conviene no reintroducir:

- **`sink()` con una ruta deja el archivo tomado por PHP**, y después PowerShell no puede descomprimirlo ("está siendo utilizado en otro proceso"). Por eso `descargarPaquete()` abre el handle con `fopen` y lo cierra explícitamente.
- **`Expand-Archive` sale con código 0 aunque falle** (error no terminante), así que el exit code no sirve para saber si anduvo. Lo que confirma el éxito es que exista el `VERSION` en el staging.

Se extrae con PowerShell y no con `ZipArchive` porque el PHP de las cajas (XAMPP 8.2) viene **sin la extensión zip cargada**; depender de ella obligaría a editar el `php.ini` de cada máquina.

## Contrato de sync con el POS

El POS vive en `C:\MisLaravel\pos` (Laravel + Livewire + SQLite, offline-first). `SyncController` es la única puerta de entrada. Cada venta y movimiento que llega trae un `uuid` generado en la caja, con índice único en `ventas` y `movimientos_stock`: si el uuid ya existe se descarta y se responde `duplicada`/`duplicado` en `resultados[]`, en vez de volver a insertar. Esto es lo que hace seguro el `retry()` del cliente, así que **no saques la validación `required|uuid` ni el chequeo previo a insertar**, y cualquier cambio al payload hay que hacerlo en los dos repos a la vez. Cubierto por `tests/Feature/SyncIdempotenciaTest.php`.

**`/sync/productos` responde en streaming** (`lazyById(500)`, solo las columnas que viajan, padre por join): con ~10.000 productos la colección Eloquent superaba los 256 MB de PHP y la caja no podía bajar el catálogo. Medido en producción: 10.000 productos en 2,2 s, 6,1 MB de JSON, 48 MB de pico. Mismo JSON `{data, total, synced_at}`; `synced_at` se toma antes de consultar. La caja lo pide cada 5 minutos con `updated_since` (delta). En tests el cuerpo sale de `->streamedContent()`.

`/sync/ventas` ya genera él mismo el `MovimientoStock` de la venta y descuenta `stock_sucursal`; por eso el POS filtra los movimientos tipo `venta` y no los reenvía por `/sync/movimientos`. Mandarlos por ambos lados descuenta el stock dos veces.

Al tocar `stock_sucursal.cantidad` no uses `DB::raw()` dentro de `updateOrCreate()`: el modelo castea `cantidad` a `integer` y un `Query\Expression` revienta con "could not be converted to int" (era un 500 fijo en `/sync/ventas`). Usá `firstOrNew()` + `max(0, ...)`.
