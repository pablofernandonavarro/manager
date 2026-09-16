<div class="max-w-3xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Comportamiento de remitos internos</h1>
        <p class="text-gray-600 mt-2">Configura cómo se comportan los remitos entre sucursales</p>
    </div>

    <form wire:submit="guardar" class="space-y-8">
        <!-- Ruta directa -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Ruta del remito</h3>
                    <p class="text-sm text-gray-600 mt-1">¿Pueden las sucursales enviarse mercadería directo?</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="rutaDirecta" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>
            <div class="mt-4 text-sm text-gray-600">
                @if ($rutaDirecta)
                    <p class="font-medium text-green-700">✓ Directo: sucursal A → sucursal B</p>
                @else
                    <p class="font-medium text-orange-700">→ Via Manager: sucursal A → Central → sucursal B</p>
                @endif
            </div>
        </div>

        <!-- Destino de rechazados -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Destino de mercadería rechazada</h3>
            <p class="text-sm text-gray-600 mb-4">¿Qué pasa con lo que no se recibe por defectos?</p>

            <div class="space-y-3">
                @foreach ($destinos as $valor => $label)
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition" wire:key="destino-{{ $valor }}">
                        <input type="radio" wire:model="destinoRechazados" value="{{ $valor }}" class="w-4 h-4 text-blue-600">
                        <div class="ml-3">
                            <p class="font-medium text-gray-900">{{ ucfirst($valor) }}</p>
                            <p class="text-sm text-gray-600">{{ $label }}</p>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        <!-- Mensaje de éxito -->
        @if ($mensaje)
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <p class="text-green-800">{{ $mensaje }}</p>
            </div>
        @endif

        <!-- Botón guardar -->
        <div class="flex items-center gap-3">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                Guardar cambios
            </button>
        </div>
    </form>
</div>
