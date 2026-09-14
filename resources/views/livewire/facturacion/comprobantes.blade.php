@php
    use App\Models\Comprobante;
    $pesos = fn ($v) => '$'.number_format((float) $v, 2, ',', '.');
    $colores = ['autorizado' => 'bg-green-100 text-green-800', 'pendiente' => 'bg-yellow-100 text-yellow-800', 'rechazado' => 'bg-red-100 text-red-800'];
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Comprobantes electrónicos</h1>
            <p class="mt-2 text-sm text-gray-700">Facturas y notas de crédito de las cajas con su CAE de AFIP</p>
        </div>
        <div class="flex gap-2 text-sm">
            @if($pendientes > 0)
                <span class="px-3 py-1.5 rounded-lg bg-yellow-50 text-yellow-800 border border-yellow-200">{{ $pendientes }} pendiente(s) de CAE</span>
            @endif
            @if($rechazados > 0)
                <span class="px-3 py-1.5 rounded-lg bg-red-50 text-red-800 border border-red-200">{{ $rechazados }} rechazado(s)</span>
            @endif
        </div>
    </div>

    @if($mensaje)
        <div class="rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-800">{{ $mensaje }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                <select wire:model.live="estado" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todos</option>
                    <option value="pendiente">Pendientes</option>
                    <option value="autorizado">Autorizados</option>
                    <option value="rechazado">Rechazados</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Sucursal</label>
                <select wire:model.live="sucursal" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todas</option>
                    @foreach($sucursales as $s)
                        <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Desde</label>
                <input type="date" wire:model.live="desde" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Hasta</label>
                <input type="date" wire:model.live="hasta" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Comprobante</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Caja / origen</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Receptor</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($comprobantes as $c)
                        <tr wire:key="comprobante-{{ $c->id }}" class="hover:bg-gray-50 align-top">
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">{{ $c->nombreTipo() }}</div>
                                <div class="text-xs font-mono text-gray-600">{{ $c->numeroFormateado() ?? 'PV '.str_pad($c->afip_punto_venta, 5, '0', STR_PAD_LEFT).' · sin número' }}</div>
                                @if($c->entorno === 'homologacion')
                                    <span class="text-xs text-orange-600">Homologación</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $c->fecha->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">
                                <div class="text-sm text-gray-900">{{ $c->puntoDeVenta?->nombre ?? '—' }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ $c->sucursal?->nombre }}
                                    · {{ $c->venta ? 'Venta '.$c->venta->numero_venta : ($c->devolucion ? 'Devolución '.$c->devolucion->numero : '') }}
                                </div>
                                @if($c->asociado)
                                    <div class="text-xs text-gray-500">Asociada a {{ $c->asociado->nombreTipo() }} {{ $c->asociado->numeroFormateado() ?? '(sin número)' }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm text-gray-900">{{ $c->receptor_nombre ?? Comprobante::CONDICIONES_IVA[$c->receptor_condicion_iva] ?? '' }}</div>
                                @if($c->receptor_doc_tipo !== 99)
                                    <div class="text-xs text-gray-500">{{ Comprobante::DOC_TIPOS[$c->receptor_doc_tipo] ?? '' }} {{ $c->receptor_doc_nro }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="text-sm font-semibold text-gray-900">{{ $pesos($c->importe_total) }}</div>
                                @if($c->letra() !== 'C')
                                    <div class="text-xs text-gray-500">IVA {{ $pesos($c->importe_iva) }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-xs font-medium {{ $colores[$c->estado] ?? '' }}">{{ ucfirst($c->estado) }}</span>
                                @if($c->cae)
                                    <div class="mt-1 text-xs font-mono text-gray-600">CAE {{ $c->cae }}</div>
                                    <div class="text-xs text-gray-500">Vto. {{ $c->cae_vencimiento?->format('d/m/Y') }}</div>
                                @endif
                                @if($c->error)
                                    <div class="mt-1 max-w-xs text-xs text-red-700">{{ $c->error }}</div>
                                @endif
                                @if($c->intentos > 0 && ! $c->estaAutorizado())
                                    <div class="text-xs text-gray-400">{{ $c->intentos }} intento(s)</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('facturacion.configurar')
                                    @unless($c->estaAutorizado())
                                        <button type="button" wire:click="reintentar({{ $c->id }})"
                                                class="px-2.5 py-1 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg">
                                            Reintentar
                                        </button>
                                    @endunless
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">No hay comprobantes con estos filtros.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-200">{{ $comprobantes->links() }}</div>
    </div>
</div>
