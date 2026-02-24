<div class="space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Puntos de venta</h1>
            <p class="mt-2 text-sm text-gray-700">Gestioná los terminales POS por sucursal</p>
        </div>
    </div>

    <!-- Formulario nuevo POS -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Nuevo punto de venta</h3>
        <div class="flex gap-3 flex-wrap">
            <div class="flex-1 min-w-48">
                <select wire:model="sucursalId"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('sucursalId') border-red-300 @enderror">
                    <option value="">Seleccioná una sucursal...</option>
                    @foreach($sucursales as $sucursal)
                        <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                    @endforeach
                </select>
                @error('sucursalId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex-1 min-w-48 flex gap-2">
                <input type="text"
                       wire:model="nombre"
                       wire:keydown.enter.prevent="save"
                       placeholder="Nombre del POS (ej: Caja 1)..."
                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('nombre') border-red-300 @enderror">
                <button type="button" wire:click="save"
                        class="px-6 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors whitespace-nowrap">
                    + Agregar
                </button>
            </div>
        </div>
        @error('nombre') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <!-- Listado -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sucursal</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Activo</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($puntosDeVenta as $pdv)
                    <tr wire:key="pdv-{{ $pdv->id }}" class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-600">{{ $pdv->sucursal->nombre }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm font-medium text-gray-900 {{ !$pdv->activo ? 'line-through text-gray-400' : '' }}">
                                {{ $pdv->nombre }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button type="button" wire:click="toggleActive({{ $pdv->id }})"
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none {{ $pdv->activo ? 'bg-blue-600' : 'bg-gray-200' }}">
                                <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform {{ $pdv->activo ? 'translate-x-4.5' : 'translate-x-0.5' }}"></span>
                            </button>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button"
                                        @click="confirm('¿Regenerar el secret de \'{{ $pdv->nombre }}\'?\nEl secret anterior dejará de funcionar.') && $wire.regenerarSecret({{ $pdv->id }})"
                                        class="px-2.5 py-1 text-xs font-medium text-yellow-700 bg-yellow-50 hover:bg-yellow-100 rounded-lg transition-colors">
                                    Secret
                                </button>
                                <button type="button" wire:click="generarToken({{ $pdv->id }})"
                                        class="px-2.5 py-1 text-xs font-medium text-purple-700 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                                    Token
                                </button>
                                <button type="button" wire:click="revocarTokens({{ $pdv->id }})"
                                        wire:confirm="¿Revocar todos los tokens de '{{ $pdv->nombre }}'?"
                                        class="px-2.5 py-1 text-xs font-medium text-orange-700 bg-orange-50 hover:bg-orange-100 rounded-lg transition-colors">
                                    Revocar
                                </button>
                                <button type="button" wire:click="delete({{ $pdv->id }})"
                                        wire:confirm="¿Eliminar el punto de venta '{{ $pdv->nombre }}'?"
                                        class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-400 italic">
                            No hay puntos de venta creados todavía
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
