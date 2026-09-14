@php
    use App\Models\PromocionBancaria;
    $nombresSucursales = $listaSucursales->pluck('nombre', 'id');
@endphp

<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Promociones bancarias</h1>
            <p class="mt-2 text-sm text-gray-700">Descuentos, reintegros y cuotas sin interés que las cajas aplican al cobrar con tarjeta o QR</p>
        </div>
        <button type="button" wire:click="crear"
                class="mt-4 sm:mt-0 inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nueva promoción
        </button>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-green-50 p-4 border-l-4 border-green-400">
            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Promoción</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aplica a</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Beneficio</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cuándo</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Activa</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($promociones as $p)
                        <tr wire:key="promo-{{ $p->id }}" class="{{ $p->estaVigente() ? '' : 'bg-gray-50' }}">
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-gray-900">{{ $p->nombre }}</div>
                                <div class="text-xs text-gray-500">{{ $p->banco ?: 'Cualquier banco' }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                {{ collect($p->medios)->map(fn ($m) => PromocionBancaria::MEDIOS[$m] ?? $m)->implode(', ') }}
                                <div class="text-xs text-gray-500">
                                    {{ $p->tarjetas ? collect($p->tarjetas)->map(fn ($t) => PromocionBancaria::TARJETAS[$t] ?? $t)->implode(', ') : 'Todas las tarjetas' }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ $p->sucursales ? collect($p->sucursales)->map(fn ($id) => $nombresSucursales[$id] ?? "#{$id}")->implode(', ') : 'Todas las sucursales' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                @if($p->porcentaje > 0)
                                    <span class="font-semibold text-gray-900">{{ rtrim(rtrim(number_format($p->porcentaje, 2, ',', '.'), '0'), ',') }}%</span>
                                    {{ $p->modalidad === 'descuento' ? 'de descuento en caja' : 'de reintegro del banco' }}
                                    @if($p->tope)
                                        <div class="text-xs text-gray-500">Tope ${{ number_format($p->tope, 0, ',', '.') }} por compra</div>
                                    @endif
                                @endif
                                @if($p->cuotas_sin_interes)
                                    <div class="text-sm">{{ $p->cuotas_sin_interes }} cuotas sin interés</div>
                                @endif
                                @if($p->monto_minimo)
                                    <div class="text-xs text-gray-500">Compra mínima ${{ number_format($p->monto_minimo, 0, ',', '.') }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                {{ $p->dias_semana ? collect($p->dias_semana)->sort()->map(fn ($d) => PromocionBancaria::DIAS[$d] ?? $d)->implode(' ') : 'Todos los días' }}
                                <div class="text-xs {{ $p->estaVigente() ? 'text-gray-500' : 'text-amber-700' }}">
                                    @if($p->vigencia_desde || $p->vigencia_hasta)
                                        {{ $p->vigencia_desde?->format('d/m/Y') ?? '…' }} → {{ $p->vigencia_hasta?->format('d/m/Y') ?? '…' }}
                                    @else
                                        Sin vencimiento
                                    @endif
                                    @unless($p->estaVigente()) · no vigente hoy @endunless
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button type="button" wire:click="alternarActiva({{ $p->id }})"
                                        class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors {{ $p->activa ? 'bg-blue-600' : 'bg-gray-200' }}">
                                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform {{ $p->activa ? 'translate-x-4.5' : 'translate-x-0.5' }}"></span>
                                </button>
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <button type="button" wire:click="editar({{ $p->id }})" class="text-sm text-blue-600 hover:text-blue-800 mr-3">Editar</button>
                                <button type="button" wire:click="eliminar({{ $p->id }})"
                                        wire:confirm="¿Eliminar la promoción «{{ $p->nombre }}»? Las ventas ya hechas conservan el nombre."
                                        class="text-sm text-red-600 hover:text-red-800">Eliminar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-400">
                                Todavía no hay promociones. Creá la primera con «Nueva promoción».
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($modalAbierto)
        <div style="position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);display:flex;align-items:flex-start;justify-content:center;overflow-y:auto;padding:2rem 1rem"
             wire:click.self="cerrarModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl" @click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">{{ $editandoId ? 'Editar promoción' : 'Nueva promoción' }}</h3>
                    <button type="button" wire:click="cerrarModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form wire:submit="guardar" class="px-6 py-5 space-y-5">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                            <input type="text" wire:model="nombre" placeholder="Ej: Galicia 20% los jueves"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                            @error('nombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Banco / billetera</label>
                            <input type="text" wire:model="banco" placeholder="Vacío = cualquier banco"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                            @error('banco') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-1">Medios de pago</p>
                            <div class="flex flex-wrap gap-3">
                                @foreach(PromocionBancaria::MEDIOS as $valor => $etiqueta)
                                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                                        <input type="checkbox" wire:model="medios" value="{{ $valor }}" class="rounded border-gray-300 text-blue-600"> {{ $etiqueta }}
                                    </label>
                                @endforeach
                            </div>
                            @error('medios') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-1">Tarjetas <span class="text-xs text-gray-400">(ninguna = todas)</span></p>
                            <div class="flex flex-wrap gap-3">
                                @foreach(PromocionBancaria::TARJETAS as $valor => $etiqueta)
                                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                                        <input type="checkbox" wire:model="tarjetas" value="{{ $valor }}" class="rounded border-gray-300 text-blue-600"> {{ $etiqueta }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de beneficio</label>
                            <select wire:model.live="modalidad" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                <option value="descuento">Descuento en caja</option>
                                <option value="reintegro">Reintegro del banco</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ $modalidad === 'descuento' ? 'Se cobra menos en el momento.' : 'Se cobra completo; el banco devuelve después. La caja lo informa.' }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Porcentaje</label>
                            <input type="number" step="0.01" min="0" max="100" wire:model="porcentaje" placeholder="Ej: 20"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                            @error('porcentaje') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tope por compra ($)</label>
                            <input type="number" step="0.01" min="0" wire:model="tope" placeholder="Sin tope"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                            @error('tope') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cuotas sin interés</label>
                            <input type="number" min="2" max="36" wire:model="cuotasSinInteres" placeholder="Ninguna"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                            @error('cuotasSinInteres') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Compra mínima ($)</label>
                            <input type="number" step="0.01" min="0" wire:model="montoMinimo" placeholder="Sin mínimo"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                            @error('montoMinimo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex items-end">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 pb-2">
                                <input type="checkbox" wire:model="activa" class="rounded border-gray-300 text-blue-600"> Activa
                            </label>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-1">Días <span class="text-xs text-gray-400">(ninguno = todos)</span></p>
                            <div class="flex flex-wrap gap-2">
                                @foreach(PromocionBancaria::DIAS as $valor => $etiqueta)
                                    <label class="inline-flex items-center gap-1 text-sm text-gray-700">
                                        <input type="checkbox" wire:model="diasSemana" value="{{ $valor }}" class="rounded border-gray-300 text-blue-600"> {{ $etiqueta }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                                <input type="date" wire:model="vigenciaDesde" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                                <input type="date" wire:model="vigenciaHasta" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm">
                                @error('vigenciaHasta') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
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

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
                        <textarea wire:model="observaciones" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                        <button type="button" wire:click="cerrarModal" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancelar</button>
                        <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
