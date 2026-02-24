<div class="h-screen flex flex-col bg-gray-50">
    <!-- Header Fijo -->
    <div class="bg-white border-b border-gray-200 shadow-sm">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-4">
                    <a href="{{ route('listas-precios.index') }}" class="text-gray-600 hover:text-gray-900">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">{{ $lista->nombre }}</h1>
                        <p class="text-sm text-gray-600">Buscador de Precios • Factor: {{ number_format($lista->factor, 2) }}x</p>
                    </div>
                </div>
            </div>

            <!-- Barra de búsqueda y filtros -->
            <div class="flex gap-3">
                <!-- Búsqueda -->
                <div class="flex-1 relative">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="busqueda"
                        placeholder="Buscar por nombre, código interno o código de barras..."
                        class="w-full px-4 py-3 pl-12 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        autofocus
                    >
                    <svg class="absolute left-4 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>

                <!-- Filtro Solo con Stock -->
                <button
                    wire:click="$toggle('soloConStock')"
                    class="px-4 py-3 rounded-lg font-medium transition-colors whitespace-nowrap {{ $soloConStock ? 'bg-green-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50' }}"
                >
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path>
                        </svg>
                        Solo con stock
                    </div>
                </button>

                <!-- Ordenar -->
                <select
                    wire:model.live="ordenar"
                    class="px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="nombre">Por Nombre</option>
                    <option value="codigo_interno">Por Código</option>
                    <option value="precio">Por Precio</option>
                    <option value="stock">Por Stock</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Tabla de Resultados -->
    <div class="flex-1 overflow-auto">
        <table class="w-full text-sm border-collapse bg-white">
            <thead class="bg-gray-100 sticky top-0 z-10 border-b-2 border-gray-300">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300 w-28">Código</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300">Producto</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300 w-24">Stock</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300 w-32">Precio Base</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300 w-32 bg-blue-50">Precio Lista</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300 w-28">Diferencia</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider w-24">% Margen</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($productos as $producto)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 text-gray-900 font-mono text-xs border-r border-gray-200">
                            {{ $producto['codigo'] ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-gray-900 border-r border-gray-200">
                            {{ $producto['nombre'] }}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold border-r border-gray-200 {{ $producto['stock'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format($producto['stock']) }}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-700 border-r border-gray-200">
                            ${{ number_format($producto['precio_base'], 2) }}
                        </td>
                        <td class="px-4 py-3 text-right font-bold text-blue-700 border-r border-gray-200 bg-blue-50">
                            ${{ number_format($producto['precio_lista'], 2) }}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold border-r border-gray-200 {{ $producto['diferencia'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $producto['diferencia'] >= 0 ? '+' : '' }}${{ number_format($producto['diferencia'], 2) }}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold {{ $producto['porcentaje'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $producto['porcentaje'] >= 0 ? '+' : '' }}{{ number_format($producto['porcentaje'], 1) }}%
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center">
                            <div class="text-gray-400">
                                <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                <p class="text-lg font-medium">No se encontraron productos</p>
                                <p class="text-sm">Intenta ajustar los filtros de búsqueda</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Footer con Paginación -->
    <div class="bg-white border-t border-gray-200 px-4 py-3">
        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-600">
                Mostrando {{ $productos->count() }} de {{ $productos->total() }} resultados
            </div>
            {{ $productos->links() }}
        </div>
    </div>
</div>
