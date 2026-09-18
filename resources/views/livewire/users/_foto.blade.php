{{-- Foto de perfil: se guarda comprimida y las cajas la muestran en la sesión del vendedor. --}}
<div>
    <p class="block text-sm font-medium text-gray-700 mb-2">Foto de perfil <span class="text-xs text-gray-400">(opcional)</span></p>
    <div class="flex items-center gap-4">
        <div class="relative w-20 h-20 shrink-0 rounded-full overflow-hidden bg-gray-100 ring-1 ring-gray-200 flex items-center justify-center">
            @if($foto && str_starts_with((string) $foto->getMimeType(), 'image/'))
                <img src="{{ $foto->temporaryUrl() }}" alt="Vista previa" class="w-full h-full object-cover">
            @elseif($fotoActual)
                <img src="{{ $fotoActual }}" alt="{{ $name }}" class="w-full h-full object-cover">
            @else
                <svg class="w-10 h-10 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4.42 0-8 2.24-8 5v1a1 1 0 001 1h14a1 1 0 001-1v-1c0-2.76-3.58-5-8-5z"/>
                </svg>
            @endif
            <div wire:loading.flex wire:target="foto" class="absolute inset-0 bg-white/70 items-center justify-center">
                <svg class="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>
        </div>
        <div class="min-w-0">
            <label for="foto" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                {{ ($foto || $fotoActual) ? 'Cambiar foto' : 'Subir foto' }}
            </label>
            <input type="file" id="foto" wire:model="foto" accept="image/jpeg,image/png,image/webp" class="sr-only">
            <p class="mt-1 text-xs text-gray-500">JPG, PNG o WebP hasta 5 MB. Se recorta cuadrada y se comprime automáticamente.</p>
            @error('foto') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
