@php
    use App\Models\MovimientoCaja;
    use App\Models\PagoVenta;
    $pesos = fn ($v) => '$'.number_format((float) $v, 2, ',', '.');
@endphp

<div class="space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Cierres de caja</h1>
        <p class="mt-2 text-sm text-gray-700">Turnos de todas las cajas: los abiertos en vivo y los cerrados con su cierre Z y arqueo</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
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
                <label class="block text-sm font-medium text-gray-700 mb-2">Caja</label>
                <select wire:model.live="caja" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todas</option>
                    @foreach($cajas as $c)
                        <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                <select wire:model.live="estado" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todos</option>
                    <option value="abierto">Abiertos</option>
                    <option value="cerrado">Cerrados</option>
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
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Caja</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turno</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cajero</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Apertura / cierre</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ventas</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Diferencia</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($turnos as $t)
                        <tr wire:key="turno-{{ $t->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">{{ $t->puntoDeVenta?->nombre ?? '—' }}</div>
                                <div class="text-xs text-gray-500">{{ $t->sucursal?->nombre }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                Z #{{ $t->numero }}
                                @if($t->estaCerrado())
                                    <span class="ml-1 px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">Cerrado</span>
                                @else
                                    <span class="ml-1 px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Abierto</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $t->cajero }}</td>
                            <td class="px-4 py-3 text-xs text-gray-600">
                                {{ $t->abierto_at->timezone(config('app.display_timezone', 'America/Argentina/Buenos_Aires'))->format('d/m/Y H:i') }}
                                <div>{{ $t->cerrado_at ? '→ '.$t->cerrado_at->timezone(config('app.display_timezone', 'America/Argentina/Buenos_Aires'))->format('d/m/Y H:i') : 'en curso' }}</div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="text-sm font-semibold text-gray-900">{{ $pesos($t->total_ventas) }}</div>
                                <div class="text-xs text-gray-500">{{ $t->cantidad_ventas }} venta(s)</div>
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-semibold">
                                @if($t->diferencia === null)
                                    <span class="text-gray-400">—</span>
                                @elseif((float) $t->diferencia == 0)
                                    <span class="text-green-700">Sin diferencia</span>
                                @else
                                    <span class="{{ $t->diferencia < 0 ? 'text-red-600' : 'text-amber-600' }}">
                                        {{ $t->diferencia < 0 ? 'Falta' : 'Sobra' }} {{ $pesos(abs((float) $t->diferencia)) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" wire:click="verDetalle({{ $t->id }})" class="text-sm text-blue-600 hover:text-blue-800">Ver detalle</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-12 text-center text-sm text-gray-400">No hay turnos con estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($turnos->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">{{ $turnos->links() }}</div>
        @endif
    </div>

    @if($detalle)
        @php $r = $detalle->resumen ?? []; @endphp
        <div style="position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);display:flex;align-items:flex-start;justify-content:center;overflow-y:auto;padding:2rem 1rem"
             wire:click.self="cerrarDetalle">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl" @click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">
                            {{ $detalle->estaCerrado() ? 'Cierre Z' : 'Turno abierto' }} #{{ $detalle->numero }} · {{ $detalle->puntoDeVenta?->nombre }}
                        </h3>
                        <p class="text-sm text-gray-500">{{ $detalle->sucursal?->nombre }} · {{ $detalle->cajero }}</p>
                    </div>
                    <button type="button" wire:click="cerrarDetalle" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="px-6 py-5 space-y-5 text-sm">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="text-xs text-gray-500">Ventas</div>
                            <div class="text-lg font-bold text-gray-900">{{ $detalle->cantidad_ventas }}</div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="text-xs text-gray-500">Total cobrado</div>
                            <div class="text-lg font-bold text-gray-900">{{ $pesos($detalle->total_ventas) }}</div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="text-xs text-gray-500">Descuentos</div>
                            <div class="text-lg font-bold text-gray-900">{{ $pesos($r['ventas']['descuentos'] ?? 0) }}</div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="text-xs text-gray-500">Ticket promedio</div>
                            <div class="text-lg font-bold text-gray-900">{{ $pesos($r['ventas']['ticket_promedio'] ?? 0) }}</div>
                        </div>
                    </div>

                    <div>
                        <p class="font-semibold text-gray-900 mb-2">Por medio de pago</p>
                        <table class="w-full">
                            @forelse($r['por_medio'] ?? [] as $medio => $importe)
                                <tr class="border-b border-gray-100">
                                    <td class="py-1.5 text-gray-700">{{ PagoVenta::MEDIOS[$medio] ?? $medio }}</td>
                                    <td class="py-1.5 text-right font-medium text-gray-900">{{ $pesos($importe) }}</td>
                                </tr>
                            @empty
                                <tr><td class="py-1.5 text-gray-400">Sin ventas</td></tr>
                            @endforelse
                        </table>
                    </div>

                    @if(!empty($r['promociones']))
                        <div>
                            <p class="font-semibold text-gray-900 mb-2">Promociones bancarias</p>
                            <table class="w-full">
                                @foreach($r['promociones'] as $promo)
                                    <tr class="border-b border-gray-100">
                                        <td class="py-1.5 text-gray-700">{{ $promo['nombre'] }} <span class="text-xs text-gray-400">×{{ $promo['cantidad'] }}</span></td>
                                        <td class="py-1.5 text-right text-gray-900">−{{ $pesos($promo['descuento']) }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    @endif

                    @if(($r['ventas']['descuentos_manuales'] ?? 0) > 0)
                        <p class="text-sm text-gray-700">Descuentos manuales autorizados: <strong>{{ $pesos($r['ventas']['descuentos_manuales']) }}</strong></p>
                    @endif

                    @if(!empty($r['devoluciones']['cantidad']))
                        <div>
                            <p class="font-semibold text-gray-900 mb-2">Devoluciones y anulaciones</p>
                            <table class="w-full">
                                @foreach($r['devoluciones']['detalle'] as $dev)
                                    <tr class="border-b border-gray-100">
                                        <td class="py-1.5 text-gray-700">
                                            {{ $dev['numero'] }} · {{ $dev['tipo'] === 'anulacion' ? 'Anulación' : 'Devolución' }} de {{ $dev['venta'] }}
                                            <div class="text-xs text-gray-500">{{ $dev['motivo'] }} · autorizó {{ $dev['autorizado_por'] }} · {{ $dev['reintegro'] === 'efectivo' ? 'en efectivo' : 'al medio original' }}</div>
                                        </td>
                                        <td class="py-1.5 text-right text-red-600">−{{ $pesos($dev['total']) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="font-semibold">
                                    <td class="py-1.5">Neto vendido</td>
                                    <td class="py-1.5 text-right">{{ $pesos($r['ventas']['neto'] ?? $detalle->total_ventas) }}</td>
                                </tr>
                            </table>
                        </div>
                    @endif

                    <div>
                        <p class="font-semibold text-gray-900 mb-2">Efectivo</p>
                        <table class="w-full">
                            <tr class="border-b border-gray-100"><td class="py-1.5 text-gray-700">Fondo inicial</td><td class="py-1.5 text-right">{{ $pesos($detalle->fondo_inicial) }}</td></tr>
                            <tr class="border-b border-gray-100"><td class="py-1.5 text-gray-700">Ventas en efectivo</td><td class="py-1.5 text-right">{{ $pesos($r['efectivo']['ventas'] ?? 0) }}</td></tr>
                            <tr class="border-b border-gray-100"><td class="py-1.5 text-gray-700">Ingresos</td><td class="py-1.5 text-right">{{ $pesos($r['efectivo']['ingresos'] ?? 0) }}</td></tr>
                            <tr class="border-b border-gray-100"><td class="py-1.5 text-gray-700">Retiros y gastos</td><td class="py-1.5 text-right">−{{ $pesos(($r['efectivo']['retiros'] ?? 0) + ($r['efectivo']['gastos'] ?? 0)) }}</td></tr>
                            @if(($r['efectivo']['devoluciones'] ?? 0) > 0)
                                <tr class="border-b border-gray-100"><td class="py-1.5 text-gray-700">Devoluciones en efectivo</td><td class="py-1.5 text-right">−{{ $pesos($r['efectivo']['devoluciones']) }}</td></tr>
                            @endif
                            <tr class="border-b border-gray-100 font-semibold"><td class="py-1.5">Esperado en caja</td><td class="py-1.5 text-right">{{ $pesos($detalle->efectivo_esperado ?? ($r['efectivo']['esperado'] ?? 0)) }}</td></tr>
                            @if($detalle->efectivo_contado !== null)
                                <tr class="border-b border-gray-100"><td class="py-1.5 text-gray-700">Contado</td><td class="py-1.5 text-right">{{ $pesos($detalle->efectivo_contado) }}</td></tr>
                                <tr class="font-semibold {{ (float) $detalle->diferencia < 0 ? 'text-red-600' : ((float) $detalle->diferencia > 0 ? 'text-amber-600' : 'text-green-700') }}">
                                    <td class="py-1.5">Diferencia</td><td class="py-1.5 text-right">{{ $pesos($detalle->diferencia) }}</td>
                                </tr>
                            @endif
                        </table>
                    </div>

                    @if($detalle->movimientos->isNotEmpty())
                        <div>
                            <p class="font-semibold text-gray-900 mb-2">Movimientos de caja</p>
                            <table class="w-full">
                                @foreach($detalle->movimientos as $m)
                                    <tr class="border-b border-gray-100">
                                        <td class="py-1.5 text-gray-700">{{ MovimientoCaja::TIPOS[$m->tipo] ?? $m->tipo }} · {{ $m->motivo }}</td>
                                        <td class="py-1.5 text-right {{ $m->tipo === 'ingreso' ? 'text-gray-900' : 'text-red-600' }}">{{ $m->tipo === 'ingreso' ? '' : '−' }}{{ $pesos($m->monto) }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    @endif

                    @if(!empty($r['numeracion']['desde']))
                        <p class="text-xs text-gray-500">Ventas {{ $r['numeracion']['desde'] }} a {{ $r['numeracion']['hasta'] }}</p>
                    @endif
                    @if($detalle->observaciones)
                        <p class="text-sm text-gray-700 bg-gray-50 rounded p-3">{{ $detalle->observaciones }}</p>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
