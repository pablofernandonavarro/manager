<div class="space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Temporadas</h1>
            <p class="mt-2 text-sm text-gray-700">Administrá las temporadas disponibles para los productos</p>
        </div>
    </div>

    <!-- Formulario nueva temporada -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Nueva temporada</h3>
        <div class="flex gap-2">
            <input type="text"
                   wire:model="nombre"
                   wire:keydown.enter.prevent="save"
                   placeholder="Ej: Verano, Invierno 2026..."
                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('nombre') border-red-300 @enderror">
            <input type="number"
                   wire:model="anio"
                   placeholder="Año"
                   min="2000" max="2100"
                   class="w-28 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('anio') border-red-300 @enderror">
            <button type="button" wire:click="save"
                    class="px-6 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors whitespace-nowrap">
                + Agregar
            </button>
        </div>
        <div class="flex gap-4 mt-1.5">
            @error('nombre')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
            @error('anio')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <!-- Listado -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Año</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Activo</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($temporadas as $temporada)
                    <tr wire:key="temporada-{{ $temporada->id }}" class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            @if($editingId === $temporada->id)
                                <input type="text"
                                       wire:model="editingNombre"
                                       wire:keydown.enter.prevent="saveEdit"
                                       wire:keydown.escape.prevent="cancelEdit"
                                       class="w-full px-2 py-1 border border-blue-400 rounded text-sm focus:ring-2 focus:ring-blue-500">
                                @error('editingNombre')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            @else
                                <span class="text-sm font-medium text-gray-900 {{ !$temporada->activo ? 'line-through text-gray-400' : '' }}">
                                    {{ $temporada->nombre }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($editingId === $temporada->id)
                                <input type="number"
                                       wire:model="editingAnio"
                                       wire:keydown.escape.prevent="cancelEdit"
                                       min="2000" max="2100"
                                       class="w-24 px-2 py-1 border border-blue-400 rounded text-sm focus:ring-2 focus:ring-blue-500">
                                @error('editingAnio')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            @else
                                <span class="text-sm text-gray-500">{{ $temporada->anio ?? '—' }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button type="button" wire:click="toggleActive({{ $temporada->id }})"
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none {{ $temporada->activo ? 'bg-blue-600' : 'bg-gray-200' }}">
                                <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform {{ $temporada->activo ? 'translate-x-4.5' : 'translate-x-0.5' }}"></span>
                            </button>
                        </td>
                        <td class="px-6 py-4 text-right">
                            @if($editingId === $temporada->id)
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
                                    <button type="button" wire:click="startEdit({{ $temporada->id }})"
                                            class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button type="button" wire:click="delete({{ $temporada->id }})"
                                            wire:confirm="¿Eliminar la temporada '{{ $temporada->nombre }}'?"
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
                        <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-400 italic">
                            No hay temporadas creadas todavía
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
