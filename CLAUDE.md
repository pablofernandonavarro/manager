<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5.3
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `livewire-development` — Develops reactive Livewire 4 components. Activates when creating, updating, or modifying Livewire components; working with wire:model, wire:click, wire:loading, or any wire: directives; adding real-time updates, loading states, or reactivity; debugging component behavior; writing Livewire tests; or when the user mentions Livewire, component, counter, or reactive UI.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `vendor/bin/sail npm run build`, `vendor/bin/sail npm run dev`, or `vendor/bin/sail composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `vendor/bin/sail artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces using only PHP — no JavaScript required.
- Instead of writing frontend code in JavaScript frameworks, you use Alpine.js to build the UI when client-side interactions are required.
- State lives on the server; the UI reflects it. Validate and authorize in actions (they're like HTTP requests).
- IMPORTANT: Activate `livewire-development` every time you're working with Livewire-related tasks.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `vendor/bin/sail artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `vendor/bin/sail artisan test --compact`.
- To run all tests in a file: `vendor/bin/sail artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `vendor/bin/sail artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

</laravel-boost-guidelines>

## Entorno local real (tiene prioridad sobre las "sail rules" de arriba)

Las reglas generadas por Boost dicen ejecutar todo con `vendor/bin/sail`. **En esta máquina no es así.** El setup acordado es híbrido:

- La app se sirve con **Laravel Herd** en `http://manager.test`. Los contenedores `laravel.test` y `mailpit` están apagados a propósito: el de la app competía por el puerto 80 con Herd.
- Solo corre el contenedor de **MySQL**, publicado en el host en `127.0.0.1:3307` (no `mysql:3306`, que solo resuelve dentro de la red de Docker). El `.env` apunta ahí.
- Levantarlo: `docker compose up -d mysql` desde `C:\MisLaravel\manager`.
- **Los comandos van desde Windows, no por Sail**, y con PHP 8.4 — el `php` del PATH es 8.2 y `vendor/composer/platform_check.php` aborta porque el proyecto requiere >= 8.4:

```powershell
& "$env:USERPROFILE\.config\herd\bin\php84.bat" artisan migrate
& "$env:USERPROFILE\.config\herd\bin\php84.bat" artisan test --filter=NombreDelTest
```

Si aparece `getaddrinfo for mysql failed`, es que el `.env` volvió a tener `DB_HOST=mysql`, o que quedó config cacheada: `php84 artisan config:clear`.

### Assets: recompilar al agregar clases de Tailwind

Tailwind v4 genera CSS solo para las clases que encuentra escaneando los archivos. **Una clase nueva en un Blade no existe hasta correr el build**, y el síntoma es confuso: el elemento se renderiza sin estilo (un modal a todo el ancho, un color que no aparece) sin ningún error.

```powershell
npm ci          # solo la primera vez en Windows: node_modules venía instalado desde el contenedor, sin los binarios de Windows
npm run build
```

Para diagnosticar si una clase falta: `Select-String -Path "public\build\assets\*.css" -Pattern "max-w-5xl"`.

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

## Caja, cobro y promociones bancarias

- **Promociones bancarias** (`PromocionBancaria`, pantalla `Promociones\Index`, permiso `promociones.gestionar`): medio(s), tarjetas, banco, días, vigencia, sucursales (`null` = todas en las listas JSON), `modalidad` descuento en caja o reintegro del banco, porcentaje con tope, mínimo y cuotas sin interés. Bajan a las cajas por `GET api/v1/sync/promociones` (`paraSucursal()`: activas, no vencidas, de la sucursal). La caja las aplica y **recalcula el descuento ella misma**; el Manager guarda lo que la caja informa.
- **Ventas con pagos**: `sync/ventas` acepta `turno_uuid`, `cajero`, `metodo_pago`, cliente y `pagos[]` (`PagoVenta`: `monto` cubierto, `descuento`, `importe` cobrado, tarjeta, banco, cuotas, promoción). Todo opcional para que las cajas viejas sigan entrando. `promocion_id` **no** usa `exists`: una promo borrada mientras la caja estaba offline dejaría la venta rechazada para siempre; se vincula solo si existe y se conserva `promocion_nombre`.
- **Turnos de caja** (`TurnoCaja`, `MovimientoCaja`): llegan por `POST api/v1/sync/turnos` (`PosTurnosController`), idempotente por uuid. El abierto se actualiza en cada envío; **el cerrado es inmutable** (reenvío → `duplicado`). Un uuid de otra caja → `rechazado`. `numero` no es único por caja (una reinstalación vuelve a numerar). Ventas y turnos se vinculan por `turno_uuid`, sin FK, porque llegan en envíos separados. Pantalla `Cajas\Cierres` (permiso `cajas.ver`, admin y supervisor) con filtros y el Z completo.
- **Cajeros** (`Cajero`, pantalla `Cajeros\Index`, permiso `cajeros.gestionar`): nombre, rol `cajero`/`supervisor`, sucursales (`null` = todas), PIN de 4 a 6 dígitos guardado con `Hash::make` (al editar, vacío = no cambiar). `GET api/v1/sync/cajeros` manda solo `id, nombre, rol, pin_hash` de los activos de la sucursal: la caja verifica el PIN offline. No son usuarios del Manager.
- **Devoluciones** (`Devolucion`, `DevolucionItem`): `POST api/v1/sync/devoluciones` (`PosDevolucionesController`), idempotente por uuid, vinculada a la venta por `venta_uuid`. **No toca stock**: la mercadería devuelta llega como `movimientos_stock` tipo `devolucion`. Las ventas guardan `descuento_manual` y `descuento_autorizado_por`. El Z (`resumen`) trae bloque `devoluciones` y `ventas.neto`.
- En tests de API con varias cajas, llamar `$this->app['auth']->forgetGuards()` entre requests: el guard queda con el usuario del request anterior y dos cajas parecen la misma. Cubierto por `PosCajaSyncTest` y `PromocionesYCierresTest`.

## Facturación electrónica (AFIP) — configuración

Centralizada en el Manager: **un emisor** (`ConfiguracionFiscal`, fila única id 1, `actual()`) con su certificado, y **un punto de venta de AFIP por sucursal** (`sucursales.afip_punto_venta`, único). Las cajas le van a pedir el CAE al Manager (la emisión todavía no está implementada). Pantalla `Facturacion\Configuracion` (`/facturacion/configuracion`, permiso `facturacion.configurar`, solo admin).

- **Certificado**: `Services\Afip\Certificados` genera clave RSA 2048 y CSR con `serialNumber = "CUIT nnnnnnnnnnn"` (lo exige AFIP) y lee/valida el `.crt` (corresponde a la clave, es del CUIT configurado, no está vencido; detecta homologación por emisor "Computadores Test"). **Siempre pasar `'config' => Certificados::opensslCnf()`** (`resources/openssl/openssl.cnf`): en Windows `openssl_pkey_new` falla sin un cnf ("system library::No such process").
- **Cifrado**: `clave_privada`, `certificado`, `ta_token`, `ta_sign` con cast `encrypted` (APP_KEY). Cambiar la APP_KEY obliga a volver a cargar el certificado.
- **WSAA** (`Services\Afip\Wsaa`): firma el TRA con `openssl_cms_sign` en DER y lo manda por `Http` (sin ext-soap ni WSDL). **El ticket se guarda y se reusa**: AFIP rechaza un login nuevo mientras hay uno vigente (`coe.alreadyAuthenticated`). Cambiar CUIT o entorno lo invalida y desactiva la facturación. Los errores de AFIP vienen con el código en `faultcode` (`Soap::fault()` devuelve `código: texto`).
- **WSFEv1** (`Services\Afip\Wsfe`): `dummy()`, `ultimoAutorizado()`, `puntosDeVenta()` (602 = sin resultados, normal en homologación). "Probar conexión" corre esos pasos en orden y corta al primero que falla; no se puede activar la facturación sin faltantes ni sin una prueba OK.
- Tests (`FacturacionConfiguracionTest`) usan un certificado autofirmado con la misma clave y respuestas SOAP con `Http::fake` + `Http::preventStrayRequests()`: nunca pegan a AFIP.
- `ConfiguracionFiscal::actual()` fuerza `id = 1` con `forceFill`: `firstOrCreate(['id' => 1])` ignoraba el id (no fillable) y creaba filas nuevas.

## Reportes de ventas

`App\Services\ReporteVentasService` (pantalla `Reportes\Ventas`, exportación `ExportarVentasController`, permiso `reportes.ver` para admin y supervisor): indicadores (bruto, descuentos, manuales, cobrado, devoluciones, neto, ticket promedio, unidades), por medio de pago, por cajero, por sucursal/caja, por día, tarjetas/QR para conciliar y promociones.

- **Todo se guarda en UTC; los filtros son días locales** (`config('app.display_timezone')`, por defecto Argentina). `rangoUtc()` convierte el día local a un rango UTC: sin eso una venta de las 23:30 caía en el día siguiente (hay test). `porDia()` agrupa en PHP y no con `CONVERT_TZ` porque MySQL necesita las tablas de zonas cargadas.
- Ventas de cajas anteriores a 1.1 no tienen `pagos_venta`: aparecen como medio `sin_detalle` para que la suma por medio cierre contra el total.
- Las devoluciones cuentan por **su** fecha, y el filtro por cajero usa el cajero de la venta original.
- CSV con `;`, coma decimal y BOM UTF-8 para que Excel en español lo abra bien; se genera con `chunk(500)`.

## Remitos (transferencias entre sucursales)

**Todo movimiento de stock por remito pasa por `App\Services\RemitoService`** (`crear`, `confirmar`, `cancelar`); las pantallas (`Sucursales\RemitoNuevo`, el envío rápido de `Sucursales\Stock` y `Sucursales\Remitos`) no tocan `stock_sucursal` directamente. Cualquier sucursal activa puede ser origen o destino, Central incluida. Crear descuenta del origen (queda en tránsito, no suma en ninguna sucursal), confirmar acredita en el destino, cancelar devuelve al origen; cada paso registra un `MovimientoStock` tipo `transferencia` sin punto de venta (por eso `punto_de_venta_id` es nullable) con referencia `Remito #000123`, y recalcula `products.stock` como suma de `stock_sucursal`.

- El disponible se lee dentro de la transacción con `lockForUpdate`, **nunca** del valor que manda la pantalla: antes el control de stock se hacía contra un parámetro del navegador y se podía dejar Central en negativo.
- `confirmar`/`cancelar` bloquean la fila del remito y releen el estado: un doble clic no acredita dos veces.
- Errores de negocio salen como `App\Exceptions\RemitoException`, con mensaje apto para mostrar.
- Permisos: `remitos.ver`, `remitos.crear`, `remitos.recibir`, `remitos.cancelar` (admin todos; supervisor ver y recibir). Cubierto por `tests/Feature/RemitosTest.php`.
- **Recepción desde la caja**: `GET api/v1/pos/remitos` (en tránsito hacia la sucursal del PDV autenticado) y `POST api/v1/pos/remitos/{id}/recibir` (`PosRemitosController`). Recibir es **idempotente**: si ya estaba confirmado responde 200 `ya_recibido` con el stock actual, para que la caja pueda reintentar tras un corte; cancelado → 409; de otra sucursal → 404. Devuelve `stock[]` de la sucursal para esos productos. Quién recibió queda en `confirmado_por_user_id` / `confirmado_por_punto_de_venta_id`. Cubierto por `tests/Feature/PosRemitosTest.php`.

## Instalador descargable

### App de escritorio (camino principal)

El POS se reparte como app de escritorio NativePHP, compilada en `C:\MisLaravel\pos-native` (`php artisan native:build win x64`, con el bin de Herd primero en el PATH). `php artisan pos:publicar-escritorio <carpeta win-unpacked | Setup.exe>` la deja en `storage/app/private/pos-escritorio/` (zip con carpeta raíz `POS-Escritorio` —no `POS`, que es la de la instalación clásica—, o el `.exe`, más `pos-escritorio.json` con versión y sha256). Se baja desde `Puntos de venta` → **Descargar POS de escritorio**, ruta `pdv.instalador-escritorio`, con `can:terminales.instalar`. El comando rechaza la carpeta si trae `.sqlite` o `.pos-info`: en la app de escritorio los datos viven en `%APPDATA%\pos-system`, así que una compilación sana nunca los lleva.

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

## Pendiente para producción

Lo hecho: permisos, `throttle:10,1` en `pos/auth` (antes se podía probar el secret por fuerza bruta), `.env.example` del POS con `APP_DEBUG=false`, y la contraseña del admin fuera del repositorio (`AdminUserSeeder` toma `ADMIN_PASSWORD` o genera una al azar y la muestra una sola vez).

Lo que falta y es bloqueante:

- **HTTPS.** Hoy el secret de cada caja, el token bearer y los códigos de instalación viajan en texto plano. En la red de un local, eso alcanza para quedarse con una terminal.
- **`APP_ENV`/`APP_DEBUG` de estas instalaciones.** Los `.env` locales siguen en modo desarrollo a propósito; hay que endurecerlos al desplegar de verdad.
- Manager en un servidor real con backup de la base (hoy Herd + MySQL en Docker de una máquina de escritorio).
- `C:\MisLaravel\pos` es a la vez el código fuente y la instalación de Caja 1. Conviene separarlos: empaquetar captura el estado en que esté esa carpeta.

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

`/sync/ventas` ya genera él mismo el `MovimientoStock` de la venta y descuenta `stock_sucursal`; por eso el POS filtra los movimientos tipo `venta` y no los reenvía por `/sync/movimientos`. Mandarlos por ambos lados descuenta el stock dos veces.

Al tocar `stock_sucursal.cantidad` no uses `DB::raw()` dentro de `updateOrCreate()`: el modelo castea `cantidad` a `integer` y un `Query\Expression` revienta con "could not be converted to int" (era un 500 fijo en `/sync/ventas`). Usá `firstOrNew()` + `max(0, ...)`.
