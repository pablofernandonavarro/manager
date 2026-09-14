{{-- Datos de caja: solo para los roles que atienden cajas (cajero y supervisor). --}}
@if(in_array($role, \App\Models\User::ROLES_CAJA, true))
    <div class="rounded-lg border border-blue-200 bg-blue-50/50 p-4 space-y-4">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Caja</h3>
            <p class="text-xs text-gray-600 mt-0.5">
                @if($role === 'cajero')
                    Abre la caja con su PIN en la sucursal donde atiende. No entra al Manager.
                @else
                    Abre la caja y autoriza anulaciones y descuentos con su PIN, en todas las sucursales que supervisa.
                @endif
            </p>
        </div>

        <div class="max-w-xs">
            <label for="pin" class="block text-sm font-medium text-gray-700 mb-1">
                PIN de caja @if(! ($tienePin ?? false))<span class="text-red-500">*</span>@endif
            </label>
            <input type="password" id="pin" wire:model="pin" inputmode="numeric" maxlength="6" autocomplete="new-password"
                   placeholder="{{ ($tienePin ?? false) ? 'Dejar en blanco para no cambiar' : '4 a 6 números' }}"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg tracking-widest focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('pin') border-red-500 @enderror">
            @error('pin') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <p class="block text-sm font-medium text-gray-700 mb-2">
                {{ $role === 'cajero' ? 'Sucursal' : 'Sucursales que supervisa' }} <span class="text-red-500">*</span>
            </p>
            <div class="flex flex-wrap gap-x-5 gap-y-2">
                @foreach($listaSucursales as $s)
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700" wire:key="sucursal-{{ $s->id }}">
                        @if($role === 'cajero')
                            <input type="radio" value="{{ $s->id }}" wire:click="elegirSucursal({{ $s->id }})"
                                   @checked(in_array($s->id, array_map('intval', $sucursales), true))
                                   class="border-gray-300 text-blue-600">
                        @else
                            <input type="checkbox" value="{{ $s->id }}" wire:model="sucursales" class="rounded border-gray-300 text-blue-600">
                        @endif
                        {{ $s->nombre }}
                    </label>
                @endforeach
            </div>
            @error('sucursales') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @error('sucursales.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>
@endif
