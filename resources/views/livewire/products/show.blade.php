<div class="space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-3 mb-2">
                <a href="/productos" wire:navigate
                   class="inline-flex items-center p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h1 class="text-3xl font-bold text-gray-900">{{ $product->nombre }}</h1>

                @if($product->isConfigurable())
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-800 ring-1 ring-blue-600/20">
                        📦 Configurable
                    </span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-800 ring-1 ring-green-600/20">
                        ✅ Simple
                    </span>
                @endif

                @if($product->estado)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                        Activo
                    </span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                        Inactivo
                    </span>
                @endif
            </div>
            <p class="text-sm text-gray-600">Código: <span class="font-mono font-semibold">{{ $product->codigo_interno }}</span></p>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-2">
            <a href="/productos/{{ $product->id }}/editar" wire:navigate
               class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-sm transition-all duration-200">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Editar Producto
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Columna Izquierda - Imágenes -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Imágenes</h3>

                @if($product->images->count() > 0)
                    <div class="space-y-4">
                        <!-- Imagen Principal -->
                        @php
                            $baseImage = $product->images->where('is_base', true)->first() ?? $product->images->first();
                        @endphp
                        <div class="aspect-square bg-gray-50 rounded-lg overflow-hidden border border-gray-200">
                            <img src="{{ asset('storage/' . $baseImage->path) }}"
                                 alt="{{ $baseImage->label ?? $product->nombre }}"
                                 class="w-full h-full object-contain p-4">
                        </div>

                        <!-- Galería de Miniaturas -->
                        @if($product->images->count() > 1)
                            <div class="grid grid-cols-4 gap-2">
                                @foreach($product->images as $image)
                                    <div class="aspect-square bg-gray-50 rounded-lg overflow-hidden border {{ $image->is_base ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-200' }} cursor-pointer hover:border-blue-400 transition-all">
                                        <img src="{{ asset('storage/' . $image->path) }}"
                                             alt="{{ $image->label ?? 'Imagen' }}"
                                             class="w-full h-full object-contain p-2">
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <div class="aspect-square bg-gray-50 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center">
                        <div class="text-center">
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-sm text-gray-500">Sin imagen</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Columna Derecha - Información -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Información General -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Información General</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Código Interno</label>
                        <p class="text-base font-mono font-semibold text-gray-900">{{ $product->codigo_interno ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Código de Barras</label>
                        <p class="text-base font-mono text-gray-900">{{ $product->codigo_barras ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Marca</label>
                        <p class="text-base text-gray-900">{{ $product->marca ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Línea</label>
                        <p class="text-base text-gray-900">{{ $product->linea ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Familia</label>
                        <p class="text-base text-gray-900">{{ $product->familia ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Grupo</label>
                        <p class="text-base text-gray-900">{{ $product->grupo ?? '-' }}</p>
                    </div>
                    @if(!$product->isConfigurable())
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Color</label>
                            <p class="text-base text-gray-900">{{ $product->color ?? '-' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Talle</label>
                            <p class="text-base text-gray-900">{{ $product->n_talle ?? '-' }}</p>
                        </div>
                    @endif
                </div>

                @if($product->descripcion_web)
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-600 mb-1">Descripción</label>
                        <p class="text-base text-gray-700 leading-relaxed">{{ $product->descripcion_web }}</p>
                    </div>
                @endif
            </div>

            <!-- Precios y Stock -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Precios y Stock</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                        <label class="block text-xs font-medium text-blue-600 mb-1">Precio</label>
                        <p class="text-2xl font-bold text-blue-900">${{ number_format($product->precio ?? 0, 2) }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Costo</label>
                        <p class="text-2xl font-bold text-gray-900">${{ number_format($product->costo ?? 0, 2) }}</p>
                    </div>
                    <div class="bg-{{ $product->stock <= 0 ? 'red' : ($product->isCriticalStock() ? 'amber' : 'green') }}-50 rounded-lg p-4 border border-{{ $product->stock <= 0 ? 'red' : ($product->isCriticalStock() ? 'amber' : 'green') }}-200">
                        <label class="block text-xs font-medium text-{{ $product->stock <= 0 ? 'red' : ($product->isCriticalStock() ? 'amber' : 'green') }}-600 mb-1">Stock</label>
                        <p class="text-2xl font-bold text-{{ $product->stock <= 0 ? 'red' : ($product->isCriticalStock() ? 'amber' : 'green') }}-900">{{ $product->stock }}</p>
                    </div>
                    <div class="bg-orange-50 rounded-lg p-4 border border-orange-200">
                        <label class="block text-xs font-medium text-orange-600 mb-1">Stock Crítico</label>
                        <p class="text-2xl font-bold text-orange-900">{{ $product->stock_critico }}</p>
                    </div>
                </div>

                @if($product->isConfigurable())
                    <div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <p class="text-sm text-blue-800">
                            <span class="font-semibold">Stock Total de Variantes:</span>
                            {{ $product->variants->sum('stock') }} unidades
                        </p>
                    </div>
                @endif
            </div>

            <!-- Variantes (si es configurable) -->
            @if($product->isConfigurable() && $product->variants->count() > 0)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Variantes ({{ $product->variants->count() }})</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Imagen</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Código</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Color</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Talle</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Stock</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Precio</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($product->variants as $variant)
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-3">
                                            <div class="h-12 w-12 border border-gray-200 rounded overflow-hidden bg-gray-50">
                                                @if($variant->images->count() > 0)
                                                    <img src="{{ asset('storage/' . $variant->images->first()->path) }}"
                                                         alt="{{ $variant->nombre }}"
                                                         class="h-full w-full object-contain p-1">
                                                @else
                                                    <div class="h-full w-full flex items-center justify-center">
                                                        <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                        </svg>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="text-sm font-mono text-gray-900">{{ $variant->codigo_interno }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="text-sm text-gray-900">{{ $variant->color ?? '-' }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="text-sm font-medium text-gray-900">{{ $variant->n_talle ?? '-' }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($variant->stock <= 0)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                                    Sin stock
                                                </span>
                                            @elseif($variant->isCriticalStock())
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                                    {{ $variant->stock }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                    {{ $variant->stock }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="text-sm font-semibold text-gray-900">${{ number_format($variant->precio ?? 0, 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="/productos/{{ $variant->id }}" wire:navigate
                                               class="inline-flex items-center p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-all"
                                               title="Ver variante">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Producto Padre (si es variante) -->
            @if($product->parent_id && $product->parent)
                <div class="bg-gradient-to-r from-purple-50 to-blue-50 rounded-xl shadow-sm border border-purple-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Producto Padre</h3>
                    <div class="flex items-center gap-4">
                        <div class="h-16 w-16 border border-gray-200 rounded-lg overflow-hidden bg-white">
                            @if($product->parent->images->count() > 0)
                                <img src="{{ asset('storage/' . $product->parent->images->first()->path) }}"
                                     alt="{{ $product->parent->nombre }}"
                                     class="h-full w-full object-contain p-2">
                            @else
                                <div class="h-full w-full flex items-center justify-center">
                                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            @endif
                        </div>
                        <div class="flex-1">
                            <p class="text-base font-semibold text-gray-900">{{ $product->parent->nombre }}</p>
                            <p class="text-sm text-gray-600 font-mono">{{ $product->parent->codigo_interno }}</p>
                        </div>
                        <a href="/productos/{{ $product->parent->id }}" wire:navigate
                           class="inline-flex items-center px-4 py-2 border border-purple-300 text-sm font-medium rounded-lg text-purple-700 bg-white hover:bg-purple-50 transition-all">
                            Ver Producto Padre
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
