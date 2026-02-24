<div class="space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="/listas-precios" class="hover:text-blue-600 transition-colors">Listas de precios</a>
                <span>/</span>
                <span class="text-gray-900 font-medium">{{ $lista->nombre }}</span>
            </div>
            <h1 class="text-3xl font-bold text-gray-900">{{ $lista->nombre }}</h1>
            @if($lista->descripcion)
                <p class="mt-1 text-sm text-gray-500">{{ $lista->descripcion }}</p>
            @endif
        </div>
        <div class="mt-4 sm:mt-0">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $lista->activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                {{ $lista->activo ? 'Activa' : 'Inactiva' }}
            </span>
        </div>
    </div>

    <!-- Agregar producto -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Agregar precio de producto</h3>
        <p class="mb-3 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2">
            Solo cargá overrides para productos que tengan un precio <strong>diferente</strong> al que da el factor × precio base.
            El resto se calcula automáticamente.
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">

            <!-- Búsqueda de producto -->
            <div class="sm:col-span-2 relative">
                @if($productoSeleccionadoId)
                    <div class="flex items-center gap-2 px-3 py-2 bg-blue-50 border border-blue-300 rounded-lg">
                        <span class="text-sm text-blue-800 flex-1 truncate">{{ $productoSeleccionadoNombre }}</span>
                        <button type="button" wire:click="$set('productoSeleccionadoId', null); $set('productoSeleccionadoNombre', '')"
                                class="text-blue-400 hover:text-blue-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                @else
                    <input type="text"
                           wire:model.live="busquedaProducto"
                           placeholder="Buscar por nombre o código..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    @if($productosSugeridos->isNotEmpty())
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                            @foreach($productosSugeridos as $producto)
                                <button type="button"
                                        wire:click="seleccionarProducto({{ $producto->id }}, '{{ addslashes($producto->nombre) }}')"
                                        class="w-full text-left px-4 py-2.5 text-sm hover:bg-blue-50 border-b border-gray-100 last:border-0">
                                    <span class="font-medium text-gray-900">{{ $producto->nombre }}</span>
                                    <span class="ml-2 text-xs text-gray-400">{{ $producto->codigo_interno }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                @endif
                @error('productoSeleccionadoId')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Precio -->
            <div>
                <input type="number"
                       wire:model="nuevoPrecioOverride"
                       placeholder="Precio override"
                       min="0"
                       step="0.01"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('nuevoPrecioOverride') border-red-300 @enderror">
                @error('nuevoPrecioOverride')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Botón agregar -->
            <div>
                <button type="button" wire:click="agregarPrecio"
                        class="w-full px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
                    + Agregar
                </button>
            </div>

            <!-- Vigencias (opcionales) -->
            <div>
                <label class="block text-xs text-gray-500 mb-1">Vigencia desde (opcional)</label>
                <input type="date"
                       wire:model="nuevaVigenciaDesde"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Vigencia hasta (opcional)</label>
                <input type="date"
                       wire:model="nuevaVigenciaHasta"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('nuevaVigenciaHasta')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    <!-- Tabla de precios -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">
                Precios cargados
                <span class="ml-2 text-sm font-normal text-gray-500">({{ $detalles->count() }} productos)</span>
            </h3>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Precio override</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Vigencia desde</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Vigencia hasta</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($detalles as $detalle)
                    <tr wire:key="detalle-{{ $detalle->id }}" class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-500 font-mono">
                            {{ $detalle->product->codigo_interno ?? '—' }}
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm font-medium text-gray-900">
                                {{ $detalle->product->nombre ?? '—' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="text-sm font-semibold text-gray-900">
                                ${{ number_format($detalle->precio_override, 2, ',', '.') }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center text-sm text-gray-500">
                            {{ $detalle->vigencia_desde?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-6 py-4 text-center text-sm text-gray-500">
                            {{ $detalle->vigencia_hasta?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($detalle->estaVigente())
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Vigente</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Vencido</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button type="button"
                                    wire:click="eliminarDetalle({{ $detalle->id }})"
                                    wire:confirm="¿Eliminar el precio de '{{ $detalle->product->nombre ?? '' }}'?"
                                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-400 italic">
                            No hay precios cargados en esta lista todavía
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
