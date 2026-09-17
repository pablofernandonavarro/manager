<div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 py-12">
    <div class="max-w-4xl mx-auto px-6">
        <!-- Header -->
        <div class="mb-10">
            <div class="flex items-center gap-3 mb-3">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Comportamiento de remitos</h1>
                    <p class="text-gray-600 text-sm mt-1">Configura las reglas de envío entre sucursales</p>
                </div>
            </div>
        </div>

        <form wire:submit="guardar" class="space-y-6">
            <!-- Ruta directa -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                <div class="p-8">
                    <div class="flex items-start justify-between gap-6">
                        <div class="flex-1">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                                <span class="text-2xl">🛣️</span> Ruta del remito
                            </h3>
                            <p class="text-gray-600 mt-2 leading-relaxed">Define si las sucursales pueden enviarse mercadería directamente entre ellas, o si todo debe pasar por la sucursal Central.</p>

                            <div class="mt-6 space-y-2">
                                @if ($rutaDirecta)
                                    <div class="flex items-center gap-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                                        <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <div>
                                            <p class="font-bold text-green-900">Ruta directa activa</p>
                                            <p class="text-sm text-green-700">Sucursal A → Sucursal B (sin pasar por Central)</p>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center gap-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                        <svg class="w-5 h-5 text-amber-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        <div>
                                            <p class="font-bold text-amber-900">Ruta vía Central</p>
                                            <p class="text-sm text-amber-700">Sucursal A → Central → Sucursal B</p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <button type="button" wire:click="$toggle('rutaDirecta')" class="flex-shrink-0 relative inline-flex h-12 w-20 items-center rounded-full transition-all duration-300 {{ $rutaDirecta ? 'bg-gradient-to-r from-green-400 to-green-600' : 'bg-gradient-to-r from-gray-300 to-gray-400' }} shadow-md hover:shadow-lg">
                            <span class="inline-block h-10 w-10 transform rounded-full bg-white shadow-lg transition-transform duration-300 {{ $rutaDirecta ? 'translate-x-9' : 'translate-x-1' }}"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Destino de rechazados -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                <div class="p-8">
                    <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2 mb-2">
                        <span class="text-2xl">📦</span> Destino de mercadería rechazada
                    </h3>
                    <p class="text-gray-600 leading-relaxed mb-8">¿Qué sucede con la mercadería que no se recibe por defectos o daños?</p>

                    <div class="space-y-3">
                        @foreach ($destinos as $valor => $label)
                            <label class="flex items-start p-4 border-2 rounded-lg cursor-pointer transition-all duration-200 {{ $destinoRechazados === $valor ? 'border-blue-600 bg-blue-50' : 'border-gray-200 bg-gray-50 hover:border-gray-300' }}" wire:key="destino-{{ $valor }}">
                                <input type="radio" wire:model="destinoRechazados" value="{{ $valor }}" class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0">
                                <div class="ml-4 flex-1">
                                    <p class="font-bold text-gray-900 text-lg capitalize">{{ $valor }}</p>
                                    <p class="text-gray-600 text-sm mt-1">{{ $label }}</p>
                                </div>
                                @if ($destinoRechazados === $valor)
                                    <svg class="w-6 h-6 text-blue-600 flex-shrink-0 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Mensaje de éxito -->
            @if ($mensaje)
                <div class="flex items-center gap-3 p-4 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-300 rounded-lg">
                    <svg class="w-6 h-6 text-green-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-green-800 font-medium">{{ $mensaje }}</p>
                </div>
            @endif

            <!-- Botón guardar -->
            <div class="flex gap-3">
                <button type="submit" class="flex items-center gap-2 px-8 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:shadow-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-200 font-semibold text-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>
