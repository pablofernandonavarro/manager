<div class="space-y-6" x-data="{ activeTab: 'basico', productType: @entangle('product_type').live }">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Editar Producto</h1>
            <p class="mt-2 text-sm text-gray-700">Modifica el producto <span class="font-semibold">{{ $product->nombre }}</span></p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="/productos" wire:navigate
               class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-sm transition-all duration-200">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Volver
            </a>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="border-b border-gray-200">
            <nav class="flex flex-wrap -mb-px overflow-x-auto" aria-label="Tabs">
                <button @click="activeTab = 'basico'" :class="activeTab === 'basico' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                    Básico
                </button>
                <button @click="activeTab = 'precios'" :class="activeTab === 'precios' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                    Precios/Costos
                </button>
                <button @click="activeTab = 'variantes'" :class="activeTab === 'variantes' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors"
                        x-show="productType === 'configurable'">
                    <span class="inline-flex items-center">
                        ⚡ Variantes
                        <span class="ml-2 bg-blue-100 text-blue-600 text-xs px-2 py-0.5 rounded-full">{{ $existingVariants->count() ?? 0 }}</span>
                    </span>
                </button>
                <button @click="activeTab = 'inventario'" :class="activeTab === 'inventario' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors"
                        x-show="productType === 'simple'">
                    Inventario
                </button>
                <button @click="activeTab = 'clasificacion'" :class="activeTab === 'clasificacion' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                    Clasificación
                </button>
                <button @click="activeTab = 'produccion'" :class="activeTab === 'produccion' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                    Producción
                </button>
                <button @click="activeTab = 'proveedores'" :class="activeTab === 'proveedores' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                    Proveedores
                </button>
                <button @click="activeTab = 'ecommerce'" :class="activeTab === 'ecommerce' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                    E-commerce
                </button>
                <button @click="activeTab = 'imagenes'" :class="activeTab === 'imagenes' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                    Imágenes
                </button>
                <button @click="activeTab = 'fechas'" :class="activeTab === 'fechas' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                    Fechas/Obs.
                </button>
            </nav>
        </div>

        <form wire:submit="update">
            <!-- Tab Básico -->
            <div x-show="activeTab === 'basico'" class="p-6 space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <!-- Tipo de Producto (NUEVO) -->
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Tipo de Producto *</label>
                        <select wire:model.live="product_type" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm @error('product_type') border-red-300 @enderror">
                            <option value="simple">Simple - Producto vendible con stock propio</option>
                            <option value="configurable">Configurable - Agrupa variantes (no vendible directamente)</option>
                        </select>
                        @error('product_type') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                        <!-- Ayuda contextual -->
                        <div class="mt-2 p-3 rounded-lg text-sm" :class="productType === 'simple' ? 'bg-green-50 text-green-800' : 'bg-blue-50 text-blue-800'">
                            <span x-show="productType === 'simple'">
                                ✅ <strong>Simple:</strong> Producto que se vende directamente con stock propio (ej: Remera Negra M, Gorra Nike)
                            </span>
                            <span x-show="productType === 'configurable'">
                                📦 <strong>Configurable:</strong> Producto padre que agrupa variantes de colores/talles. No se vende directamente, solo sus variantes.
                            </span>
                        </div>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Nombre del producto *</label>
                        <input type="text" wire:model="nombre" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm @error('nombre') border-red-300 @enderror">
                        @error('nombre') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Código Interno</label>
                        <input type="text" wire:model="codigo_interno" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Código de Barras</label>
                        <input type="text" wire:model="codigo_barras" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Denominación</label>
                        <input type="text" wire:model="denominacion" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Abreviatura</label>
                        <input type="text" wire:model="abreviatura" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Color</label>
                        <input type="text" wire:model="color" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Talle</label>
                        <input type="text" wire:model="n_talle" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Género</label>
                        <select wire:model="genero" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">— Sin especificar —</option>
                            <option value="Femenino">Femenino</option>
                            <option value="Masculino">Masculino</option>
                            <option value="Unisex">Unisex</option>
                            <option value="Niña">Niña</option>
                            <option value="Niño">Niño</option>
                            <option value="Bebé">Bebé</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Composición / Material</label>
                        <input type="text" wire:model="composicion" placeholder="Ej: 100% Algodón, 50% Poliéster..." class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Peso (kg)</label>
                        <input type="number" step="0.001" wire:model="peso" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Dimensión</label>
                        <input type="text" wire:model="dimension" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Descripción Web</label>
                        <textarea wire:model="descripcion_web" rows="4" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Descripción Técnica</label>
                        <textarea wire:model="descripcion_tecnica" rows="4" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"></textarea>
                    </div>
                    <div class="sm:col-span-2 space-y-4">
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="estado" value="1" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" {{ $estado == 1 ? 'checked' : '' }}>
                            <span class="ml-3 text-sm font-medium text-gray-700">Producto activo</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="es_vendible" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" {{ $es_vendible ? 'checked' : '' }}>
                            <span class="ml-3 text-sm font-medium text-gray-700">Es vendible</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="remitible" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="ml-3 text-sm font-medium text-gray-700">Remitible</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Tab Precios/Costos -->
            <div x-show="activeTab === 'precios'" class="p-6 space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Precio</label>
                        <input type="number" step="0.01" wire:model="precio" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Costo</label>
                        <input type="number" step="0.01" wire:model="costo" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">IVA (%)</label>
                        <input type="number" step="0.01" wire:model="iva" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Costo USD</label>
                        <input type="number" step="0.01" wire:model="costo_usd" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Precio USD</label>
                        <input type="number" step="0.01" wire:model="precio_usd" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Público</label>
                        <input type="number" step="0.01" wire:model="publico" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Costo Producción</label>
                        <input type="number" step="0.01" wire:model="costo_produccion" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Costo Corte</label>
                        <input type="number" step="0.01" wire:model="costo_corte" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Costo Target</label>
                        <input type="number" step="0.01" wire:model="costo_target" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Costo Adicional</label>
                        <input type="number" step="0.01" wire:model="costo_adicional" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Precosto Compra</label>
                        <input type="number" step="0.01" wire:model="precosto_compra" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Precosto Avíos</label>
                        <input type="number" step="0.01" wire:model="precosto_avios" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Precosto Telas</label>
                        <input type="number" step="0.01" wire:model="precosto_telas" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Markup</label>
                        <input type="number" step="0.01" wire:model="markup" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Descuento Web (%)</label>
                        <input type="number" step="0.01" wire:model="descuento_web" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                </div>
            </div>

            <!-- Tab Variantes (solo para configurables) -->
            <div x-show="activeTab === 'variantes'" class="p-6" x-cloak>
                <h3 class="text-lg font-medium text-gray-900 mb-6">Variantes del Producto</h3>

                <!-- Variantes Existentes -->
                <div class="mb-8">
                    <h4 class="text-md font-semibold text-gray-900 mb-3">📦 Variantes Existentes</h4>

                    @if($existingVariants && $existingVariants->count() > 0)
                        <div class="space-y-3">
                            @foreach($existingVariants as $variant)
                                <div class="border border-gray-300 rounded-lg p-4 bg-white hover:bg-gray-50 transition shadow-sm">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-4">
                                            <!-- Variant Image -->
                                            <div class="flex-shrink-0 h-16 w-16 border border-gray-300 bg-white p-1 rounded">
                                                @if($variant->imagen)
                                                    <img src="{{ asset('storage/' . $variant->imagen) }}"
                                                         alt="{{ $variant->nombre }}"
                                                         class="h-full w-full object-contain">
                                                @else
                                                    <div class="h-full w-full bg-gray-50 flex items-center justify-center">
                                                        <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                        </svg>
                                                    </div>
                                                @endif
                                            </div>

                                            <!-- Variant Info -->
                                            <div>
                                                <h4 class="text-sm font-semibold text-gray-900">{{ $variant->nombre }}</h4>
                                                <div class="flex gap-4 mt-1 text-xs text-gray-600">
                                                    @if($variant->color)
                                                        <span>Color: <strong>{{ $variant->color }}</strong></span>
                                                    @endif
                                                    @if($variant->n_talle)
                                                        <span>Talle: <strong>{{ $variant->n_talle }}</strong></span>
                                                    @endif
                                                    <span>SKU: <strong>{{ $variant->codigo_interno }}</strong></span>
                                                </div>
                                                <div class="flex gap-4 mt-1 text-xs">
                                                    <span class="text-gray-600">Stock: <strong class="text-gray-900">{{ $variant->stock }}</strong></span>
                                                    <span class="text-gray-600">Precio: <strong class="text-gray-900">${{ number_format($variant->precio ?? 0, 2) }}</strong></span>
                                                    @if($variant->estado)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                            Activo
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                            Inactivo
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Actions -->
                                        <div class="flex gap-2">
                                            <a href="/productos/{{ $variant->id }}/editar" wire:navigate
                                               class="inline-flex items-center p-2 text-blue-600 hover:text-blue-900 hover:bg-blue-50 rounded-lg transition-all"
                                               title="Editar variante">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <p class="text-sm text-blue-800">
                                💡 <strong>Tip:</strong> Este producto tiene {{ $existingVariants->count() }} variante(s). Haz clic en el botón "Editar" para modificar cada variante individualmente.
                            </p>
                        </div>
                    @else
                        <div class="text-center py-8 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            <h3 class="text-md font-medium text-gray-900 mb-1">No hay variantes todavía</h3>
                            <p class="text-sm text-gray-500">Usa la sección de abajo para agregar variantes a este producto.</p>
                        </div>
                    @endif
                </div>

                <!-- Crear Nuevas Variantes -->
                <div class="mb-6 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6">
                    <h4 class="text-md font-bold text-gray-900 mb-4">➕ Agregar Nuevas Variantes</h4>

                    @forelse($variantAttributes as $attrIndex => $attrType)
                        <!-- Paso {{ $attrIndex + 1 }}: {{ $attrType->nombre }} -->
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                {{ $attrIndex + 1 }}. Seleccionar {{ $attrType->nombre }}
                            </label>
                            <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                                @foreach($attrType->activeValues as $value)
                                <label class="flex items-center justify-center p-2 border rounded-lg cursor-pointer transition-all hover:bg-white"
                                       :class="($wire.selectedAttributeValues['{{ $attrType->slug }}'] ?? []).includes('{{ $value->valor }}') ? 'border-blue-500 bg-white' : 'border-gray-300'">
                                    <input type="checkbox"
                                           wire:model.live="selectedAttributeValues.{{ $attrType->slug }}"
                                           value="{{ $value->valor }}"
                                           class="sr-only">
                                    <span class="text-sm font-bold">{{ $value->valor }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-gray-500 text-sm">
                            No hay atributos configurados. <a href="/configuracion/atributos" wire:navigate class="text-blue-600 underline">Configurar atributos</a>
                        </div>
                    @endforelse

                    <!-- Generar -->
                    <div class="mb-3">
                        <button type="button" wire:click="generateVariants"
                                class="w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                                wire:loading.attr="disabled" wire:target="generateVariants">
                            <span wire:loading.remove wire:target="generateVariants">
                                Generar Variantes
                            </span>
                            <span wire:loading wire:target="generateVariants">
                                Generando...
                            </span>
                        </button>
                    </div>

                    <!-- Tabla de variantes a crear -->
                    @if(count($variants) > 0)
                        <div class="mt-4 border border-gray-300 rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    @foreach($variantAttributes as $attrType)
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $attrType->nombre }}</th>
                                    @endforeach
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($variants as $index => $variant)
                                <tr>
                                    @foreach($variant['attributes'] as $attr)
                                    <td class="px-4 py-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100">
                                            {{ $attr['value'] }}
                                        </span>
                                    </td>
                                    @endforeach
                                    <td class="px-4 py-2">
                                        <input type="number" wire:model="variants.{{ $index }}.stock" min="0"
                                               class="w-20 px-2 py-1 border border-gray-300 rounded text-sm">
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <!-- Botón Guardar Variantes -->
                        <div class="mt-4 mb-4 px-4">
                            <button type="button" wire:click="saveVariants"
                                    wire:loading.attr="disabled"
                                    style="background-color: #16a34a;"
                                    class="w-full px-4 py-3 text-white text-base font-semibold rounded-lg hover:opacity-90 transition-all disabled:opacity-50 shadow-md">
                                <span wire:loading.remove wire:target="saveVariants">
                                    ✓ GUARDAR VARIANTES
                                </span>
                                <span wire:loading wire:target="saveVariants">
                                    💾 Guardando...
                                </span>
                            </button>
                        </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Tab Inventario -->
            <div x-show="activeTab === 'inventario'" class="p-6 space-y-6">
                <!-- Stock global (solo lectura) -->
                <div class="flex items-center justify-between bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
                    <div>
                        <p class="text-xs font-medium text-blue-600 uppercase tracking-wide">Stock global (todas las sucursales)</p>
                        <p class="text-2xl font-bold text-blue-900 mt-0.5">{{ $product->stock ?? 0 }}</p>
                    </div>
                    <a href="{{ route('sucursales.ajuste-stock') }}" wire:navigate
                       class="flex items-center gap-1.5 px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Ajustar stock
                    </a>
                </div>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Stock Crítico</label>
                        <input type="number" wire:model="stock_critico" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Stock Comprometido</label>
                        <input type="number" wire:model="stock_comprometido" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Reservados</label>
                        <input type="number" wire:model="reservados" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Primera</label>
                        <input type="number" wire:model="primera" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Segunda</label>
                        <input type="number" wire:model="segunda" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                </div>
            </div>

            <!-- Tab Clasificación -->
            <div x-show="activeTab === 'clasificacion'" class="p-6 space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Línea</label>
                        <select wire:model="linea" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">— Sin línea —</option>
                            @foreach($lineas as $l)
                                <option value="{{ $l->id }}">{{ $l->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Marca</label>
                        <select wire:model="marca" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">— Sin marca —</option>
                            @foreach($marcas as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Familia (ID)</label>
                        <input type="number" wire:model="familia" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Grupo</label>
                        <select wire:model="grupo" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">— Sin grupo —</option>
                            @foreach($grupos as $g)
                                <option value="{{ $g->id }}">{{ $g->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Subgrupo</label>
                        <select wire:model="subgrupo" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">— Sin subgrupo —</option>
                            @foreach($subgrupos as $sg)
                                <option value="{{ $sg->id }}">{{ $sg->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Temporada</label>
                        <select wire:model="temporada" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">— Sin temporada —</option>
                            @foreach($temporadas as $t)
                                <option value="{{ $t->id }}">{{ $t->nombre }}{{ $t->anio ? ' '.$t->anio : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Target</label>
                        <select wire:model="target" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">— Sin target —</option>
                            @foreach($targets as $tg)
                                <option value="{{ $tg->id }}">{{ $tg->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Edad (ID)</label>
                        <input type="number" wire:model="edad" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Procedencia</label>
                        <select wire:model="procedencia" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">— Sin procedencia —</option>
                            @foreach($procedencias as $pr)
                                <option value="{{ $pr->id }}">{{ $pr->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Nombre Grupo</label>
                        <input type="text" wire:model="n_grupo" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Nombre Subgrupo</label>
                        <input type="text" wire:model="n_subgrupo" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Nombre Temporada</label>
                        <input type="text" wire:model="n_temporada" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                </div>
            </div>

            <!-- Tab Producción -->
            <div x-show="activeTab === 'produccion'" class="p-6 space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Modelista</label>
                        <input type="text" wire:model="modelista" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Número Molde</label>
                        <input type="text" wire:model="numero_molde" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Cantidad a Fabricar</label>
                        <input type="number" wire:model="cantidad_a_fabricar" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Complejidad</label>
                        <input type="number" wire:model="complejidad" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                </div>
                <div class="border-t pt-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">Control de Producción</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="molde" value="1" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">Molde</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="progresion" value="1" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">Progresión</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="ficha_tecnica" value="1" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">Ficha Técnica</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="estampa" value="1" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">Estampa</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="bordado" value="1" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">Bordado</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="tachas" value="1" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">Tachas</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="etiquetas" value="1" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">Etiquetas</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="avios" value="1" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">Avíos</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="lavado" value="1" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">Lavado</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="muestra" value="1" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">Muestra</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="muestrario" value="1" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">Muestrario</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="encorte" value="1" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">En Corte</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Tab Proveedores -->
            <div x-show="activeTab === 'proveedores'" class="p-6 space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Proveedor (ID)</label>
                        <input type="number" wire:model="proveedor" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Proveedor 2 (ID)</label>
                        <input type="number" wire:model="proveedor2" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Proveedor 3 (ID)</label>
                        <input type="number" wire:model="proveedor3" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Nombre Proveedor</label>
                        <input type="text" wire:model="n_proveedor" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Código Color Proveedor</label>
                        <input type="text" wire:model="codigo_color_proveedor" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Código Artículo Proveedor</label>
                        <input type="text" wire:model="codigo_articulo_proveedor" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Ancho Proveedor</label>
                        <input type="text" wire:model="ancho_proveedor" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Ancho Real</label>
                        <input type="text" wire:model="ancho_real" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                </div>
            </div>

            <!-- Tab E-commerce -->
            <div x-show="activeTab === 'ecommerce'" class="p-6 space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">SKU Dafiti</label>
                        <input type="text" wire:model="dafiti_sku" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">GTIN</label>
                        <input type="text" wire:model="gtin" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Stock ML</label>
                        <input type="number" wire:model="stock_ml" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Color ML</label>
                        <input type="text" wire:model="color_ml" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">URL E-commerce</label>
                        <input type="text" wire:model="url_ecomm" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div class="sm:col-span-2 space-y-4">
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="publicar_ml" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                            <span class="ml-3 text-sm font-medium text-gray-700">Publicar en Mercado Libre</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="destacado_web" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                            <span class="ml-3 text-sm font-medium text-gray-700">Destacado Web</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Tab Imágenes -->
            <div x-show="activeTab === 'imagenes'" class="p-6" x-data="{
                uploading: false,
                dragOver: false
            }">
                <!-- Título -->
                <div class="mb-4">
                    <h3 class="text-base font-medium text-gray-700">Fotos (requerido)</h3>
                </div>

                <!-- Mensaje de Éxito -->
                @if (session()->has('success'))
                    <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-sm text-green-800">{{ session('success') }}</p>
                    </div>
                @endif

                <!-- Aviso de herencia de imágenes del padre -->
                @php
                    $inheritConfig = \App\Models\ProductConfiguration::current();
                @endphp
                @if($product->images->isEmpty() && $product->parent_id && $inheritConfig->child_inherits_parent_images)
                    @php
                        $parentImages = $product->parent?->images ?? collect();
                    @endphp
                    <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg flex items-start gap-3">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="text-sm text-blue-800">
                            <p class="font-semibold">Usando imágenes del producto padre</p>
                            <p class="mt-1">Este producto no tiene imágenes propias. Se muestran las
                                <strong>{{ $parentImages->count() }} imagen(es)</strong>
                                del producto padre.
                                <a href="/productos/{{ $product->parent_id }}/editar" wire:navigate class="underline hover:text-blue-600">Ver producto padre</a>.
                            </p>
                            <p class="mt-1 text-xs text-blue-600">Sube imágenes propias para reemplazar la herencia.</p>
                        </div>
                    </div>
                @endif

                <!-- Grid de Miniaturas -->
                <div class="flex flex-wrap gap-3 mb-6">
                    <!-- Imágenes Existentes -->
                    @foreach($product->images as $index => $image)
                        <div class="relative group" wire:key="image-{{ $image->id }}">
                            <!-- Caja de Imagen -->
                            <div class="w-32 h-32 border-2 border-dashed rounded {{ $image->is_base ? 'border-green-500' : 'border-gray-300' }} bg-white hover:border-gray-400 transition-all relative overflow-hidden">
                                <img src="{{ asset('storage/' . $image->path) }}"
                                     alt="{{ $image->label ?? 'Foto' }}"
                                     class="w-full h-full object-contain p-2">

                                @if($image->is_base || $product->images->count() === 1)
                                    <!-- Badge PORTADA -->
                                    <div class="absolute bottom-0 left-0 right-0 bg-green-600 text-white text-center py-0.5">
                                        <span class="text-[10px] font-semibold uppercase">Portada</span>
                                    </div>
                                @endif

                                <!-- Botón eliminar X -->
                                <button type="button"
                                        wire:click="deleteImage({{ $image->id }})"
                                        wire:confirm="¿Eliminar esta foto?"
                                        class="absolute top-1 right-1 w-5 h-5 bg-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-gray-100 flex items-center justify-center shadow">
                                    <svg class="w-3 h-3 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endforeach

                    <!-- Botón Seleccionar -->
                    @if($product->images->count() < 10)
                        <div>
                            <label class="w-32 h-32 border-2 border-dashed border-blue-400 rounded cursor-pointer hover:bg-blue-50 hover:border-blue-500 transition-all bg-white flex flex-col items-center justify-center gap-2"
                                   @dragover.prevent="dragOver = true"
                                   @dragleave.prevent="dragOver = false"
                                   @drop.prevent="dragOver = false; $refs.fileInput.files = $event.dataTransfer.files"
                                   :class="{ 'border-blue-500 bg-blue-50': dragOver }">
                                <svg class="w-10 h-10 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <span class="text-sm font-medium text-blue-600">Seleccionar</span>
                                <input type="file" x-ref="fileInput" wire:model="newImages" multiple accept="image/*" class="hidden">
                            </label>
                        </div>
                    @endif
                </div>

                <!-- Indicador de Carga -->
                <div wire:loading wire:target="newImages" class="mb-6">
                    <div class="flex items-center justify-center p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <svg class="animate-spin h-5 w-5 text-blue-600 mr-3" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-sm font-medium text-blue-600">Subiendo fotos...</span>
                    </div>
                </div>

                <!-- Opciones Avanzadas (Collapsible) -->
                <div x-data="{ showAdvanced: false }" class="border-t pt-6">
                    <button type="button" @click="showAdvanced = !showAdvanced"
                            class="flex items-center gap-2 text-sm font-medium text-gray-700 hover:text-gray-900 mb-4">
                        <svg class="w-5 h-5 transition-transform" :class="{ 'rotate-90': showAdvanced }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        Opciones avanzadas de imágenes
                    </button>

                    <div x-show="showAdvanced" x-collapse class="space-y-4">
                        @foreach($product->images as $image)
                            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50" wire:key="advanced-{{ $image->id }}">
                                <div class="flex gap-4">
                                    <!-- Miniatura -->
                                    <div class="flex-shrink-0 w-20 h-20 border border-gray-300 rounded overflow-hidden bg-white">
                                        <img src="{{ asset('storage/' . $image->path) }}" class="w-full h-full object-contain p-1">
                                    </div>

                                    <!-- Controles -->
                                    <div class="flex-1 space-y-3">
                                        <!-- Etiqueta -->
                                        <input type="text" wire:model.blur="imageLabels.{{ $image->id }}"
                                               placeholder="Describe esta foto (opcional)"
                                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">

                                        <!-- Roles -->
                                        <div class="flex flex-wrap gap-2">
                                            <label class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium cursor-pointer transition-all {{ $image->is_base ? 'bg-blue-100 text-blue-700 border-2 border-blue-500' : 'bg-white text-gray-600 border border-gray-300 hover:border-blue-400' }}">
                                                <input type="checkbox"
                                                       wire:change="updateImageRoles({{ $image->id }}, $event.target.checked ? [...$wire.imageRoles[{{ $image->id }}] || [], 'base'] : ($wire.imageRoles[{{ $image->id }}] || []).filter(r => r !== 'base'))"
                                                       @if($image->is_base) checked @endif
                                                       class="sr-only">
                                                Principal
                                            </label>
                                            <label class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium cursor-pointer transition-all {{ $image->is_small ? 'bg-green-100 text-green-700 border-2 border-green-500' : 'bg-white text-gray-600 border border-gray-300 hover:border-green-400' }}">
                                                <input type="checkbox"
                                                       wire:change="updateImageRoles({{ $image->id }}, $event.target.checked ? [...$wire.imageRoles[{{ $image->id }}] || [], 'small'] : ($wire.imageRoles[{{ $image->id }}] || []).filter(r => r !== 'small'))"
                                                       @if($image->is_small) checked @endif
                                                       class="sr-only">
                                                Listado
                                            </label>
                                            <label class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium cursor-pointer transition-all {{ $image->is_thumbnail ? 'bg-purple-100 text-purple-700 border-2 border-purple-500' : 'bg-white text-gray-600 border border-gray-300 hover:border-purple-400' }}">
                                                <input type="checkbox"
                                                       wire:change="updateImageRoles({{ $image->id }}, $event.target.checked ? [...$wire.imageRoles[{{ $image->id }}] || [], 'thumbnail'] : ($wire.imageRoles[{{ $image->id }}] || []).filter(r => r !== 'thumbnail'))"
                                                       @if($image->is_thumbnail) checked @endif
                                                       class="sr-only">
                                                Miniatura
                                            </label>
                                            <label class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium cursor-pointer transition-all {{ $image->is_swatch ? 'bg-orange-100 text-orange-700 border-2 border-orange-500' : 'bg-white text-gray-600 border border-gray-300 hover:border-orange-400' }}">
                                                <input type="checkbox"
                                                       wire:change="updateImageRoles({{ $image->id }}, $event.target.checked ? [...$wire.imageRoles[{{ $image->id }}] || [], 'swatch'] : ($wire.imageRoles[{{ $image->id }}] || []).filter(r => r !== 'swatch'))"
                                                       @if($image->is_swatch) checked @endif
                                                       class="sr-only">
                                                Muestra
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="space-y-6 hidden">
                    <!-- Imagen Principal -->
                    <div class="border border-gray-300 rounded p-4 bg-white">
                        <label class="block text-sm font-medium text-gray-700 mb-3">Imagen Principal</label>
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                <div class="h-32 w-32 border border-gray-300 bg-white p-1">
                                    @if($product->imagen)
                                        <img src="{{ asset('storage/' . $product->imagen) }}"
                                             alt="Imagen principal"
                                             class="h-full w-full object-contain">
                                    @else
                                        <div class="h-full w-full bg-gray-50 flex items-center justify-center">
                                            <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex-1">
                                <input type="file" wire:model="imagen" accept="image/*"
                                       class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:border file:border-gray-300 file:rounded file:text-sm file:font-medium file:bg-white hover:file:bg-gray-50 cursor-pointer">
                                <p class="mt-2 text-xs text-gray-500">Formatos permitidos: JPG, PNG, GIF (máx. 2MB)</p>
                                @if($product->imagen)
                                    <p class="mt-1 text-xs text-gray-600">Actual: <span class="font-mono">{{ basename($product->imagen) }}</span></p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Gallery Images -->
                    <div class="border border-gray-300 rounded p-4 bg-white">
                        <label class="block text-sm font-medium text-gray-700 mb-3">Galería de Imágenes</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Imagen ML -->
                            <div>
                                <div class="h-28 w-28 border border-gray-300 bg-white p-1 mb-2">
                                    @if($product->imagen_ml)
                                        <img src="{{ asset('storage/' . $product->imagen_ml) }}" class="h-full w-full object-contain">
                                    @else
                                        <div class="h-full w-full bg-gray-50 flex items-center justify-center">
                                            <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">MercadoLibre</label>
                                <input type="file" wire:model="imagen_ml" accept="image/*"
                                       class="block w-full text-xs text-gray-700 file:mr-2 file:py-1.5 file:px-3 file:border file:border-gray-300 file:rounded file:text-xs file:bg-white hover:file:bg-gray-50">
                                @if($product->imagen_ml)
                                    <p class="mt-1 text-[10px] text-gray-500 font-mono">{{ basename($product->imagen_ml) }}</p>
                                @endif
                            </div>

                            <!-- Foto Modelo -->
                            <div>
                                <div class="h-28 w-28 border border-gray-300 bg-white p-1 mb-2">
                                    @if($product->foto_modelo)
                                        <img src="{{ asset('storage/' . $product->foto_modelo) }}" class="h-full w-full object-contain">
                                    @else
                                        <div class="h-full w-full bg-gray-50 flex items-center justify-center">
                                            <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Foto Modelo</label>
                                <input type="file" wire:model="foto_modelo" accept="image/*"
                                       class="block w-full text-xs text-gray-700 file:mr-2 file:py-1.5 file:px-3 file:border file:border-gray-300 file:rounded file:text-xs file:bg-white hover:file:bg-gray-50">
                                @if($product->foto_modelo)
                                    <p class="mt-1 text-[10px] text-gray-500 font-mono">{{ basename($product->foto_modelo) }}</p>
                                @endif
                            </div>

                            <!-- Foto Modelo Detalle -->
                            <div>
                                <div class="h-28 w-28 border border-gray-300 bg-white p-1 mb-2">
                                    @if($product->foto_modelo_detalle)
                                        <img src="{{ asset('storage/' . $product->foto_modelo_detalle) }}" class="h-full w-full object-contain">
                                    @else
                                        <div class="h-full w-full bg-gray-50 flex items-center justify-center">
                                            <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Modelo Detalle</label>
                                <input type="file" wire:model="foto_modelo_detalle" accept="image/*"
                                       class="block w-full text-xs text-gray-700 file:mr-2 file:py-1.5 file:px-3 file:border file:border-gray-300 file:rounded file:text-xs file:bg-white hover:file:bg-gray-50">
                                @if($product->foto_modelo_detalle)
                                    <p class="mt-1 text-[10px] text-gray-500 font-mono">{{ basename($product->foto_modelo_detalle) }}</p>
                                @endif
                            </div>

                            <!-- Foto Medidas -->
                            <div>
                                <div class="h-28 w-28 border border-gray-300 bg-white p-1 mb-2">
                                    @if($product->foto_medidas)
                                        <img src="{{ asset('storage/' . $product->foto_medidas) }}" class="h-full w-full object-contain">
                                    @else
                                        <div class="h-full w-full bg-gray-50 flex items-center justify-center">
                                            <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Foto Medidas</label>
                                <input type="file" wire:model="foto_medidas" accept="image/*"
                                       class="block w-full text-xs text-gray-700 file:mr-2 file:py-1.5 file:px-3 file:border file:border-gray-300 file:rounded file:text-xs file:bg-white hover:file:bg-gray-50">
                                @if($product->foto_medidas)
                                    <p class="mt-1 text-[10px] text-gray-500 font-mono">{{ basename($product->foto_medidas) }}</p>
                                @endif
                            </div>

                            <!-- Foto Estampa -->
                            <div>
                                <div class="h-28 w-28 border border-gray-300 bg-white p-1 mb-2">
                                    @if($product->foto_estampa)
                                        <img src="{{ asset('storage/' . $product->foto_estampa) }}" class="h-full w-full object-contain">
                                    @else
                                        <div class="h-full w-full bg-gray-50 flex items-center justify-center">
                                            <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Foto Estampa</label>
                                <input type="file" wire:model="foto_estampa" accept="image/*"
                                       class="block w-full text-xs text-gray-700 file:mr-2 file:py-1.5 file:px-3 file:border file:border-gray-300 file:rounded file:text-xs file:bg-white hover:file:bg-gray-50">
                                @if($product->foto_estampa)
                                    <p class="mt-1 text-[10px] text-gray-500 font-mono">{{ basename($product->foto_estampa) }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- URL Externa -->
                    <div class="border border-gray-300 rounded p-4 bg-white">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Imagen URL (Externa)</label>
                        <input type="text" wire:model="imagen_url" placeholder="https://ejemplo.com/imagen.jpg"
                               class="block w-full px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        <p class="mt-1 text-xs text-gray-500">URL de imagen alojada externamente</p>
                    </div>

                    <!-- Documentos -->
                    <div class="border border-gray-300 rounded p-4 bg-white">
                        <label class="block text-sm font-medium text-gray-700 mb-3">Documentos</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Plano -->
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Plano (PDF/Imagen)</label>
                                <input type="file" wire:model="plano" accept="image/*,.pdf"
                                       class="block w-full text-xs text-gray-700 file:mr-2 file:py-1.5 file:px-3 file:border file:border-gray-300 file:rounded file:text-xs file:bg-white hover:file:bg-gray-50">
                                @if($product->plano)
                                    <p class="mt-1 text-[10px] text-gray-500 font-mono">{{ basename($product->plano) }}</p>
                                @endif
                            </div>

                            <!-- Manual -->
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Manual (PDF/Imagen)</label>
                                <input type="file" wire:model="manual" accept="image/*,.pdf"
                                       class="block w-full text-xs text-gray-700 file:mr-2 file:py-1.5 file:px-3 file:border file:border-gray-300 file:rounded file:text-xs file:bg-white hover:file:bg-gray-50">
                                @if($product->manual)
                                    <p class="mt-1 text-[10px] text-gray-500 font-mono">{{ basename($product->manual) }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Fechas/Observaciones -->
            <div x-show="activeTab === 'fechas'" class="p-6 space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Fecha Costo</label>
                        <input type="date" wire:model="fecha_costo" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Fecha Venta 1</label>
                        <input type="date" wire:model="fecha_venta1" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Fecha Ingreso</label>
                        <input type="date" wire:model="fecha_ingreso" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Observaciones</label>
                        <textarea wire:model="observaciones" rows="4" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Observaciones Modelaje</label>
                        <textarea wire:model="observaciones_modelaje" rows="4" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"></textarea>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="border-t px-6 py-4 flex items-center justify-end gap-3 bg-gray-50">
                <a href="/productos" wire:navigate
                   class="inline-flex items-center px-4 py-2.5 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-all">
                    Cancelar
                </a>
                <button type="submit"
                        class="inline-flex items-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition-all duration-200">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Actualizar Producto
                </button>
            </div>
        </form>
    </div>
</div>
