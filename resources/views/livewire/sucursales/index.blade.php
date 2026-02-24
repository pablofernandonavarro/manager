<div class="space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Sucursales</h1>
            <p class="mt-2 text-sm text-gray-700">Administrá las sucursales y sus puntos de venta</p>
        </div>
    </div>

    <!-- Formulario nueva sucursal -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Nueva sucursal</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <input type="text"
                       wire:model="nombre"
                       wire:keydown.enter.prevent="save"
                       placeholder="Nombre de la sucursal..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('nombre') border-red-300 @enderror">
                @error('nombre')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex gap-2">
                <input type="text"
                       wire:model="direccion"
                       placeholder="Dirección (opcional)..."
                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <button type="button" wire:click="save"
                        class="px-6 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors whitespace-nowrap">
                    + Agregar
                </button>
            </div>
        </div>
    </div>

    <!-- Listado -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sucursal</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">POS</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Listas asignadas</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Activo</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($sucursales as $sucursal)
                    <tr wire:key="sucursal-{{ $sucursal->id }}" class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            @if($editingId === $sucursal->id)
                                <div class="space-y-1.5">
                                    <input type="text"
                                           wire:model="editingNombre"
                                           wire:keydown.enter.prevent="saveEdit"
                                           wire:keydown.escape.prevent="cancelEdit"
                                           class="w-full px-2 py-1 border border-blue-400 rounded text-sm focus:ring-2 focus:ring-blue-500"
                                           placeholder="Nombre">
                                    <input type="text"
                                           wire:model="editingDireccion"
                                           class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500"
                                           placeholder="Dirección">
                                </div>
                            @else
                                <div>
                                    <span class="text-sm font-medium text-gray-900 {{ !$sucursal->activo ? 'line-through text-gray-400' : '' }}">
                                        {{ $sucursal->nombre }}
                                    </span>
                                    @if($sucursal->direccion)
                                        <p class="text-xs text-gray-500 mt-0.5">{{ $sucursal->direccion }}</p>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-sm font-medium text-gray-700">{{ $sucursal->puntos_de_venta_count }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-1">
                                @foreach($sucursal->listasPrecios as $lista)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $lista->pivot->es_default ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $lista->nombre }}{{ $lista->pivot->es_default ? ' ★' : '' }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button type="button" wire:click="toggleActive({{ $sucursal->id }})"
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none {{ $sucursal->activo ? 'bg-blue-600' : 'bg-gray-200' }}">
                                <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform {{ $sucursal->activo ? 'translate-x-4.5' : 'translate-x-0.5' }}"></span>
                            </button>
                        </td>
                        <td class="px-6 py-4 text-right">
                            @if($editingId === $sucursal->id)
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
                                    <a href="{{ route('sucursales.edit', $sucursal->id) }}"
                                       class="p-1.5 text-gray-400 hover:text-purple-600 hover:bg-purple-50 rounded-lg transition-colors" title="Editar detalles">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    </a>
                                    <button type="button" wire:click="startEdit({{ $sucursal->id }})"
                                            class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button type="button" wire:click="delete({{ $sucursal->id }})"
                                            wire:confirm="¿Eliminar la sucursal '{{ $sucursal->nombre }}'?"
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
                        <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-400 italic">
                            No hay sucursales creadas todavía
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
