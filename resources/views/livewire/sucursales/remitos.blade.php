@php
    // Los botones se esconden por comodidad; lo que protege son los authorize() del componente.
    $puedeCrear = auth()->user()->can('remitos.crear');
    $puedeRecibir = auth()->user()->can('remitos.recibir');
    $puedeCancelar = auth()->user()->can('remitos.cancelar');
@endphp

<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Remitos</h1>
            <p class="mt-2 text-sm text-gray-700">Mercadería enviada y recibida entre sucursales, Central incluida</p>
        </div>
        <div class="mt-4 sm:mt-0 flex items-center gap-3">
            @if($pendientes > 0)
                <button type="button" wire:click="$set('direccion', 'recibidos')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-100 text-yellow-800 text-sm font-semibold rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ $pendientes }} por recibir
                </button>
            @endif
            @if($puedeCrear)
                <a href="{{ route('sucursales.remitos.nuevo') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nuevo remito
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-green-50 p-4 border-l-4 border-green-400">
            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-lg bg-red-50 p-4 border-l-4 border-red-400">
            <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
        </div>
    @endif

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Sucursal</label>
                <select wire:model.live="sucursalSeleccionada"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    @foreach($sucursales as $sucursal)
                        <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}{{ $sucursal->isCentral() ? ' (Central)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Ver</label>
                <select wire:model.live="direccion"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="recibidos">Recibidos (llegan a esta sucursal)</option>
                    <option value="enviados">Enviados (salen de esta sucursal)</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                <select wire:model.live="filtroEstado"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="remitido">En tránsito (sin confirmar)</option>
                    <option value="confirmado">Confirmados</option>
                    <option value="cancelado">Cancelados</option>
                    <option value="">Todos</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Listado de remitos -->
    <div class="space-y-4">
        @forelse($remitos as $remito)
            <div wire:key="remito-{{ $remito->id }}"
                 class="bg-white rounded-xl shadow-sm border {{ $remito->estado->value === 'remitido' ? 'border-yellow-300' : ($remito->estado->value === 'confirmado' ? 'border-green-300' : 'border-gray-200') }} overflow-hidden">

                <!-- Header del remito -->
                <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-b border-gray-100 bg-gray-50">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-sm font-semibold text-gray-500">#{{ str_pad($remito->id, 6, '0', STR_PAD_LEFT) }}</span>
                        <span class="text-sm text-gray-700">
                            <strong>{{ $remito->sucursalOrigen->nombre }}</strong>
                            <span class="text-gray-400 mx-1">→</span>
                            <strong>{{ $remito->sucursalDestino->nombre }}</strong>
                        </span>
                        <span class="text-xs text-gray-500">
                            {{ $remito->remitido_at->format('d/m/Y H:i') }}@if($remito->user) · {{ $remito->user->name }}@endif
                        </span>
                    </div>
                    <div class="flex items-center gap-3">
                        @php
                            $estadoColor = match($remito->estado->value) {
                                'remitido' => 'bg-yellow-100 text-yellow-800',
                                'confirmado' => 'bg-green-100 text-green-800',
                                'cancelado' => 'bg-red-100 text-red-800',
                                default => 'bg-gray-100 text-gray-800',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $estadoColor }}">
                            {{ $remito->estado->value === 'remitido' ? 'En tránsito' : $remito->estado->label() }}
                        </span>
                        @if($remito->estado->value === 'remitido')
                            @if($puedeRecibir)
                                <button type="button" wire:click="confirmarRecepcion({{ $remito->id }})"
                                        wire:loading.attr="disabled"
                                        wire:confirm="¿Confirmás que {{ $remito->sucursalDestino->nombre }} recibió esta mercadería? El stock se acredita ahí."
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors disabled:opacity-50">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Confirmar recepción
                                </button>
                            @endif
                            @if($puedeCancelar)
                                <button type="button" wire:click="cancelarRemito({{ $remito->id }})"
                                        wire:loading.attr="disabled"
                                        wire:confirm="¿Cancelar este remito? El stock vuelve a {{ $remito->sucursalOrigen->nombre }}."
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition-colors disabled:opacity-50">
                                    Cancelar
                                </button>
                            @endif
                        @elseif($remito->confirmado_at)
                            <span class="text-xs text-gray-500">
                                Recibido {{ $remito->confirmado_at->format('d/m/Y H:i') }}
                                @if($remito->confirmadoPorCaja)
                                    · en caja {{ $remito->confirmadoPorCaja->nombre }}
                                @elseif($remito->confirmadoPorUsuario)
                                    · por {{ $remito->confirmadoPorUsuario->name }}
                                @endif
                            </span>
                        @endif
                        <a href="{{ route('remitos.imprimir', $remito->id) }}" target="_blank"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-gray-600 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            Imprimir
                        </a>
                    </div>
                </div>

                @if($remito->observaciones)
                    <p class="px-6 py-2 text-xs text-gray-600 border-b border-gray-100">{{ $remito->observaciones }}</p>
                @endif

                <!-- Detalle de productos -->
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                            <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                            <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($remito->detalles as $detalle)
                            <tr>
                                <td class="px-6 py-3 font-medium text-gray-900">{{ $detalle->product->nombre }}</td>
                                <td class="px-6 py-3 text-gray-600 font-mono text-xs">{{ $detalle->product->codigo_interno ?? $detalle->product->codigo_barras ?? '-' }}</td>
                                <td class="px-6 py-3 text-center">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-800">
                                        {{ number_format($detalle->cantidad) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    @if($remito->detalles->count() > 1)
                        <tfoot class="bg-gray-50 border-t border-gray-200">
                            <tr>
                                <td colspan="2" class="px-6 py-2 text-xs font-semibold text-gray-600">{{ $remito->detalles->count() }} artículos</td>
                                <td class="px-6 py-2 text-center text-sm font-bold text-gray-900">{{ number_format($remito->detalles->sum('cantidad')) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-6 py-16 text-center">
                <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="text-gray-500 font-medium">No hay remitos {{ $direccion }} en este estado</p>
            </div>
        @endforelse
    </div>

    @if($remitos->hasPages())
        <div class="bg-white rounded-xl px-6 py-4 border border-gray-200">
            {{ $remitos->links() }}
        </div>
    @endif
</div>
