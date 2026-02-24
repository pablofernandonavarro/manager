<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
        <a href="{{ route('sucursales.index') }}"
           class="p-2 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">{{ $sucursal->nombre }}</h1>
            <p class="mt-1 text-sm text-gray-500">Editar sucursal</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-6">
            <button wire:click="$set('tab', 'datos')"
                    class="py-3 px-1 text-sm font-medium border-b-2 transition-colors {{ $tab === 'datos' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Datos generales
            </button>
            <button wire:click="$set('tab', 'listas')"
                    class="py-3 px-1 text-sm font-medium border-b-2 transition-colors {{ $tab === 'listas' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Listas de precios
            </button>
            <button wire:click="$set('tab', 'stock')"
                    class="py-3 px-1 text-sm font-medium border-b-2 transition-colors {{ $tab === 'stock' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Stock
            </button>
        </nav>
    </div>

    <!-- Tab: Datos generales -->
    @if($tab === 'datos')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="space-y-4 max-w-lg">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                    <input type="text" wire:model="nombre"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('nombre') border-red-300 @enderror">
                    @error('nombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                    <input type="text" wire:model="direccion"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                    <input type="text" wire:model="telefono"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <button type="button" wire:click="saveDatos"
                        class="px-6 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                    Guardar datos
                </button>
            </div>
        </div>
    @endif

    <!-- Tab: Listas de precios -->
    @if($tab === 'listas')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-4">Listas de precios asignadas</h3>
            @if($todasLasListas->isEmpty())
                <p class="text-sm text-gray-500 italic">No hay listas de precios disponibles.</p>
            @else
                <div class="space-y-3">
                    @foreach($todasLasListas as $lista)
                        <div class="flex items-center justify-between p-3 rounded-lg border {{ isset($listasSeleccionadas[$lista->id]) && $listasSeleccionadas[$lista->id] ? 'border-blue-200 bg-blue-50' : 'border-gray-200' }}">
                            <label class="flex items-center gap-3 cursor-pointer flex-1">
                                <input type="checkbox"
                                       wire:model="listasSeleccionadas.{{ $lista->id }}"
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <div>
                                    <span class="text-sm font-medium text-gray-900">{{ $lista->nombre }}</span>
                                    @if($lista->descripcion)
                                        <p class="text-xs text-gray-500">{{ $lista->descripcion }}</p>
                                    @endif
                                </div>
                            </label>
                            @if(isset($listasSeleccionadas[$lista->id]) && $listasSeleccionadas[$lista->id])
                                <label class="flex items-center gap-1.5 text-xs text-blue-600 cursor-pointer ml-4">
                                    <input type="radio"
                                           wire:model="listaDefault"
                                           value="{{ $lista->id }}"
                                           class="text-blue-600 focus:ring-blue-500">
                                    Default
                                </label>
                            @endif
                        </div>
                    @endforeach
                </div>
                <button type="button" wire:click="saveListas"
                        class="mt-4 px-6 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                    Guardar listas
                </button>
            @endif
        </div>
    @endif

    <!-- Tab: Stock -->
    @if($tab === 'stock')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">Stock por producto en esta sucursal</h3>
                <button type="button" wire:click="saveStock"
                        class="px-4 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700 transition-colors">
                    Guardar stock
                </button>
            </div>
            @if($stockItems->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-gray-400 italic">
                    No hay stock registrado para esta sucursal todavía
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Cantidad</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($stockItems as $item)
                            <tr wire:key="stock-{{ $item->id }}" class="hover:bg-gray-50">
                                <td class="px-6 py-3 text-sm text-gray-900">{{ $item->product->nombre }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500">{{ $item->product->codigo_interno }}</td>
                                <td class="px-6 py-3 text-center">
                                    <input type="number"
                                           wire:model="stockEditable.{{ $item->product_id }}"
                                           min="0"
                                           class="w-24 px-2 py-1 text-center border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    @if(session('saved') || $this->getErrorBag()->isNotEmpty())
        <div class="fixed bottom-4 right-4 z-50">
            @if(session('saved'))
                <div class="bg-green-600 text-white px-4 py-2 rounded-lg shadow text-sm">
                    ✓ Guardado correctamente
                </div>
            @endif
        </div>
    @endif
</div>
