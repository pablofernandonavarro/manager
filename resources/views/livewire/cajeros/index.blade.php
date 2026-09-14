@php $nombresSucursales = $listaSucursales->pluck('nombre', 'id'); @endphp

<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Cajeros</h1>
            <p class="mt-2 text-sm text-gray-700">Personas que atienden las cajas. Abren la caja con su PIN; los supervisores además autorizan anulaciones y descuentos.</p>
        </div>
        <button type="button" wire:click="crear"
                class="mt-4 sm:mt-0 inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors">
            + Nuevo cajero
        </button>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-green-50 p-4 border-l-4 border-green-400">
            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rol</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sucursales</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Activo</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($cajeros as $c)
                    <tr wire:key="cajero-{{ $c->id }}">
                        <td class="px-6 py-4 text-sm font-medium {{ $c->activo ? 'text-gray-900' : 'text-gray-400 line-through' }}">{{ $c->nombre }}</td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $c->rol === 'supervisor' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-700' }}">
                                {{ \App\Models\Cajero::ROLES[$c->rol] }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $c->sucursales ? collect($c->sucursales)->map(fn ($id) => $nombresSucursales[$id] ?? "#{$id}")->implode(', ') : 'Todas' }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button type="button" wire:click="alternarActivo({{ $c->id }})"
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors {{ $c->activo ? 'bg-blue-600' : 'bg-gray-200' }}">
                                <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform {{ $c->activo ? 'translate-x-4.5' : 'translate-x-0.5' }}"></span>
                            </button>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button type="button" wire:click="editar({{ $c->id }})" class="text-sm text-blue-600 hover:text-blue-800">Editar</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-400">Todavía no hay cajeros. Mientras no haya, las cajas se abren escribiendo un nombre.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($modalAbierto)
        <div style="position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;padding:1rem"
             wire:click.self="cerrarModal">
            <form wire:submit="guardar" class="bg-white rounded-xl shadow-2xl w-full max-w-md" @click.stop>
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">{{ $editandoId ? 'Editar cajero' : 'Nuevo cajero' }}</h3>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                        <input type="text" wire:model="nombre" maxlength="100" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        @error('nombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Rol</label>
                            <select wire:model="rol" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                @foreach(\App\Models\Cajero::ROLES as $valor => $etiqueta)
                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">PIN {{ $editandoId ? '(vacío = no cambiar)' : '' }}</label>
                            <input type="password" wire:model="pin" inputmode="numeric" maxlength="6" autocomplete="new-password" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm tracking-widest">
                            @error('pin') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-700 mb-1">Sucursales <span class="text-xs text-gray-400">(ninguna = todas)</span></p>
                        <div class="flex flex-wrap gap-3">
                            @foreach($listaSucursales as $s)
                                <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                                    <input type="checkbox" wire:model="sucursales" value="{{ $s->id }}" class="rounded border-gray-300 text-blue-600"> {{ $s->nombre }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="activo" class="rounded border-gray-300 text-blue-600"> Activo
                    </label>
                </div>
                <div class="px-6 py-4 bg-gray-50 rounded-b-xl flex justify-end gap-3 border-t border-gray-200">
                    <button type="button" wire:click="cerrarModal" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg">Cancelar</button>
                    <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700">Guardar</button>
                </div>
            </form>
        </div>
    @endif
</div>
