@php
    use App\Models\Comprobante;
    $pesos = fn ($v) => '$'.number_format((float) $v, 2, ',', '.');
@endphp

<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Clientes</h1>
            <p class="mt-2 text-sm text-gray-700">Datos para facturar y cuenta corriente. Las cajas los reciben con el saldo para vender a cuenta y cobrar sin conexión.</p>
        </div>
        <button type="button" wire:click="crear"
                class="mt-4 sm:mt-0 inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors">
            + Nuevo cliente
        </button>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-green-50 p-4 border-l-4 border-green-400">
            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-wrap items-center gap-4">
        <input type="text" wire:model.live.debounce.300ms="buscar" placeholder="Buscar por nombre, CUIT o DNI..."
               class="flex-1 min-w-64 px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" wire:model.live="soloConDeuda" class="rounded border-gray-300 text-blue-600"> Solo con deuda
        </label>
        <span class="text-sm text-gray-600">Deuda total en cuentas corrientes: <strong class="text-gray-900">{{ $pesos($deudaTotal) }}</strong></span>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Documento</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Condición IVA</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cuenta corriente</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($clientes as $c)
                    <tr wire:key="cliente-{{ $c->id }}" class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium {{ $c->activo ? 'text-gray-900' : 'text-gray-400 line-through' }}">{{ $c->nombre }}</div>
                            <div class="text-xs text-gray-500">{{ collect([$c->telefono, $c->email])->filter()->implode(' · ') }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700 font-mono">{{ $c->documentoFormateado() ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ Comprobante::CONDICIONES_IVA[$c->condicion_iva] ?? '' }}</td>
                        <td class="px-6 py-4 text-right">
                            @if($c->cuenta_corriente || (float) $c->saldo != 0)
                                <div class="text-sm font-semibold {{ (float) $c->saldo > 0 ? 'text-red-700' : 'text-gray-900' }}">{{ $pesos($c->saldo ?? 0) }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ $c->cuenta_corriente ? ($c->limite_credito !== null ? 'Límite '.$pesos($c->limite_credito) : 'Sin límite') : 'Deshabilitada' }}
                                </div>
                            @else
                                <span class="text-xs text-gray-400">No</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <a href="{{ route('clientes.show', $c) }}" class="text-sm text-blue-600 hover:text-blue-800 mr-3">Cuenta</a>
                            <button type="button" wire:click="editar({{ $c->id }})" class="text-sm text-blue-600 hover:text-blue-800">Editar</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-400">No hay clientes con estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-3 border-t border-gray-200">{{ $clientes->links() }}</div>
    </div>

    @if($modalAbierto)
        <div style="position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;padding:1rem"
             wire:click.self="cerrarModal">
            <form wire:submit="guardar" class="bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-full overflow-y-auto" @click.stop>
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">{{ $editandoId ? 'Editar cliente' : 'Nuevo cliente' }}</h3>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre o razón social</label>
                        <input type="text" wire:model="nombre" maxlength="150" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        @error('nombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Condición frente al IVA</label>
                            <select wire:model="condicionIva" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                @foreach(Comprobante::CONDICIONES_IVA as $codigo => $etiqueta)
                                    <option value="{{ $codigo }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">CUIT o DNI</label>
                            <input type="text" wire:model="documento" maxlength="20" inputmode="numeric" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono">
                            @error('documento') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                            <input type="text" wire:model="telefono" maxlength="50" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" wire:model="email" maxlength="150" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Domicilio</label>
                        <input type="text" wire:model="domicilio" maxlength="200" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4 space-y-3">
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input type="checkbox" wire:model.live="cuentaCorriente" class="rounded border-gray-300 text-blue-600"> Habilitar cuenta corriente (vender a cuenta)
                        </label>
                        @if($cuentaCorriente)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Límite de crédito <span class="text-xs text-gray-400">(vacío = sin límite)</span></label>
                                <input type="number" step="0.01" min="0" wire:model="limiteCredito" class="w-48 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                @error('limiteCredito') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
                        <textarea wire:model="observaciones" rows="2" maxlength="1000" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></textarea>
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
