<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Remitos</h1>
            <p class="mt-2 text-sm text-gray-700">Confirmá la recepción de mercadería enviada desde Central</p>
        </div>
        @if($pendientes > 0)
            <span class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-100 text-yellow-800 text-sm font-semibold rounded-lg">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ $pendientes }} pendiente{{ $pendientes > 1 ? 's' : '' }} de confirmar
            </span>
        @endif
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-green-50 p-4 border-l-4 border-green-400">
            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
        </div>
    @endif

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Sucursal</label>
                <select wire:model.live="sucursalSeleccionada"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    @foreach($sucursales as $sucursal)
                        <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                <select wire:model.live="filtroEstado"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="remitido">Pendientes de confirmación</option>
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
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-semibold text-gray-500">#{{ $remito->id }}</span>
                        <span class="text-sm text-gray-700">
                            Desde <strong>{{ $remito->sucursalOrigen->nombre }}</strong>
                        </span>
                        <span class="text-xs text-gray-500">{{ $remito->remitido_at->format('d/m/Y H:i') }}</span>
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
                            {{ $remito->estado->label() }}
                        </span>
                        @if($remito->estado->value === 'remitido')
                            <button type="button" wire:click="confirmarRecepcion({{ $remito->id }})"
                                    wire:confirm="¿Confirmás la recepción de este remito? El stock será acreditado a tu sucursal."
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Confirmar recepción
                            </button>
                            <button wire:click="cancelarRemito({{ $remito->id }})"
                                    wire:confirm="¿Cancelar este remito? El stock será devuelto a Central."
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition-colors">
                                Cancelar
                            </button>
                        @elseif($remito->confirmado_at)
                            <span class="text-xs text-gray-500">Confirmado {{ $remito->confirmado_at->format('d/m/Y H:i') }}</span>
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

                <!-- Detalle de productos -->
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                            <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                            <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase">Cantidad enviada</th>
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
                </table>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-6 py-16 text-center">
                <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="text-gray-500 font-medium">No hay remitos en este estado</p>
            </div>
        @endforelse
    </div>

    @if($remitos->hasPages())
        <div class="bg-white rounded-xl px-6 py-4 border border-gray-200">
            {{ $remitos->links() }}
        </div>
    @endif
</div>
