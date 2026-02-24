<div class="space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Configuración de Productos</h1>
            <p class="mt-2 text-sm text-gray-700">Configura las opciones generales para la creación y gestión de productos</p>
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

    <form wire:submit="save">
        <div class="space-y-6">
            <!-- Sección: Códigos Internos -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                        </svg>
                        Códigos Internos
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">Define cómo se generarán los códigos internos de los productos</p>
                </div>
                <div class="p-6 space-y-6">
                    <!-- Toggle: Generar automáticamente -->
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="flex-1">
                            <label class="text-sm font-semibold text-gray-900">Generar códigos automáticamente</label>
                            <p class="text-xs text-gray-600 mt-1">Los códigos se generarán automáticamente al crear productos</p>
                        </div>
                        <button type="button" wire:click="$toggle('auto_generate_code')"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 {{ $auto_generate_code ? 'bg-blue-600' : 'bg-gray-200' }}">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $auto_generate_code ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        </button>
                    </div>

                    @if($auto_generate_code)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Formato de código -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Formato de Código</label>
                                <select wire:model.live="code_format"
                                        class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                    <option value="sequential">Secuencial (0001, 0002, 0003...)</option>
                                    <option value="timestamp">Timestamp (20260222143045)</option>
                                    <option value="sku_based">SKU único aleatorio</option>
                                    <option value="manual">Manual (sin generar)</option>
                                </select>
                            </div>

                            <!-- Prefijo -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Prefijo (opcional)</label>
                                <input type="text" wire:model.live="code_prefix"
                                       placeholder="Ej: PROD, ART, SKU"
                                       class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>

                            <!-- Sufijo -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Sufijo (opcional)</label>
                                <input type="text" wire:model.live="code_suffix"
                                       placeholder="Ej: 2026, AR"
                                       class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>

                            <!-- Separador -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Separador</label>
                                <input type="text" wire:model.live="code_separator"
                                       placeholder="-"
                                       maxlength="2"
                                       class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>

                            @if($code_format === 'sequential')
                                <!-- Longitud del número -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Longitud del Número</label>
                                    <input type="number" wire:model.live="code_length"
                                           min="3" max="10"
                                           class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                </div>

                                <!-- Próximo número -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Próximo Número</label>
                                    <input type="number" wire:model.live="code_next_number"
                                           min="1"
                                           class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                </div>
                            @endif
                        </div>

                        <!-- Vista previa -->
                        <div class="p-4 bg-blue-50 border-l-4 border-blue-500 rounded-lg">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-blue-900">Vista Previa del Código:</p>
                                    <p class="text-lg font-mono font-bold text-blue-700">{{ $codePreview }}</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Requerir código único -->
                    <div class="flex items-center">
                        <input type="checkbox" wire:model="require_unique_code" id="require_unique_code"
                               class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <label for="require_unique_code" class="ml-2 text-sm text-gray-700">
                            Requerir que el código interno sea único
                        </label>
                    </div>
                </div>
            </div>

            <!-- Sección: Stock y Precios -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Stock y Precios
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">Configura las reglas para gestión de stock y precios</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Stock crítico por defecto -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Stock Crítico por Defecto</label>
                            <input type="number" wire:model="default_stock_critical"
                                   min="0" step="0.01"
                                   class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                        </div>

                        <!-- IVA por defecto -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">IVA por Defecto (%)</label>
                            <input type="number" wire:model="default_tax_rate"
                                   min="0" max="100" step="0.01"
                                   class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                        </div>

                        <!-- Markup por defecto -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Markup por Defecto (%)</label>
                            <input type="number" wire:model="default_markup"
                                   min="0" step="0.01"
                                   class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                            <p class="text-xs text-gray-500 mt-1">Se usa para calcular precio automático desde el costo</p>
                        </div>
                    </div>

                    <!-- Opciones booleanas -->
                    <div class="space-y-3">
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <input type="checkbox" wire:model="track_stock" id="track_stock"
                                   class="h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500">
                            <label for="track_stock" class="ml-3 text-sm text-gray-700">
                                <span class="font-medium">Rastrear stock</span> - Controlar inventario de productos
                            </label>
                        </div>

                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <input type="checkbox" wire:model="allow_negative_stock" id="allow_negative_stock"
                                   class="h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500">
                            <label for="allow_negative_stock" class="ml-3 text-sm text-gray-700">
                                <span class="font-medium">Permitir stock negativo</span> - Ventas con stock en cero
                            </label>
                        </div>

                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <input type="checkbox" wire:model="require_cost" id="require_cost"
                                   class="h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500">
                            <label for="require_cost" class="ml-3 text-sm text-gray-700">
                                <span class="font-medium">Requerir costo</span> - Obligatorio ingresar costo al crear producto
                            </label>
                        </div>

                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <input type="checkbox" wire:model="require_price" id="require_price"
                                   class="h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500">
                            <label for="require_price" class="ml-3 text-sm text-gray-700">
                                <span class="font-medium">Requerir precio</span> - Obligatorio ingresar precio al crear producto
                            </label>
                        </div>

                        <div class="flex items-center p-3 bg-green-50 rounded-lg border border-green-200">
                            <input type="checkbox" wire:model="auto_calculate_price" id="auto_calculate_price"
                                   class="h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500">
                            <label for="auto_calculate_price" class="ml-3 text-sm text-gray-700">
                                <span class="font-medium text-green-800">Calcular precio automáticamente</span> - Precio = Costo × (1 + Markup%)
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección: Valores por Defecto -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-purple-50 to-pink-50 px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Valores por Defecto
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">Define valores predeterminados para nuevos productos</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Marca por Defecto</label>
                            <input type="text" wire:model="default_marca"
                                   placeholder="Sin definir"
                                   class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Línea por Defecto</label>
                            <input type="text" wire:model="default_linea"
                                   placeholder="Sin definir"
                                   class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Familia por Defecto</label>
                            <input type="text" wire:model="default_familia"
                                   placeholder="Sin definir"
                                   class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Temporada por Defecto</label>
                            <input type="text" wire:model="default_temporada"
                                   placeholder="Sin definir"
                                   class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-sm">
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <input type="checkbox" wire:model="default_estado" id="default_estado"
                                   class="h-4 w-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                            <label for="default_estado" class="ml-3 text-sm text-gray-700">
                                <span class="font-medium">Estado activo por defecto</span>
                            </label>
                        </div>

                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <input type="checkbox" wire:model="default_es_vendible" id="default_es_vendible"
                                   class="h-4 w-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                            <label for="default_es_vendible" class="ml-3 text-sm text-gray-700">
                                <span class="font-medium">Producto vendible por defecto</span>
                            </label>
                        </div>

                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <input type="checkbox" wire:model="default_remitible" id="default_remitible"
                                   class="h-4 w-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                            <label for="default_remitible" class="ml-3 text-sm text-gray-700">
                                <span class="font-medium">Producto remitible por defecto</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección: Variantes -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-orange-50 to-amber-50 px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                        Productos Configurables y Variantes
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">Reglas para productos con variantes (color, talle, etc.)</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="flex items-center p-3 bg-orange-50 rounded-lg border border-orange-200">
                        <input type="checkbox" wire:model="require_variants_for_configurable" id="require_variants_for_configurable"
                               class="h-4 w-4 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                        <label for="require_variants_for_configurable" class="ml-3 text-sm text-gray-700">
                            <span class="font-medium text-orange-800">Requerir variantes para productos configurables</span>
                        </label>
                    </div>

                    @if($require_variants_for_configurable)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Mínimo de Variantes Requeridas</label>
                            <input type="number" wire:model="min_variants"
                                   min="1" max="100"
                                   class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 text-sm">
                        </div>
                    @endif
                </div>
            </div>

            <!-- Sección: Imágenes -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-pink-50 to-rose-50 px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Imágenes de Productos
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">Reglas para las imágenes de los productos</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="flex items-center p-3 bg-pink-50 rounded-lg border border-pink-200">
                        <input type="checkbox" wire:model="require_images" id="require_images"
                               class="h-4 w-4 text-pink-600 border-gray-300 rounded focus:ring-pink-500">
                        <label for="require_images" class="ml-3 text-sm text-gray-700">
                            <span class="font-medium text-pink-800">Requerir al menos una imagen al crear producto</span>
                        </label>
                    </div>

                    @if($require_images)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Mínimo de Imágenes</label>
                                <input type="number" wire:model="min_images"
                                       min="1" max="20"
                                       class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500 text-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Máximo de Imágenes</label>
                                <input type="number" wire:model="max_images"
                                       min="1" max="50"
                                       class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500 text-sm">
                            </div>
                        </div>
                    @endif

                    <!-- Toggle: Heredar imágenes del padre -->
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="flex-1">
                            <label class="text-sm font-semibold text-gray-900">Heredar imágenes del producto padre</label>
                            <p class="text-xs text-gray-600 mt-1">Si un producto variante no tiene imágenes propias, usa las del producto padre en el listado y edición.</p>
                        </div>
                        <button type="button" wire:click="$toggle('childInheritsParentImages')"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 {{ $childInheritsParentImages ? 'bg-blue-600' : 'bg-gray-200' }}">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $childInheritsParentImages ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Botón Guardar -->
            <div class="flex justify-end gap-3">
                <a href="/dashboard" wire:navigate
                   class="px-6 py-3 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all">
                    Cancelar
                </a>
                <button type="submit"
                        class="px-6 py-3 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-sm transition-all">
                    <span wire:loading.remove wire:target="save">Guardar Configuración</span>
                    <span wire:loading wire:target="save">Guardando...</span>
                </button>
            </div>
        </div>
    </form>
</div>
