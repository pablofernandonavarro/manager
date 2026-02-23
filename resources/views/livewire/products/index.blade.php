<div class="space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Gestión de Productos</h1>
            <p class="mt-2 text-sm text-gray-700">Administra el catálogo de productos del sistema</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="/productos/crear" wire:navigate
               class="inline-flex items-center justify-center px-4 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-sm transition-all duration-200">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nuevo Producto
            </a>
        </div>
    </div>

    <!-- Alerts -->
    @if (session()->has('success'))
        <div class="rounded-lg bg-green-50 p-4 border-l-4 border-green-400">
            <div class="flex">
                <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="ml-3 text-sm font-medium text-green-800">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-5">
            <div class="sm:col-span-2">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Buscar producto</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por nombre, código, código de barras..."
                           class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                </div>
            </div>
            <div>
                <label for="filterTipo" class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                <select wire:model.live="filterTipo"
                        class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    <option value="todos">Todos</option>
                    <option value="simple">✅ Simple</option>
                    <option value="configurable">📦 Configurable</option>
                </select>
            </div>
            <div>
                <label for="filterEstado" class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                <select wire:model.live="filterEstado"
                        class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    <option value="todos">Todos</option>
                    <option value="1">Activos</option>
                    <option value="0">Inactivos</option>
                </select>
            </div>
            <div>
                <label for="filterStock" class="block text-sm font-medium text-gray-700 mb-2">Stock</label>
                <select wire:model.live="filterStock"
                        class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    <option value="todos">Todos</option>
                    <option value="disponible">Con stock</option>
                    <option value="critico">Stock crítico</option>
                    <option value="sin_stock">Sin stock</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Código</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Producto</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Tipo</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Precio</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Stock</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Estado</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($products as $product)
                        <tr class="hover:bg-gray-50 transition-colors duration-150 {{ $product->trashed() ? 'bg-red-50/50' : '' }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 font-mono">{{ $product->codigo_interno ?? '-' }}</div>
                                @if($product->codigo_barras)
                                    <div class="text-xs text-gray-500 font-mono">{{ $product->codigo_barras }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-middle">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 h-16 w-16 border border-gray-300 bg-white p-0.5">
                                        @if($product->imagen)
                                            <img src="{{ asset('storage/' . $product->imagen) }}"
                                                 alt="{{ $product->nombre }}"
                                                 class="h-full w-full object-contain max-h-16">
                                        @else
                                            <div class="h-full w-full bg-white flex items-center justify-center border border-dashed border-gray-300">
                                                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <div class="text-sm font-medium text-gray-900">{{ $product->nombre }}</div>
                                            @if($product->trashed())
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700 flex-shrink-0">
                                                    Eliminado
                                                </span>
                                            @endif
                                        </div>
                                        @if($product->color)
                                            <div class="text-xs text-gray-600">Color: {{ $product->color }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($product->isSimple())
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 ring-1 ring-green-600/20">
                                        ✅ Simple
                                    </span>
                                    @if($product->parent_id)
                                        <div class="text-xs text-gray-500 mt-1">Variante</div>
                                    @endif
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 ring-1 ring-blue-600/20">
                                        📦 Config
                                    </span>
                                    @if($product->variants->count() > 0)
                                        <div class="text-xs text-gray-500 mt-1">{{ $product->variants->count() }} vars</div>
                                    @endif
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-900">
                                    ${{ number_format($product->precio ?? 0, 2) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($product->stock <= 0)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700 ring-1 ring-red-600/20">
                                        Sin stock
                                    </span>
                                @elseif($product->isCriticalStock())
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700 ring-1 ring-amber-600/20">
                                        {{ $product->stock }} (Crítico)
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700 ring-1 ring-green-600/20">
                                        {{ $product->stock }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if(!$product->trashed())
                                    <button wire:click="toggleEstado({{ $product->id }})"
                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 {{ $product->estado ? 'bg-blue-600' : 'bg-gray-200' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $product->estado ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </button>
                                @else
                                    <span class="text-sm text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    @if($product->trashed())
                                        <button wire:click="restore({{ $product->id }})"
                                                wire:confirm="¿Estás seguro de restaurar este producto?"
                                                class="inline-flex items-center p-2 text-green-600 hover:text-green-900 hover:bg-green-50 rounded-lg transition-all"
                                                title="Restaurar producto">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                            </svg>
                                        </button>
                                    @else
                                        <a href="/productos/{{ $product->id }}" wire:navigate
                                           class="inline-flex items-center p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-50 rounded-lg transition-all"
                                           title="Ver producto">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                        <a href="/productos/{{ $product->id }}/editar" wire:navigate
                                           class="inline-flex items-center p-2 text-blue-600 hover:text-blue-900 hover:bg-blue-50 rounded-lg transition-all"
                                           title="Editar producto">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        <button wire:click="delete({{ $product->id }})"
                                                wire:confirm="¿Estás seguro de eliminar este producto?"
                                                class="inline-flex items-center p-2 text-red-600 hover:text-red-900 hover:bg-red-50 rounded-lg transition-all"
                                                title="Eliminar producto">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                    <h3 class="text-lg font-medium text-gray-900 mb-1">No se encontraron productos</h3>
                                    <p class="text-sm text-gray-500">Intenta ajustar los filtros o crea un nuevo producto</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div class="bg-white px-6 py-4 border-t border-gray-200">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</div>
