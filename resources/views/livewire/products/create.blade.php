<div class="space-y-6" x-data="{ activeTab: 'basico', productType: @entangle('product_type').live }" x-init="$watch('productType', value => { if (value === 'configurable' && activeTab === 'basico') { /* User can now navigate to variantes */ } })">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Crear Producto</h1>
            <p class="mt-2 text-sm text-gray-700">Agrega un nuevo producto al catálogo completo</p>
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
                        <span class="ml-2 bg-blue-100 text-blue-600 text-xs px-2 py-0.5 rounded-full" x-show="$wire.variants.length > 0" x-text="$wire.variants.length"></span>
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

        <form wire:submit="save">
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
                        <input type="text" wire:model="codigo_interno" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-mono">
                        @if($codigo_interno && $codigo_interno !== '0')
                            <p class="mt-1 text-xs text-green-600 flex items-center">
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Código generado automáticamente
                            </p>
                        @endif
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
                            <input type="checkbox" wire:model="estado" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="ml-3 text-sm font-medium text-gray-700">Producto activo</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="es_vendible" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
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

            <!-- Tab Variantes (Configurables) - Estilo Magento 2 -->
            <div x-show="activeTab === 'variantes'" class="p-6 space-y-6" x-cloak>
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-2">⚡ Configuración de Variantes</h3>
                    <p class="text-sm text-gray-600">Selecciona los atributos para generar automáticamente todas las combinaciones posibles.</p>
                </div>

                @forelse($variantAttributes as $attrIndex => $attrType)
                    <!-- Atributo: {{ $attrType->nombre }} -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6">
                        <h4 class="font-semibold text-gray-900 mb-4">{{ $attrIndex + 1 }}. Seleccionar {{ $attrType->nombre }}</h4>
                        <div class="grid grid-cols-3 sm:grid-cols-6 gap-3">
                            @foreach($attrType->activeValues as $value)
                            <label class="flex items-center justify-center p-3 border rounded-lg cursor-pointer transition-all hover:bg-gray-50"
                                   :class="($wire.selectedAttributeValues['{{ $attrType->slug }}'] ?? []).includes('{{ $value->valor }}') ? 'border-blue-500 bg-blue-50' : 'border-gray-300'">
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
                    <div class="bg-white border border-gray-200 rounded-lg p-6 text-center text-gray-500 text-sm">
                        No hay atributos configurados. <a href="/configuracion/atributos" wire:navigate class="text-blue-600 underline">Configurar atributos</a>
                    </div>
                @endforelse

                <!-- Generar Variantes -->
                <div class="flex items-center justify-between bg-white border border-gray-200 rounded-lg p-6">
                    <div>
                        <h4 class="font-semibold text-gray-900">Generar Variantes</h4>
                        <p class="text-sm text-gray-600 mt-1">
                            Se generarán <strong class="text-blue-600" x-text="$wire.variants.length > 0 ? $wire.variants.length : '?'"></strong> variantes
                        </p>
                    </div>
                    <button type="button" wire:click="generateVariants"
                            class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                        Generar Variantes
                    </button>
                </div>

                <!-- Tabla de Variantes Generadas -->
                <div x-show="$wire.variants.length > 0" class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h4 class="font-semibold text-gray-900">Variantes Generadas (<span x-text="$wire.variants.length"></span>)</h4>
                        <p class="text-sm text-gray-600 mt-1">Configura el stock para cada variante</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                    @foreach($variantAttributes as $attrType)
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $attrType->nombre }}</th>
                                    @endforeach
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($variants as $index => $variant)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $index + 1 }}</td>
                                    @foreach($variant['attributes'] as $attr)
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                            {{ $attr['value'] }}
                                        </span>
                                    </td>
                                    @endforeach
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="number" wire:model="variants.{{ $index }}.stock" min="0"
                                               class="w-24 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab Inventario -->
            <div x-show="activeTab === 'inventario'" class="p-6 space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Stock</label>
                        <input type="number" wire:model="stock" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
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
            <div x-show="activeTab === 'imagenes'" class="p-6 space-y-6">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <p class="text-sm text-blue-800">📸 Sube las imágenes del producto. Puedes seleccionar archivos desde tu computadora.</p>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <!-- Imagen Principal -->
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Imagen Principal</label>
                        <input type="file" wire:model="imagen" accept="image/*"
                               class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="mt-1 text-xs text-gray-500">Formatos: JPG, PNG, GIF (máx. 2MB)</p>
                    </div>

                    <!-- Imagen Mercado Libre -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Imagen Mercado Libre</label>
                        <input type="file" wire:model="imagen_ml" accept="image/*"
                               class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Imagen URL (alternativa) -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Imagen URL (alternativa)</label>
                        <input type="url" wire:model="imagen_url" placeholder="https://..."
                               class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                        <p class="mt-1 text-xs text-gray-500">O ingresa una URL externa</p>
                    </div>

                    <!-- Foto Modelo -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Foto con Modelo</label>
                        <input type="file" wire:model="foto_modelo" accept="image/*"
                               class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Foto Modelo Detalle -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Foto Modelo Detalle</label>
                        <input type="file" wire:model="foto_modelo_detalle" accept="image/*"
                               class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Foto Medidas -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Tabla de Medidas</label>
                        <input type="file" wire:model="foto_medidas" accept="image/*"
                               class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Foto Estampa -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Foto de Estampa</label>
                        <input type="file" wire:model="foto_estampa" accept="image/*"
                               class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Plano Técnico -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Plano Técnico</label>
                        <input type="file" wire:model="plano" accept="image/*,.pdf"
                               class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="mt-1 text-xs text-gray-500">Imagen o PDF</p>
                    </div>

                    <!-- Manual -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Manual</label>
                        <input type="file" wire:model="manual" accept="image/*,.pdf"
                               class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="mt-1 text-xs text-gray-500">Imagen o PDF</p>
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
                    Crear Producto
                </button>
            </div>
        </form>
    </div>
</div>
