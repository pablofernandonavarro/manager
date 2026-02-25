<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Stock por Sucursal</h1>
            <p class="mt-2 text-sm text-gray-700">Visualizá el stock disponible en cada sucursal</p>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-green-50 p-4 border-l-4 border-green-400">
            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
        </div>
    @endif

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Sucursal</label>
                <select wire:model.live="sucursalSeleccionada"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    @foreach($sucursales as $sucursal)
                        <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}{{ $sucursal->isCentral() ? ' ★' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Buscar producto</label>
                <input type="text" wire:model.live.debounce.300ms="busqueda"
                       placeholder="Nombre, código interno o código de barras..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Resumen</label>
                <div class="flex gap-4">
                    <div class="flex-1 bg-blue-50 rounded-lg p-3">
                        <div class="text-xs text-blue-600 font-medium">Total unidades</div>
                        <div class="text-2xl font-bold text-blue-900 mt-1">{{ number_format($stockTotal) }}</div>
                    </div>
                    <div class="flex-1 bg-green-50 rounded-lg p-3">
                        <div class="text-xs text-green-600 font-medium">Productos c/stock</div>
                        <div class="text-2xl font-bold text-green-900 mt-1">{{ number_format($productosConStock) }}</div>
                    </div>
                </div>
            </div>
        </div>

        @if($esCentral)
            <div class="mt-4 flex items-center gap-2 text-sm text-indigo-700 bg-indigo-50 rounded-lg px-4 py-2.5">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
                <span>Modo <strong>Central</strong>: usá el botón <strong>Enviar</strong> para crear un remito hacia las sucursales.</span>
            </div>
        @endif
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Stock Sucursal</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Stock Global</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Marca</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Línea</th>
                        @if($esCentral)
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Remito</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($productos as $producto)
                        <tr wire:key="producto-{{ $producto->id }}" class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ $producto->nombre }}</div>
                                @if($producto->descripcion)
                                    <div class="text-xs text-gray-500 mt-0.5">{{ Str::limit($producto->descripcion, 50) }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                @if($producto->codigo_interno)
                                    <span class="block">CI: {{ $producto->codigo_interno }}</span>
                                @endif
                                @if($producto->codigo_barras)
                                    <span class="block text-xs text-gray-500">CB: {{ $producto->codigo_barras }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $producto->stock_sucursal > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ number_format($producto->stock_sucursal) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button wire:click="abrirDetalle({{ $producto->id }}, '{{ addslashes($producto->nombre) }}')"
                                        class="text-sm font-medium text-blue-700 hover:underline">
                                    {{ number_format($producto->stock) }}
                                </button>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $producto->marca?->nombre ?? '-' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $producto->linea?->nombre ?? '-' }}</td>
                            @if($esCentral)
                                <td class="px-6 py-4 text-center">
                                    @if($producto->stock_sucursal > 0)
                                        <button type="button" wire:click="abrirRemito({{ $producto->id }}, '{{ addslashes($producto->nombre) }}', {{ $producto->stock_sucursal }})"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                            </svg>
                                            Enviar
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $esCentral ? 7 : 6 }}" class="px-6 py-10 text-center text-sm text-gray-400 italic">
                                No se encontraron productos
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-gray-200">{{ $productos->links() }}</div>
    </div>

    <!-- Modal: Desglose por sucursal -->
    @if($detalleProductoId)
        <div style="position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center"
             wire:click.self="cerrarDetalle">
            <div class="bg-white rounded-xl shadow-2xl w-full mx-4 max-w-md" @click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Desglose por sucursal</h3>
                        <p class="text-sm text-gray-500 mt-0.5">{{ $detalleProductoNombre }}</p>
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
                            <span class="px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-800">
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
                @if($detalleSucursales->isNotEmpty() || $remitosEnTransito->isNotEmpty())
                    <div class="px-6 py-3 bg-gray-50 rounded-b-xl border-t border-gray-200">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Total global</span>
                            <span class="text-lg font-bold text-gray-900">
                                {{ number_format($detalleSucursales->sum('cantidad') + $remitosEnTransito->sum('cantidad')) }}
                            </span>
                        </div>
                        @if($remitosEnTransito->isNotEmpty())
                            <p class="text-xs text-yellow-600 mt-1 text-right">
                                Incluye {{ number_format($remitosEnTransito->sum('cantidad')) }} en tránsito
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Modal: Crear remito (estilo Excel) -->
    @if($remitoProductoId)
        <div style="position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center"
             wire:click.self="cerrarRemito">
            <div class="bg-white rounded-xl shadow-2xl w-full mx-4 max-w-lg" @click.stop>
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Crear remito</h3>
                        <p class="text-sm text-gray-500 mt-0.5 truncate max-w-sm">{{ $remitoProductoNombre }}</p>
                    </div>
                    <button wire:click="cerrarRemito" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Stock disponible en Central -->
                <div class="px-6 pt-4">
                    <div class="flex items-center justify-between bg-indigo-50 rounded-lg px-4 py-3">
                        <span class="text-sm font-medium text-indigo-700">Disponible en Central</span>
                        <span class="text-xl font-bold text-indigo-900">{{ number_format($remitoStockDisponible) }}</span>
                    </div>
                </div>

                <!-- Tabla Excel-style: sucursal | stock actual | en tránsito | a enviar -->
                <div class="px-6 py-4">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2 border-gray-200">
                                <th class="pb-2 text-left font-semibold text-gray-600 text-xs uppercase">Sucursal</th>
                                <th class="pb-2 text-center font-semibold text-gray-600 text-xs uppercase w-24">Stock actual</th>
                                <th class="pb-2 text-center font-semibold text-yellow-600 text-xs uppercase w-24">En tránsito</th>
                                <th class="pb-2 text-center font-semibold text-gray-600 text-xs uppercase w-32">Cantidad a enviar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($sucursalesDestino as $suc)
                                <tr>
                                    <td class="py-2.5 text-gray-800 font-medium">{{ $suc->nombre }}</td>
                                    <td class="py-2.5 text-center">
                                        <span class="text-sm {{ ($stockPorSucursal[$suc->id] ?? 0) > 0 ? 'text-green-700 font-semibold' : 'text-gray-400' }}">
                                            {{ number_format($stockPorSucursal[$suc->id] ?? 0) }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 text-center">
                                        @if(($transitoPorSucursal[$suc->id] ?? 0) > 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">
                                                {{ number_format($transitoPorSucursal[$suc->id]) }}
                                            </span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 text-center">
                                        <input type="number"
                                               wire:model="remitoCantidades.{{ $suc->id }}"
                                               min="0"
                                               max="{{ $remitoStockDisponible }}"
                                               placeholder="0"
                                               class="w-24 px-2 py-1.5 border border-gray-300 rounded text-sm text-center focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-200">
                                <td colspan="3" class="pt-3 text-sm font-semibold text-gray-700">Total a enviar</td>
                                <td class="pt-3 text-center">
                                    <span class="text-sm font-bold text-indigo-700">
                                        {{ collect($remitoCantidades)->sum(fn($v) => max(0, (int)$v)) }}
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if($remitoError)
                    <div class="mx-6 mb-4 flex items-center gap-2 text-sm text-red-700 bg-red-50 rounded-lg px-4 py-3">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ $remitoError }}
                    </div>
                @endif

                <!-- Footer -->
                <div class="px-6 py-4 bg-gray-50 rounded-b-xl flex justify-end gap-3 border-t border-gray-200">
                    <button wire:click="cerrarRemito"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="button" wire:click="confirmarRemito"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Confirmar remito
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
