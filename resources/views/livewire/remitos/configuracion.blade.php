<div class="mx-auto max-w-4xl">
    {{-- Encabezado con banda degradada --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-blue-600 via-blue-700 to-indigo-700 p-6 shadow-lg shadow-blue-900/20 sm:p-8">
        <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10"></div>
        <div class="absolute -bottom-16 right-20 h-40 w-40 rounded-full bg-white/5"></div>

        <div class="relative flex items-center gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/20 ring-1 ring-white/30 backdrop-blur">
                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
            </div>
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Remitos internos</h1>
                <p class="mt-1 text-sm text-blue-100">Configura el comportamiento de envíos entre sucursales</p>
            </div>
        </div>
    </div>

    <form wire:submit="guardar" class="mt-6 space-y-6">
        {{-- Ruta directa --}}
        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm transition-shadow hover:shadow-md">
            <div class="p-6 sm:p-8">
                {{-- Título + toggle en la misma línea, apilados en móvil --}}
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between sm:gap-8">
                    <h2 class="flex items-center gap-2.5 text-lg font-bold text-gray-900 sm:text-xl">
                        <span class="text-2xl leading-none">🛣️</span> Ruta del remito
                    </h2>

                    {{-- Switch: etiqueta de estado + control. Verde = activo, gris = inactivo. --}}
                    <div class="flex shrink-0 items-center gap-3">
                        <span class="text-sm font-semibold {{ $rutaDirecta ? 'text-emerald-700' : 'text-gray-500' }}">
                            {{ $rutaDirecta ? 'Activada' : 'Desactivada' }}
                        </span>

                        {{-- type="button" es imprescindible: sin él el botón haría submit del form en lugar de alternar. --}}
                        <button type="button"
                                role="switch"
                                aria-checked="{{ $rutaDirecta ? 'true' : 'false' }}"
                                aria-label="Alternar ruta directa entre sucursales"
                                wire:click="$toggle('rutaDirecta')"
                                class="relative inline-flex h-9 w-16 shrink-0 cursor-pointer items-center rounded-full p-1 shadow-inner ring-1 transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 {{ $rutaDirecta ? 'bg-emerald-500 ring-emerald-600/30 hover:bg-emerald-600' : 'bg-gray-300 ring-gray-400/40 hover:bg-gray-400' }}">
                            <span class="pointer-events-none inline-block h-7 w-7 rounded-full bg-white shadow-md ring-1 ring-gray-900/10 transition-transform duration-200 ease-out {{ $rutaDirecta ? 'translate-x-7' : 'translate-x-0' }}"></span>
                        </button>
                    </div>
                </div>

                <p class="mt-4 leading-relaxed text-gray-600">
                    Define si las sucursales pueden enviarse mercadería directamente entre ellas, o si todo debe pasar por la sucursal Central.
                </p>

                {{-- Resumen del efecto actual --}}
                <div class="mt-5">
                    @if ($rutaDirecta)
                        <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <div class="min-w-0">
                                <p class="font-bold text-emerald-900">Ruta directa activa</p>
                                <p class="mt-0.5 text-sm text-emerald-700">Sucursal A → Sucursal B (sin pasar por Central)</p>
                            </div>
                        </div>
                    @else
                        <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <div class="min-w-0">
                                <p class="font-bold text-amber-900">Ruta vía Central</p>
                                <p class="mt-0.5 text-sm text-amber-700">Sucursal A → Central → Sucursal B</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- Destino de rechazados --}}
        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm transition-shadow hover:shadow-md">
            <div class="p-6 sm:p-8">
                <h2 class="flex items-center gap-2.5 text-lg font-bold text-gray-900 sm:text-xl">
                    <span class="text-2xl leading-none">📦</span> Destino de mercadería rechazada
                </h2>
                <p class="mt-2 leading-relaxed text-gray-600">
                    ¿Qué sucede con la mercadería que no se recibe por defectos o daños?
                </p>

                <div class="mt-6 space-y-3">
                    @foreach ($destinos as $valor => $label)
                        <label wire:key="destino-{{ $valor }}"
                               class="flex cursor-pointer items-start gap-4 rounded-xl border-2 p-4 transition-colors focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2 {{ $destinoRechazados === $valor ? 'border-blue-600 bg-blue-50' : 'border-gray-200 bg-gray-50 hover:border-gray-300 hover:bg-gray-100' }}">
                            {{-- accent-* colorea el radio nativo: el plugin de forms está en strategy 'class'. --}}
                            <input type="radio"
                                   wire:model="destinoRechazados"
                                   value="{{ $valor }}"
                                   class="mt-1 h-5 w-5 shrink-0 cursor-pointer accent-blue-600">
                            <div class="min-w-0 flex-1">
                                <p class="font-bold capitalize {{ $destinoRechazados === $valor ? 'text-blue-900' : 'text-gray-900' }}">{{ $valor }}</p>
                                <p class="mt-1 text-sm text-gray-600">{{ $label }}</p>
                            </div>
                            @if ($destinoRechazados === $valor)
                                <svg class="mt-0.5 h-6 w-6 shrink-0 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Mensaje de éxito --}}
        @if ($mensaje)
            <div role="status"
                 class="flex items-center gap-3 rounded-xl border border-emerald-300 bg-gradient-to-r from-emerald-50 to-green-50 p-4 shadow-sm">
                <svg class="h-6 w-6 shrink-0 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="font-semibold text-emerald-800">{{ $mensaje }}</p>
            </div>
        @endif

        {{-- Barra de acciones fija al pie: el botón queda siempre visible al hacer scroll. --}}
        <div class="sticky bottom-0 z-10 rounded-2xl border border-gray-200 bg-white/95 p-4 shadow-lg backdrop-blur sm:px-6">
            <div class="flex flex-col-reverse items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-center text-xs text-gray-500 sm:text-left">
                    Los cambios se aplican a los remitos que se creen a partir de ahora.
                </p>

                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="guardar"
                        class="inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-3.5 text-base font-bold text-white shadow-lg shadow-blue-600/30 transition-all duration-200 hover:from-blue-700 hover:to-indigo-700 hover:shadow-xl hover:shadow-blue-600/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
                    <svg wire:loading.remove wire:target="guardar" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    <svg wire:loading wire:target="guardar" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span wire:loading.remove wire:target="guardar">Guardar cambios</span>
                    <span wire:loading wire:target="guardar">Guardando…</span>
                </button>
            </div>
        </div>
    </form>
</div>
