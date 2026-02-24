<div class="space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Atributos de Variantes</h1>
            <p class="mt-2 text-sm text-gray-700">Configura los tipos de atributos y sus valores para generar variantes de productos</p>
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

    @if (session()->has('error'))
        <div class="rounded-lg bg-red-50 p-4 border-l-4 border-red-400">
            <div class="flex">
                <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <p class="ml-3 text-sm font-medium text-red-800">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <!-- Tipos de atributos existentes -->
    <div class="space-y-4">
        @foreach($attributeTypes as $type)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <!-- Header del tipo -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <h2 class="text-lg font-semibold text-gray-900">{{ $type->nombre }}</h2>
                            @if($type->product_column)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    columna: {{ $type->product_column }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                    atributo extra (JSON)
                                </span>
                            @endif
                            <span class="text-xs text-gray-500">slug: {{ $type->slug }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <!-- Toggle activo -->
                            <button type="button" wire:click="toggleTypeActive({{ $type->id }})"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 {{ $type->activo ? 'bg-blue-600' : 'bg-gray-200' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $type->activo ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                            <!-- Botón eliminar -->
                            <button type="button" wire:click="deleteType({{ $type->id }})"
                                    wire:confirm="¿Eliminar este tipo de atributo?"
                                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Valores -->
                <div class="p-6">
                    <!-- Chips de valores existentes -->
                    <div class="flex flex-wrap gap-2 mb-4">
                        @forelse($type->values as $value)
                            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium border transition-all
                                {{ $value->activo ? 'bg-white border-gray-300 text-gray-700' : 'bg-gray-50 border-gray-200 text-gray-400 line-through' }}">
                                <button type="button" wire:click="toggleValueActive({{ $value->id }})"
                                        class="text-xs {{ $value->activo ? 'text-green-600 hover:text-green-800' : 'text-gray-400 hover:text-green-600' }}">
                                    {{ $value->activo ? '●' : '○' }}
                                </button>
                                <span>{{ $value->valor }}</span>
                                <button type="button" wire:click="deleteValue({{ $value->id }})"
                                        wire:confirm="¿Eliminar este valor?"
                                        class="text-gray-400 hover:text-red-600 transition-colors ml-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400 italic">Sin valores todavía</p>
                        @endforelse
                    </div>

                    <!-- Input para agregar valor -->
                    <div class="flex gap-2">
                        <input type="text"
                               wire:model="newValueInputs.{{ $type->id }}"
                               wire:keydown.enter.prevent="addValue({{ $type->id }})"
                               placeholder="Nuevo valor..."
                               class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <button type="button" wire:click="addValue({{ $type->id }})"
                                class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors whitespace-nowrap">
                            + Agregar
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Agregar nuevo tipo -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Nuevo tipo de atributo</h3>
        <div class="flex gap-2">
            <input type="text"
                   wire:model="newTypeName"
                   wire:keydown.enter.prevent="addType"
                   placeholder="Ej: Material, Género, Temporada..."
                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('newTypeName') border-red-300 @enderror">
            <button type="button" wire:click="addType"
                    class="px-6 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors whitespace-nowrap">
                + Crear tipo
            </button>
        </div>
        @error('newTypeName')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
        <p class="mt-2 text-xs text-gray-500">
            Los tipos sin <em>product_column</em> guardan su valor en JSON (campo <code>atributos_extra</code> del producto).
            Para asignar una columna dedicada, edita directamente en la base de datos.
        </p>
    </div>
</div>
