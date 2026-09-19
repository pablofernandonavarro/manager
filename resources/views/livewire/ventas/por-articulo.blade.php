@php
    $zona = config('app.display_timezone');
@endphp

<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Ventas por Artículo</h1>
            <p class="mt-2 text-sm text-gray-700">Qué se vendió, cuánto y en qué sucursal</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Buscar artículo</label>
                <input type="text" wire:model.live.debounce.300ms="busqueda"
                       placeholder="Nombre, código interno o código de barras..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Sucursal</label>
                <select wire:model.live="sucursalSeleccionada"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">Todas</option>
                    @foreach($sucursales as $sucursal)
                        <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Período</label>
                <div class="flex gap-2">
                    <input type="date" wire:model.live="desde"
                           class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <input type="date" wire:model.live="hasta"
                           class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between">
            <div class="flex gap-4">
                <div class="bg-blue-50 rounded-lg px-4 py-2">
                    <div class="text-xs text-blue-600 font-medium">Unidades vendidas</div>
                    <div class="text-2xl font-bold text-blue-900">{{ number_format($totales->unidades) }}</div>
                </div>
                <div class="bg-green-50 rounded-lg px-4 py-2">
                    <div class="text-xs text-green-600 font-medium">Facturado</div>
                    <div class="text-2xl font-bold text-green-900">${{ number_format($totales->facturado, 2, ',', '.') }}</div>
                </div>
                <div class="bg-gray-50 rounded-lg px-4 py-2">
                    <div class="text-xs text-gray-600 font-medium">Ventas</div>
                    <div class="text-2xl font-bold text-gray-900">{{ number_format($totales->ventas) }}</div>
                </div>
            </div>

            @if($busqueda || $sucursalSeleccionada || $desde || $hasta)
                <button wire:click="limpiarFiltros" class="text-sm text-gray-500 hover:text-gray-700 underline">
                    Limpiar filtros
                </button>
            @endif
        </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Artículo</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Unidades</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Facturado</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ventas</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Sucursales</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Última venta</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($articulos as $articulo)
                        <tr class="hover:bg-gray-50 cursor-pointer"
                            wire:click="abrirDetalle({{ $articulo->id }}, '{{ addslashes($articulo->nombre) }}', '{{ addslashes($articulo->codigo_interno ?? '') }}')">
                            <td class="px-6 py-4 text-sm font-mono text-gray-600">{{ $articulo->codigo_interno ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $articulo->nombre }}</td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-800">
                                    {{ number_format($articulo->unidades) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-semibold text-gray-900">
                                ${{ number_format($articulo->facturado, 2, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-sm text-center text-gray-600">{{ $articulo->cantidad_ventas }}</td>
                            <td class="px-6 py-4 text-sm text-center text-gray-600">{{ $articulo->cantidad_sucursales }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ \Carbon\Carbon::parse($articulo->ultima_venta)->timezone($zona)->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="text-blue-600 text-sm font-medium">Ver dónde →</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-10 text-center text-sm text-gray-400 italic">
                                No hay ventas registradas con esos filtros
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-gray-200">{{ $articulos->links() }}</div>
    </div>

    <!-- Modal: dónde se vendió -->
    @if($detalleProductoId)
        <div style="position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center"
             wire:click.self="cerrarDetalle">
            <div class="bg-white rounded-xl shadow-2xl w-full mx-4 max-w-5xl" @click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Dónde se vendió</h3>
                        <p class="text-sm text-gray-500 mt-0.5">
                            @if($detalleProductoCodigo)
                                <span class="font-mono">{{ $detalleProductoCodigo }}</span> ·
                            @endif
                            {{ $detalleProductoNombre }}
                        </p>
                    </div>
                    <button wire:click="cerrarDetalle" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="max-h-[28rem] overflow-y-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Venta</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sucursal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Caja</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Cant.</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Precio</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse($lineas as $linea)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-3 text-sm text-gray-600 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($linea->fecha)->timezone($zona)->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-6 py-3 text-sm font-mono text-gray-700">{{ $linea->numero_venta ?? '—' }}</td>
                                    <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $linea->sucursal ?? '—' }}</td>
                                    <td class="px-6 py-3 text-sm text-gray-600">{{ $linea->punto_de_venta ?? '—' }}</td>
                                    <td class="px-6 py-3 text-center">
                                        <span class="px-2.5 py-0.5 rounded-full text-sm font-semibold bg-blue-100 text-blue-800">
                                            {{ number_format($linea->cantidad) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-right text-gray-600">
                                        ${{ number_format($linea->precio_unitario, 2, ',', '.') }}
                                    </td>
                                    <td class="px-6 py-3 text-sm text-right font-semibold text-gray-900">
                                        ${{ number_format($linea->subtotal, 2, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-400 italic">
                                        Sin ventas para este artículo
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3 border-t border-gray-200 bg-gray-50 flex items-center justify-between text-sm">
                    <span class="text-gray-600">
                        {{ $lineas->count() }} {{ $lineas->count() === 1 ? 'línea' : 'líneas' }} ·
                        {{ number_format($lineas->sum('cantidad')) }} unidades
                    </span>
                    <span class="font-semibold text-gray-900">
                        ${{ number_format($lineas->sum('subtotal'), 2, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>
    @endif
</div>
