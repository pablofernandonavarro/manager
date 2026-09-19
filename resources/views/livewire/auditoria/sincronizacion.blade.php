@php
    $puedeMandarOrdenes = auth()->user()->can('terminales.comandos');
    // Con la pestaña oculta Livewire casi no hace poll: al volver se refresca de inmediato, salvo
    // que haya estado oculta muy poco (cada refresco recorre todas las cajas).
    $intervaloPoll = $hayComandoEnCurso ? 3 : ($cajasEnProceso > 0 ? 10 : 30);
@endphp

<div class="space-y-6" x-data="{ ocultaDesde: null }" x-on:visibilitychange.document="if (document.hidden) { ocultaDesde = Date.now() } else if (Date.now() - (ocultaDesde ?? 0) > 15000) { $wire.$refresh() }">
    {{-- El poll va en un hijo con clave según el intervalo: al cambiar de intervalo Livewire reemplaza
         el elemento y detiene el poll anterior. Cambiar el atributo en la raíz los acumulaba. --}}
    <div wire:key="poll-{{ $intervaloPoll }}" wire:poll.{{ $intervaloPoll }}s class="hidden"></div>

    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Auditoría de sincronización</h1>
            <p class="mt-2 text-sm text-gray-700">Estado en vivo de cada caja: qué le falta enviar, hace cuánto sincronizó y si necesita atención.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    <!-- Resumen -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Con problemas</p>
            <p class="mt-1 text-2xl font-bold text-red-600">{{ $cajasCriticas }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">A revisar</p>
            <p class="mt-1 text-2xl font-bold text-amber-600">{{ $cajasEnAlerta }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">En proceso</p>
            <p class="mt-1 text-2xl font-bold text-blue-600">{{ $cajasEnProceso }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Al día</p>
            <p class="mt-1 text-2xl font-bold text-green-600">{{ $cajasOk }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Sin datos</p>
            <p class="mt-1 text-2xl font-bold text-gray-500">{{ $cajasSinDatos }}</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-wrap items-center gap-3">
        <select wire:model.live="sucursalId" class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Todas las sucursales</option>
            @foreach($listaSucursales as $s)
                <option value="{{ $s->id }}">{{ $s->nombre }}</option>
            @endforeach
        </select>
        <select wire:model.live="nivel" class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Cualquier estado</option>
            <option value="problemas">Con problemas o a revisar</option>
            <option value="ok">Al día</option>
            <option value="sin_datos">Sin datos</option>
        </select>
    </div>

    <!-- Cajas -->
    <div class="space-y-4">
        @forelse($filas as $fila)
            @php
                $pdv = $fila['pdv'];
                $s = $fila['salud'];
                $estado = $pdv->estado_caja ?? [];
                [$badgeClases, $puntoClase, $label] = \App\Support\SaludCaja::estilo($s['nivel']);
            @endphp
            <div wire:key="auditoria-{{ $pdv->id }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-500">{{ $pdv->sucursal->nombre }}</span>
                            <span class="text-gray-300">·</span>
                            <span class="text-sm font-semibold text-gray-900">{{ $pdv->nombre }}</span>
                        </div>
                        <span class="mt-1 inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeClases }}">
                            @if($s['nivel'] === 'en_proceso')
                                <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            @else
                                <span class="w-1.5 h-1.5 rounded-full {{ $puntoClase }}"></span>
                            @endif
                            {{ $label }}
                        </span>
                    </div>

                    @if($puedeMandarOrdenes && ($estado['jobs_fallidos'] ?? 0) > 0)
                        @if($pdv->ultimoComando?->comando === \App\Enums\ComandoPos::LimpiarFallidos && $pdv->ultimoComando->estado !== 'completado')
                            <span class="text-xs text-gray-500">
                                @if($pdv->ultimoComando->estado === 'fallido') ✕ falló la limpieza
                                @else Limpieza en camino…
                                @endif
                            </span>
                        @else
                            <button type="button" wire:click="limpiarFallidos({{ $pdv->id }})" wire:confirm="¿Limpiar los envíos fallidos de {{ $pdv->nombre }}? No se pierde nada: lo pendiente de verdad se reenvía solo."
                                    class="px-3 py-1.5 text-xs font-medium text-white bg-amber-600 rounded-lg hover:bg-amber-700 transition-colors">
                                Limpiar envíos fallidos
                            </button>
                        @endif
                    @elseif($pdv->ultimoComando?->comando === \App\Enums\ComandoPos::LimpiarFallidos && $pdv->ultimoComando->estado === 'completado')
                        <span class="text-xs text-green-700" title="{{ $pdv->ultimoComando->resultado }}">
                            ✓ Limpiado {{ $pdv->ultimoComando->finalizado_at?->diffForHumans() }}
                        </span>
                    @endif
                </div>

                @if(count($s['problemas']) > 0)
                    <ul class="mt-3 space-y-1">
                        @foreach($s['problemas'] as $problema)
                            <li class="text-sm {{ $problema['nivel'] === 'critico' ? 'text-red-700' : ($problema['nivel'] === 'alerta' ? 'text-amber-700' : 'text-gray-500') }}">
                                {{ $problema['texto'] }}
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if($s['nivel'] !== 'sin_datos' && $estado)
                    <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                        <div>
                            <p class="text-xs text-gray-500">Stock</p>
                            <p class="text-sm font-medium text-gray-900">
                                @if(collect($s['problemas'])->contains('nivel', 'en_proceso'))
                                    <span class="text-blue-700">Actualizando…</span>
                                @else
                                    {{ isset($estado['ultima_sincronizacion_stock']) ? \Carbon\Carbon::parse($estado['ultima_sincronizacion_stock'])->diffForHumans() : 'Nunca' }}
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Catálogo</p>
                            <p class="text-sm font-medium text-gray-900">
                                @if($estado['catalogo_pendiente'] ?? false)
                                    <span class="text-amber-700">Bajando…</span>
                                @elseif(isset($estado['ultima_sincronizacion_productos']))
                                    {{ \Carbon\Carbon::parse($estado['ultima_sincronizacion_productos'])->diffForHumans() }}
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Ventas sin enviar</p>
                            <p class="text-sm font-medium {{ ($estado['ventas_pendientes'] ?? 0) > 0 ? 'text-amber-700' : 'text-gray-900' }}">
                                {{ $estado['ventas_pendientes'] ?? 0 }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Movimientos sin enviar</p>
                            <p class="text-sm font-medium {{ ($estado['movimientos_pendientes'] ?? 0) > 0 ? 'text-amber-700' : 'text-gray-900' }}">
                                {{ $estado['movimientos_pendientes'] ?? 0 }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Devoluciones sin enviar</p>
                            <p class="text-sm font-medium {{ ($estado['devoluciones_pendientes'] ?? 0) > 0 ? 'text-amber-700' : 'text-gray-900' }}">
                                {{ $estado['devoluciones_pendientes'] ?? 0 }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Facturas pendientes</p>
                            <p class="text-sm font-medium {{ ($estado['facturas_pendientes'] ?? 0) > 0 ? 'text-amber-700' : 'text-gray-900' }}">
                                {{ $estado['facturas_pendientes'] ?? 0 }}
                                @if(($estado['facturas_rechazadas'] ?? 0) > 0)
                                    <span class="text-red-600">· {{ $estado['facturas_rechazadas'] }} rechazada(s)</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Cola de trabajos</p>
                            <p class="text-sm font-medium {{ ($estado['jobs_en_cola'] ?? 0) > 20 ? 'text-amber-700' : 'text-gray-900' }}">
                                {{ $estado['jobs_en_cola'] ?? 0 }} en cola
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Envíos fallidos</p>
                            <p class="text-sm font-medium {{ ($estado['jobs_fallidos'] ?? 0) > 0 ? 'text-red-700' : 'text-gray-900' }}">
                                {{ $estado['jobs_fallidos'] ?? 0 }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Turno</p>
                            <p class="text-sm font-medium text-gray-900">
                                @if($turno = $estado['turno_abierto'] ?? null)
                                    Abierto ({{ $turno['cajero'] ?? '' }})
                                @else
                                    Cerrado
                                @endif
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-sm text-gray-500">
                No hay cajas instaladas que coincidan con el filtro.
            </div>
        @endforelse
    </div>
</div>
