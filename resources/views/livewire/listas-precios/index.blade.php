<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Listas de Precios</h1>
            <p class="mt-2 text-sm text-gray-700">El <strong>factor</strong> multiplica el precio base del producto. Factor 1 = precio público. Factor 0.7 = 30% de descuento.</p>
        </div>
    </div>

    <!-- Formulario nueva lista -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Nueva lista de precios</h3>
        <div class="flex gap-2">
            <input type="text"
                   wire:model="nombre"
                   wire:keydown.enter.prevent="save"
                   placeholder="Ej: MAYORISTA, VIP..."
                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('nombre') border-red-300 @enderror">
            <input type="text"
                   wire:model="descripcion"
                   placeholder="Descripción (opcional)"
                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <div class="flex items-center gap-1">
                <span class="text-sm text-gray-500 whitespace-nowrap">Factor ×</span>
                <input type="number"
                       wire:model="factor"
                       min="0.0001" max="99.9999" step="0.0001"
                       class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('factor') border-red-300 @enderror">
            </div>
            <button type="button" wire:click="save"
                    class="px-6 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors whitespace-nowrap">
                + Agregar
            </button>
        </div>
        @error('nombre') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('factor') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <!-- Listado -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripción</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Factor ×</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Precio $1000</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Overrides</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Activo</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($listas as $lista)
                    <tr wire:key="lista-{{ $lista->id }}" class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            @if($editingId === $lista->id)
                                <input type="text"
                                       wire:model="editingNombre"
                                       wire:keydown.enter.prevent="saveEdit"
                                       wire:keydown.escape.prevent="cancelEdit"
                                       class="w-full px-2 py-1 border border-blue-400 rounded text-sm focus:ring-2 focus:ring-blue-500">
                                @error('editingNombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            @else
                                <span class="text-sm font-medium text-gray-900 {{ !$lista->activo ? 'line-through text-gray-400' : '' }}">
                                    {{ $lista->nombre }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($editingId === $lista->id)
                                <input type="text"
                                       wire:model="editingDescripcion"
                                       wire:keydown.escape.prevent="cancelEdit"
                                       placeholder="Descripción (opcional)"
                                       class="w-full px-2 py-1 border border-blue-400 rounded text-sm focus:ring-2 focus:ring-blue-500">
                            @else
                                <span class="text-sm text-gray-500">{{ $lista->descripcion ?? '—' }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($editingId === $lista->id)
                                <input type="number"
                                       wire:model="editingFactor"
                                       min="0.0001" max="99.9999" step="0.0001"
                                       class="w-24 px-2 py-1 border border-blue-400 rounded text-sm text-center focus:ring-2 focus:ring-blue-500">
                                @error('editingFactor') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-mono font-medium
                                    {{ $lista->factor == 1 ? 'bg-gray-100 text-gray-700' : ($lista->factor < 1 ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800') }}">
                                    × {{ number_format($lista->factor, 4) }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center text-sm text-gray-500 font-mono">
                            ${{ number_format(1000 * $lista->factor, 2, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ $lista->detalles_count }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button type="button" wire:click="toggleActive({{ $lista->id }})"
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none {{ $lista->activo ? 'bg-blue-600' : 'bg-gray-200' }}">
                                <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform {{ $lista->activo ? 'translate-x-4.5' : 'translate-x-0.5' }}"></span>
                            </button>
                        </td>
                        <td class="px-6 py-4 text-right">
                            @if($editingId === $lista->id)
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" wire:click="saveEdit"
                                            class="px-3 py-1 bg-blue-600 text-white text-xs font-medium rounded hover:bg-blue-700 transition-colors">
                                        Guardar
                                    </button>
                                    <button type="button" wire:click="cancelEdit"
                                            class="px-3 py-1 bg-gray-100 text-gray-700 text-xs font-medium rounded hover:bg-gray-200 transition-colors">
                                        Cancelar
                                    </button>
                                </div>
                            @else
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('listas-precios.show', $lista) }}"
                                       class="p-1.5 text-gray-400 hover:text-purple-600 hover:bg-purple-50 rounded-lg transition-colors"
                                       title="Ver productos y precios">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </a>
                                    <a href="/listas-precios/{{ $lista->id }}/editar"
                                       class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                       title="Overrides de precios">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                        </svg>
                                    </a>
                                    <button type="button" wire:click="startEdit({{ $lista->id }})"
                                            class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button type="button" wire:click="delete({{ $lista->id }})"
                                            wire:confirm="¿Eliminar la lista '{{ $lista->nombre }}'?"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-400 italic">
                            No hay listas de precios creadas todavía
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
