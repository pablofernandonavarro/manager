@php
    // Los botones se esconden por comodidad; lo que realmente protege son los authorize()
    // de cada método del componente.
    $puedeInstalar = auth()->user()->can('terminales.instalar');
    $puedeMandarOrdenes = auth()->user()->can('terminales.comandos');
@endphp

<div class="space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Puntos de venta</h1>
            <p class="mt-2 text-sm text-gray-700">Gestioná los terminales POS por sucursal</p>
        </div>
    </div>

    <!-- Instructivo de instalación -->
    <div x-data="{ abierto: false }" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <button type="button" @click="abierto = !abierto"
                class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition-colors">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Cómo instalar un POS en una máquina</h3>
                    <p class="text-xs text-gray-500">App de escritorio · alta nueva o reinstalación de una caja rota</p>
                </div>
            </div>
            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': abierto }"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <div x-show="abierto" x-cloak class="px-6 pb-6 border-t border-gray-100 pt-5">
            <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-5 text-sm text-amber-900">
                El Manager <strong>no instala el POS a distancia</strong>. Alguien tiene que abrir la app
                <strong>una vez</strong> en la máquina de la caja y pegar un código. No hace falta instalar
                PHP ni nada más: la app trae todo adentro.
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <div>
                    <p class="text-sm font-semibold text-gray-900 mb-2 flex items-center gap-2">
                        <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-600 text-white text-xs flex items-center justify-center">1</span>
                        Acá, en el Manager
                    </p>
                    <ul class="text-sm text-gray-700 space-y-1.5 ml-7 list-disc">
                        <li>Si la caja no existe: elegí sucursal, poné el nombre y <strong>+ Agregar</strong>. El código aparece solo.</li>
                        <li>Si ya existe: apretá <strong>Código</strong> en su fila.</li>
                        <li>Anotá el código. Vence en 24 h y es de un solo uso.</li>
                    </ul>
                </div>

                <div>
                    <p class="text-sm font-semibold text-gray-900 mb-2 flex items-center gap-2">
                        <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-600 text-white text-xs flex items-center justify-center">2</span>
                        Descargar la app
                    </p>
                    <ul class="text-sm text-gray-700 space-y-1.5 ml-7 list-disc">
                        <li>En la máquina de la caja, entrá a esta pantalla y descargá el POS.</li>
                        <li>
                            Clic derecho en el zip → <strong>Extraer todo</strong> → elegí
                            <code class="px-1 py-0.5 bg-gray-100 rounded text-xs font-mono">C:\</code>.
                            Queda <code class="px-1 py-0.5 bg-gray-100 rounded text-xs font-mono">C:\POS-Escritorio\pos-system.exe</code>.
                        </li>
                        <li>No lo dejes en Descargas: el acceso directo y el inicio automático apuntan a esa carpeta.</li>
                    </ul>

                    @if($puedeInstalar)
                        <div class="mt-3 ml-7">
                            @if($escritorio)
                                <a href="{{ route('pdv.instalador-escritorio') }}"
                                   class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    Descargar POS de escritorio
                                </a>
                                <p class="mt-1.5 text-xs text-gray-500">
                                    Versión {{ $escritorio['version'] }} ·
                                    {{ number_format($escritorio['tamano'] / 1024 / 1024, 0) }} MB ·
                                    publicado el {{ \Carbon\Carbon::parse($escritorio['generado_at'])->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}
                                </p>
                            @else
                                <p class="text-xs text-amber-700 bg-amber-50 rounded px-3 py-2">
                                    Todavía no se publicó la app. En el servidor:
                                    <code class="font-mono">php artisan pos:publicar-escritorio C:\ruta\win-unpacked</code>
                                </p>
                            @endif
                        </div>
                    @endif
                </div>

                <div>
                    <p class="text-sm font-semibold text-gray-900 mb-2 flex items-center gap-2">
                        <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-600 text-white text-xs flex items-center justify-center">3</span>
                        Abrir e instalar
                    </p>
                    <ul class="text-sm text-gray-700 space-y-1.5 ml-7 list-disc">
                        <li>Doble clic en <code class="px-1 py-0.5 bg-gray-100 rounded text-xs font-mono">pos-system.exe</code>.</li>
                        <li>
                            Si Windows muestra <em>"Windows protegió su PC"</em>: <strong>Más información</strong> →
                            <strong>Ejecutar de todas formas</strong> (la app todavía no está firmada).
                        </li>
                        <li>Aparece <strong>Instalar esta caja</strong>. Poné esta dirección y el código:</li>
                    </ul>
                    <input type="text" readonly value="{{ $urlManager }}" @click="$el.select()"
                           class="mt-2 ml-7 w-[calc(100%-1.75rem)] px-3 py-1.5 bg-gray-50 border border-gray-200 rounded text-sm font-mono text-gray-800">
                    @if(str_ends_with(parse_url($urlManager, PHP_URL_HOST) ?? '', '.test') || in_array(parse_url($urlManager, PHP_URL_HOST), ['localhost', '127.0.0.1'], true))
                        <p class="mt-1.5 ml-7 text-xs text-amber-700">
                            Esta dirección solo funciona en esta PC. Para otra máquina hace falta la dirección de red o pública del Manager.
                        </p>
                    @endif
                </div>
            </div>

            <div class="mt-5 pt-5 border-t border-gray-100">
                <p class="text-sm font-semibold text-gray-900 mb-2">Al tocar "Instalar caja" queda todo listo:</p>
                <div class="grid sm:grid-cols-2 gap-x-6 gap-y-1.5 text-sm text-gray-700">
                    <p>✓ Catálogo, precios y stock descargados</p>
                    <p>✓ Acceso directo "POS - nombre" en el escritorio</p>
                    <p>✓ Se abre sola al iniciar Windows</p>
                    <p>✓ Datos propios de la caja en <code class="text-xs font-mono">%APPDATA%\pos-system</code></p>
                </div>
                <p class="mt-3 text-xs text-gray-600 bg-gray-50 rounded px-3 py-2">
                    La caja sincroniza <strong>mientras la app está abierta</strong>. Si se cierra, sigue
                    todo guardado y las ventas pendientes se envían apenas se vuelve a abrir.
                </p>
            </div>

            <div class="mt-5 pt-5 border-t border-gray-100 grid md:grid-cols-3 gap-6">
                <div>
                    <p class="text-sm font-semibold text-gray-900 mb-1.5">Reinstalar una caja rota</p>
                    <p class="text-sm text-gray-700">
                        Generá un <strong>Código</strong> nuevo. Si la app abre, en la caja:
                        <strong>Archivo → Configuración → Reinstalar con otro código</strong>. Si no,
                        instalala de cero en la máquina que corresponda. Al usarse el código, la
                        identidad anterior de esa caja deja de servir.
                    </p>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900 mb-1.5">Pasar a una versión nueva</p>
                    <p class="text-sm text-gray-700">
                        Cerrá la app, <strong>borrá</strong> la carpeta <code class="px-1 py-0.5 bg-gray-100 rounded text-xs font-mono">C:\POS-Escritorio</code>
                        y extraé el zip nuevo en <code class="text-xs font-mono">C:\</code>. No copies encima: pueden quedar archivos
                        de la versión anterior. Ventas y configuración se conservan (viven en
                        <code class="text-xs font-mono">%APPDATA%</code>). No hace falta código. La columna Versión confirma que se actualizó.
                    </p>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900 mb-1.5">Cómo saber si funcionó</p>
                    <p class="text-sm text-gray-700">
                        La columna <strong>Instalación</strong> de la tabla pasa a
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Instalada</span>
                        con la fecha, apenas la máquina canjea el código.
                    </p>
                </div>
            </div>

            <p class="mt-5 text-xs text-gray-500">
                Requisitos en la máquina de la caja: Windows 10 u 11 de 64 bits y acceso por red a este Manager.
            </p>

            <!-- Instalación clásica, para las cajas que ya la usan -->
            <div x-data="{ clasica: false }" class="mt-5 pt-4 border-t border-gray-100">
                <button type="button" @click="clasica = !clasica" class="text-xs text-gray-500 hover:text-gray-800">
                    <span x-text="clasica ? '▾' : '▸'"></span>
                    Instalación clásica (sin app de escritorio, con PHP y el navegador)
                </button>

                <div x-show="clasica" x-cloak class="mt-3 text-sm text-gray-700 space-y-2">
                    <p>
                        Descomprimí el kit, ejecutá <code class="px-1 py-0.5 bg-gray-100 rounded text-xs font-mono">instalar-pos.bat</code>
                        y pegá el código cuando lo pida. Requiere PHP 8.2+, Composer y Node.js en la máquina.
                        La orden <strong>Actualizar</strong> de <em>Reparar</em> solo sirve para estas cajas.
                    </p>

                    @if($puedeInstalar)
                        @if($kit)
                            <a href="{{ route('pdv.instalador') }}" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 text-sm font-medium">
                                Descargar kit clásico
                            </a>
                            <span class="text-xs text-gray-500">
                                · versión {{ $kit['version'] }} · {{ number_format($kit['tamano'] / 1024 / 1024, 0) }} MB
                            </span>
                        @else
                            <p class="text-xs text-gray-500">
                                Kit no generado: <code class="font-mono">php artisan pos:kit C:\ruta\a\un\pos</code>
                            </p>
                        @endif
                    @endif

                    <p class="text-xs text-red-700 bg-red-50 rounded px-2 py-1.5">
                        No copies la carpeta de una caja ya instalada: lleva su base de datos, su
                        <code class="font-mono">.env</code> y su identidad.
                    </p>
                </div>
            </div>

            <!-- Publicar actualización para las cajas ya instaladas -->
            @if($puedeInstalar)
                <div class="mt-5 pt-4 border-t border-gray-100"
                     @if($publicacionPos && $publicacionPos['estado'] === 'procesando') wire:poll.3s @endif>
                    <p class="text-sm font-semibold text-gray-900 mb-1.5">Publicar la última versión para las cajas</p>
                    <p class="text-sm text-gray-700 mb-3">
                        Trae el código más reciente del POS desde GitHub, lo compila y arma el paquete que las
                        cajas bajan solas al recibir la orden <strong>Actualizar</strong>. No hace falta SSH ni consola.
                    </p>

                    @if($publicacionPos && $publicacionPos['estado'] === 'procesando')
                        <button type="button" disabled
                                class="inline-flex items-center gap-2 px-4 py-2 bg-gray-200 text-gray-500 text-sm font-medium rounded-lg cursor-not-allowed">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Publicando... (1-2 minutos)
                        </button>
                    @else
                        <button type="button" wire:click="publicarUltimaVersionPos" wire:loading.attr="disabled"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Publicar última versión
                        </button>
                    @endif

                    @if($publicacionPos && $publicacionPos['estado'] === 'completado')
                        <p class="mt-2 text-xs text-green-700 bg-green-50 rounded px-3 py-2">✓ {{ $publicacionPos['mensaje'] }}</p>
                    @elseif($publicacionPos && $publicacionPos['estado'] === 'error')
                        <p class="mt-2 text-xs text-red-700 bg-red-50 rounded px-3 py-2 whitespace-pre-line">{{ $publicacionPos['mensaje'] }}</p>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <!-- Formulario nuevo POS -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Nuevo punto de venta</h3>

        @if($sucursales->isEmpty())
            <div class="flex items-center gap-3 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                No hay sucursales activas. Primero creá una sucursal para poder agregar puntos de venta.
            </div>
        @else
            <div class="flex gap-3 flex-wrap">
                <div class="flex-1 min-w-48">
                    <select wire:model="sucursalId"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('sucursalId') border-red-300 @enderror">
                        <option value="">Seleccioná una sucursal...</option>
                        @foreach($sucursales as $sucursal)
                            <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                        @endforeach
                    </select>
                    @error('sucursalId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex-1 min-w-48 flex gap-2">
                    <input type="text"
                           wire:model="nombre"
                           wire:keydown.enter.prevent="save"
                           placeholder="Nombre del POS (ej: Caja 1)..."
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('nombre') border-red-300 @enderror">
                    <button type="button" wire:click="save"
                            class="px-6 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors whitespace-nowrap">
                        + Agregar
                    </button>
                </div>
            </div>
            @error('nombre') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        @endif
    </div>

    @if($cajasConProblemas > 0)
        <div class="rounded-xl border px-4 py-3 text-sm {{ $cajasCriticas > 0 ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
            <strong>{{ $cajasConProblemas }} caja(s) necesitan atención.</strong>
            Mirá la columna Estado: una caja puede figurar en línea y no estar bajando stock o enviando ventas.
        </div>
    @endif

    <!-- Listado -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sucursal</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Instalación</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Versión</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Activo</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($puntosDeVenta as $pdv)
                    <tr wire:key="pdv-{{ $pdv->id }}" class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-600">{{ $pdv->sucursal->nombre }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm font-medium text-gray-900 {{ !$pdv->activo ? 'line-through text-gray-400' : '' }}">
                                {{ $pdv->nombre }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            {{-- Estar instalada gana sobre tener un código pendiente: una caja
                                 que ya funciona no debe verse como si no estuviera instalada
                                 solo porque alguien generó un código para reinstalarla. --}}
                            @if($pdv->instalado_at)
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                    Instalada
                                </span>
                                <p class="mt-0.5 text-xs text-gray-400">
                                    {{ \Carbon\Carbon::parse($pdv->instalado_at)->format('d/m/Y H:i') }}
                                </p>
                                @if($pdv->codigos_pendientes > 0)
                                    <p class="mt-0.5 text-xs text-amber-600">+ código sin usar (reinstalación)</p>
                                @endif
                                @if($pdv->ultimoComando)
                                    @php $uc = $pdv->ultimoComando; @endphp
                                    <p @class([
                                        'mt-1 text-xs',
                                        'text-gray-500' => in_array($uc->estado, ['pendiente', 'tomado']),
                                        'text-green-700' => $uc->estado === 'completado',
                                        'text-red-600' => $uc->estado === 'fallido',
                                    ])
                                       title="{{ $uc->resultado }}">
                                        {{ $uc->comando->label() }}:
                                        @if($uc->estado === 'pendiente') esperando a la caja
                                        @elseif($uc->estado === 'tomado') ejecutando…
                                        @elseif($uc->estado === 'completado') ✓ {{ $uc->finalizado_at?->format('H:i') }}
                                        @else ✕ falló
                                        @endif
                                    </p>
                                @endif
                            @elseif($pdv->codigos_pendientes > 0)
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Código sin usar
                                </span>
                                <p class="mt-0.5 text-xs text-gray-400">Esperando que se instale en la máquina</p>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                    Sin instalar
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($pdv->version_pos)
                                @php
                                    $ultima = $ultimaVersion[$pdv->tipo_instalacion] ?? null;
                                    $desactualizada = $ultima && version_compare($pdv->version_pos, $ultima, '<');
                                @endphp
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-mono font-medium text-gray-900">{{ $pdv->version_pos }}</span>
                                    <span class="px-1.5 py-0.5 rounded text-xs font-medium {{ $pdv->tipo_instalacion === 'escritorio' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $pdv->tipo_instalacion === 'escritorio' ? 'Escritorio' : 'Clásica' }}
                                    </span>
                                </div>
                                @if($desactualizada)
                                    <p class="mt-0.5 text-xs text-amber-700">Desactualizada · última {{ $ultima }}</p>
                                @elseif($ultima)
                                    <p class="mt-0.5 text-xs text-green-700">Al día</p>
                                @endif
                            @else
                                <span class="text-sm text-gray-400">Sin informar</span>
                                @if($pdv->instalado_at)
                                    <p class="mt-0.5 text-xs text-gray-400" title="Las cajas informan su versión desde la app de escritorio 1.0.4 o el paquete clásico que la incluya">Se ve cuando la caja se actualice</p>
                                @endif
                            @endif

                            @if($pdv->ultima_conexion_at)
                                <p class="mt-1 flex items-center gap-1.5 text-xs {{ $pdv->estaConectada() ? 'text-green-700' : 'text-gray-500' }}"
                                   title="{{ $pdv->ultima_conexion_at->format('d/m/Y H:i') }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $pdv->estaConectada() ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                    {{ $pdv->estaConectada() ? 'En línea' : 'Última conexión '.$pdv->ultima_conexion_at->diffForHumans() }}
                                </p>
                            @endif
                        </td>
                        <td class="px-6 py-4 align-top">
                            @if($s = $salud[$pdv->id] ?? null)
                                @php
                                    [$badgeClases, $puntoClase, $label] = \App\Support\SaludCaja::estilo($s['nivel']);
                                    $estado = $pdv->estado_caja ?? [];
                                @endphp
                                <a href="{{ route('auditoria-sincronizacion.index') }}" wire:navigate
                                   class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeClases }} {{ $s['nivel'] !== 'ok' ? 'hover:opacity-80' : '' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $puntoClase }}"></span>
                                    {{ $label }}
                                </a>
                                @if(count($s['problemas']) > 0)
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ $s['problemas'][0]['texto'] }}
                                        @if(count($s['problemas']) > 1)
                                            , +{{ count($s['problemas']) - 1 }} más
                                        @endif
                                    </p>
                                @endif
                                @if($s['nivel'] !== 'sin_datos' && $estado)
                                    <p class="mt-1 text-xs text-gray-400">
                                        {{ $estado['ventas_pendientes'] ?? 0 }} sin enviar ·
                                        {{ $estado['facturas_pendientes'] ?? 0 }} fact. pendientes
                                        @if($estado['turno_abierto'] ?? null)
                                            · caja abierta ({{ $estado['turno_abierto']['cajero'] ?? '' }})
                                        @endif
                                    </p>
                                @endif
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button type="button" wire:click="toggleActive({{ $pdv->id }})"
                                    @disabled(! $puedeInstalar)
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed {{ $pdv->activo ? 'bg-blue-600' : 'bg-gray-300' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform {{ $pdv->activo ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-1">
                                @if($pdv->instalado_at && $puedeMandarOrdenes)
                                    {{-- Modal con position:fixed y no un dropdown absoluto: el contenedor
                                         de la tabla tiene overflow-hidden y recortaba el desplegable. --}}
                                    <div x-data="{ abierto: false }">
                                        <button type="button" @click="abierto = true"
                                                title="Mandarle una orden a esta caja para repararla a distancia"
                                                class="px-2.5 py-1 text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors">
                                            Reparar
                                        </button>

                                        {{-- :style y no x-show: x-show escribe sobre la propiedad display
                                             y borraría el flex que centra el modal. --}}
                                        <div @click.self="abierto = false"
                                             style="display:none"
                                             :style="abierto ? 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center' : 'display:none'">
                                            <div class="bg-white rounded-xl shadow-2xl w-full mx-4 max-w-md text-left" @click.stop>
                                                <div class="px-5 py-4 border-b border-gray-200">
                                                    <h3 class="text-base font-semibold text-gray-900">Reparar "{{ $pdv->nombre }}"</h3>
                                                    <p class="text-xs text-gray-500 mt-0.5">
                                                        La caja consulta cada minuto y ejecuta la orden sola. No hace falta ir hasta ahí.
                                                    </p>
                                                </div>
                                                <div class="py-1 max-h-96 overflow-y-auto">
                                                    @foreach($comandosDisponibles as $comando)
                                                        <button type="button"
                                                                wire:click="enviarComando({{ $pdv->id }}, '{{ $comando->value }}')"
                                                                @click="abierto = false"
                                                                class="w-full text-left px-5 py-2.5 hover:bg-gray-50 transition-colors">
                                                            <span class="block text-sm font-medium text-gray-900">{{ $comando->label() }}</span>
                                                            <span class="block text-xs text-gray-500">{{ $comando->descripcion() }}</span>
                                                        </button>
                                                    @endforeach
                                                </div>
                                                <div class="px-5 py-3 border-t border-gray-200 bg-gray-50 text-right">
                                                    <button type="button" @click="abierto = false"
                                                            class="px-4 py-1.5 text-sm font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-lg transition-colors">
                                                        Cancelar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                @if($puedeInstalar)
                                <button type="button" wire:click="generarCodigoInstalacion({{ $pdv->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="generarCodigoInstalacion({{ $pdv->id }})"
                                        title="Genera el código que hay que tipear en la máquina de la caja"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-60">
                                    <svg wire:loading wire:target="generarCodigoInstalacion({{ $pdv->id }})"
                                         class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="generarCodigoInstalacion({{ $pdv->id }})">Código</span>
                                    <span wire:loading wire:target="generarCodigoInstalacion({{ $pdv->id }})">Generando</span>
                                </button>
                                <button type="button"
                                        @click="confirm('¿Regenerar el secret de \'{{ $pdv->nombre }}\'?\nEl secret anterior dejará de funcionar.') && $wire.regenerarSecret({{ $pdv->id }})"
                                        class="px-2.5 py-1 text-xs font-medium text-yellow-700 bg-yellow-50 hover:bg-yellow-100 rounded-lg transition-colors">
                                    Secret
                                </button>
                                <button type="button" wire:click="revocarTokens({{ $pdv->id }})"
                                        wire:confirm="¿Revocar todos los tokens de '{{ $pdv->nombre }}'?"
                                        class="px-2.5 py-1 text-xs font-medium text-orange-700 bg-orange-50 hover:bg-orange-100 rounded-lg transition-colors">
                                    Revocar
                                </button>
                                <button type="button" wire:click="delete({{ $pdv->id }})"
                                        wire:confirm="¿Eliminar el punto de venta '{{ $pdv->nombre }}'?"
                                        class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                                @else
                                    <span class="text-xs text-gray-400 italic">Sin permisos</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-400 italic">
                            No hay puntos de venta creados todavía
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
