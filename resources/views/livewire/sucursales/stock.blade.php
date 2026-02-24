<div class="space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Stock por Sucursal</h1>
            <p class="mt-2 text-sm text-gray-700">Visualizá el stock disponible en cada sucursal</p>
        </div>
    </div>

    <!-- Filtros y estadísticas -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Selector de sucursal -->
            <div class="lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">Sucursal</label>
                <select wire:model.live="sucursalSeleccionada"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    @foreach($sucursales as $sucursal)
                        <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Búsqueda -->
            <div class="lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">Buscar producto</label>
                <input type="text"
                       wire:model.live.debounce.300ms="busqueda"
                       placeholder="Nombre, código interno o código de barras..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <!-- Estadísticas -->
            <div class="lg:col-span-1">
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
    </div>

    <!-- Tabla de productos -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Stock Sucursal</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Stock Global</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marca</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Línea</th>
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
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">
                                    @if($producto->codigo_interno)
                                        <span class="block">CI: {{ $producto->codigo_interno }}</span>
                                    @endif
                                    @if($producto->codigo_barras)
                                        <span class="block text-xs text-gray-500">CB: {{ $producto->codigo_barras }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $producto->stock_sucursal > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ number_format($producto->stock_sucursal) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-sm font-medium text-gray-700">
                                    {{ number_format($producto->stock) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm text-gray-900">{{ $producto->marca?->nombre ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm text-gray-900">{{ $producto->linea?->nombre ?? '-' }}</span>
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

        <!-- Paginación -->
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $productos->links() }}
        </div>
    </div>

    <!-- Información adicional -->
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div class="flex">
            <svg class="w-5 h-5 text-yellow-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="text-sm text-yellow-700">
                <p class="font-medium mb-1">Información:</p>
                <ul class="list-disc list-inside space-y-1 text-xs">
                    <li><strong>Stock Sucursal:</strong> Cantidad disponible en la sucursal seleccionada</li>
                    <li><strong>Stock Global:</strong> Suma de stock en todas las sucursales</li>
                    <li>Los movimientos de stock se registran desde el POS de cada sucursal</li>
                    <li>Para ajustar el stock, usá la funcionalidad de stock del POS correspondiente</li>
                </ul>
            </div>
        </div>
    </div>
</div>
