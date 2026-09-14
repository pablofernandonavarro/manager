<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Stock de Productos</h1>
            <p class="mt-2 text-sm text-gray-700">Stock total consolidado de todas las sucursales</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Buscar producto</label>
                <input type="text" wire:model.live.debounce.300ms="busqueda"
                       placeholder="Nombre, código interno o código de barras..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">

                <div class="mt-3 flex gap-5">
                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" wire:model.live="soloConStock"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Solo con stock
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" wire:model.live="soloCriticos"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Solo stock crítico
                    </label>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Resumen</label>
                <div class="flex gap-4">
                    <div class="flex-1 bg-blue-50 rounded-lg p-3">
                        <div class="text-xs text-blue-600 font-medium">Total unidades</div>
                        <div class="text-2xl font-bold text-blue-900 mt-1">{{ number_format($unidadesTotales) }}</div>
                    </div>
                    <div class="flex-1 bg-green-50 rounded-lg p-3">
                        <div class="text-xs text-green-600 font-medium">Productos c/stock</div>
                        <div class="text-2xl font-bold text-green-900 mt-1">{{ number_format($productosConStock) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Stock total</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Sucursales</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Crítico</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($productos as $producto)
                        @php
                            $esCritico = $producto->stock_total > 0 && $producto->stock_total <= $producto->stock_critico;
                            $sinStock = $producto->stock_total <= 0;
                        @endphp
                        <tr class="hover:bg-gray-50 cursor-pointer"
                            wire:click="abrirDetalle({{ $producto->id }}, '{{ addslashes($producto->nombre) }}', '{{ addslashes($producto->codigo_interno ?? '') }}')">
                            <td class="px-6 py-4 text-sm font-mono text-gray-600">{{ $producto->codigo_interno ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $producto->nombre }}</td>
                            <td class="px-6 py-4 text-center">
                                <span @class([
                                    'px-3 py-1 rounded-full text-sm font-semibold',
                                    'bg-gray-100 text-gray-500' => $sinStock,
                                    'bg-yellow-100 text-yellow-800' => $esCritico,
                                    'bg-green-100 text-green-800' => ! $sinStock && ! $esCritico,
                                ])>
                                    {{ number_format($producto->stock_total) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-center text-gray-600">
                                {{ $producto->sucursales_con_stock }}
                            </td>
                            <td class="px-6 py-4 text-sm text-center text-gray-500">
                                {{ number_format($producto->stock_critico) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="text-blue-600 text-sm font-medium">Ver desglose →</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-400 italic">
                                No se encontraron productos
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-gray-200">{{ $productos->links() }}</div>
    </div>

    <!-- Modal: desglose por sucursal -->
    @if($detalleProductoId)
        <div style="position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center"
             wire:click.self="cerrarDetalle">
            <div class="bg-white rounded-xl shadow-2xl w-full mx-4 max-w-lg" @click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Desglose por sucursal</h3>
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

                <div class="px-6 py-4 space-y-1 max-h-96 overflow-y-auto">
                    @forelse($detalleSucursales as $detalle)
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-800">{{ $detalle->sucursal->nombre }}</span>
                                @if($detalle->sucursal->isCentral())
                                    <span class="px-1.5 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">Central</span>
                                @endif
                            </div>
                            <span @class([
                                'px-3 py-1 rounded-full text-sm font-semibold',
                                'bg-gray-100 text-gray-500' => $detalle->cantidad <= 0,
                                'bg-green-100 text-green-800' => $detalle->cantidad > 0,
                            ])>
                                {{ number_format($detalle->cantidad) }}
                            </span>
                        </div>
                    @empty
                        <p class="py-4 text-center text-sm text-gray-400">Sin stock en ninguna sucursal</p>
                    @endforelse

                    @if($remitosEnTransito->isNotEmpty())
                        <div class="pt-3 pb-1">
                            <p class="text-xs font-semibold text-yellow-700 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                En tránsito (pendiente de recepción)
                            </p>
                            @foreach($remitosEnTransito as $transito)
                                <div class="flex items-center justify-between py-2 border-b border-yellow-100 last:border-0">
                                    <span class="text-sm text-gray-600">→ {{ $transito->sucursal->nombre }}</span>
                                    <span class="px-3 py-1 rounded-full text-sm font-semibold bg-yellow-100 text-yellow-800">
                                        {{ number_format($transito->cantidad) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="px-6 py-3 border-t border-gray-200 bg-gray-50 flex items-center justify-between">
                    <span class="text-sm text-gray-600">Total en sucursales</span>
                    <span class="text-base font-bold text-gray-900">
                        {{ number_format($detalleSucursales->sum('cantidad')) }}
                    </span>
                </div>
            </div>
        </div>
    @endif
</div>
