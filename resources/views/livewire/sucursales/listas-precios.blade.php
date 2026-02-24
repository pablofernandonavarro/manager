<div class="space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Listas de Precios por Sucursal</h1>
            <p class="mt-2 text-sm text-gray-700">Asigná qué listas de precios están disponibles en cada sucursal</p>
        </div>
    </div>

    <!-- Tabla de asignaciones -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50 z-10">
                            Sucursal
                        </th>
                        @foreach($listasPrecios as $lista)
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                {{ $lista['nombre'] }}
                                <span class="block text-xs text-gray-400 normal-case mt-0.5">Factor: {{ $lista['factor'] }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($sucursales as $sucursal)
                        <tr wire:key="sucursal-{{ $sucursal['id'] }}" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap sticky left-0 bg-white z-10">
                                <div class="flex items-center">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $sucursal['nombre'] }}</div>
                                        @if($sucursal['direccion'])
                                            <div class="text-xs text-gray-500">{{ $sucursal['direccion'] }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            @foreach($listasPrecios as $lista)
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    @php
                                        $asignado = $asignaciones[$sucursal['id']][$lista['id']]['asignado'] ?? false;
                                        $esDefault = $asignaciones[$sucursal['id']][$lista['id']]['es_default'] ?? false;
                                    @endphp

                                    <div class="flex flex-col items-center gap-2">
                                        <!-- Checkbox de asignación -->
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="checkbox"
                                                   wire:click="toggleAsignacion({{ $sucursal['id'] }}, {{ $lista['id'] }})"
                                                   {{ $asignado ? 'checked' : '' }}
                                                   class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500 cursor-pointer">
                                        </label>

                                        <!-- Botón para marcar como default -->
                                        @if($asignado)
                                            <button type="button"
                                                    wire:click="setDefault({{ $sucursal['id'] }}, {{ $lista['id'] }})"
                                                    class="inline-flex items-center px-2 py-1 rounded text-xs font-medium transition-colors {{ $esDefault ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600 hover:bg-yellow-50 hover:text-yellow-700' }}">
                                                @if($esDefault)
                                                    ★ Default
                                                @else
                                                    ☆ Marcar default
                                                @endif
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($listasPrecios) + 1 }}" class="px-6 py-10 text-center text-sm text-gray-400 italic">
                                No hay sucursales activas
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Leyenda -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex">
            <svg class="w-5 h-5 text-blue-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="text-sm text-blue-700">
                <p class="font-medium mb-1">Instrucciones:</p>
                <ul class="list-disc list-inside space-y-1 text-xs">
                    <li>Marcá el checkbox para asignar o desasignar una lista de precios a una sucursal</li>
                    <li>Cada sucursal debe tener al menos una lista marcada como "Default" para que el POS funcione correctamente</li>
                    <li>El POS usará la lista default cuando no se especifique una lista particular</li>
                </ul>
            </div>
        </div>
    </div>
</div>
