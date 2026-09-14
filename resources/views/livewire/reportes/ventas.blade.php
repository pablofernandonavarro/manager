@php
    use App\Models\PagoVenta;
    use App\Services\ReporteVentasService;
    $pesos = fn ($v) => '$'.number_format((float) $v, 2, ',', '.');
    $maxDia = max(1, collect($porDia)->max('total') ?? 1);
@endphp

<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Reportes de ventas</h1>
            <p class="mt-2 text-sm text-gray-700">Facturación, medios de pago, cajeros y conciliación de tarjetas</p>
        </div>
        <a href="{{ route('reportes.ventas.exportar', array_filter(['desde' => $filtros['desde'], 'hasta' => $filtros['hasta'], 'sucursal' => $sucursal, 'caja' => $caja, 'cajero' => $cajero ?: null])) }}"
           class="mt-4 sm:mt-0 inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Exportar a Excel (CSV)
        </a>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        <div class="flex flex-wrap gap-2">
            @foreach(['hoy' => 'Hoy', 'ayer' => 'Ayer', '7dias' => 'Últimos 7 días', 'mes' => 'Este mes', 'mes_anterior' => 'Mes anterior'] as $clave => $etiqueta)
                <button type="button" wire:click="rapido('{{ $clave }}')" class="px-3 py-1.5 rounded-lg text-sm bg-gray-100 hover:bg-gray-200 text-gray-700">{{ $etiqueta }}</button>
            @endforeach
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                <input type="date" wire:model.live="desde" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                <input type="date" wire:model.live="hasta" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sucursal</label>
                <select wire:model.live="sucursal" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todas</option>
                    @foreach($sucursales as $s)<option value="{{ $s->id }}">{{ $s->nombre }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Caja</label>
                <select wire:model.live="caja" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todas</option>
                    @foreach($cajas as $c)<option value="{{ $c->id }}">{{ $c->nombre }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cajero</label>
                <select wire:model.live="cajero" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todos</option>
                    @foreach($cajeros as $nombre)<option value="{{ $nombre }}">{{ $nombre }}</option>@endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Indicadores --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-xs font-medium text-gray-500 uppercase">Neto vendido</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ $pesos($indicadores['neto']) }}</div>
            <div class="text-xs text-gray-500">Cobrado {{ $pesos($indicadores['total']) }} − devoluciones {{ $pesos($indicadores['devoluciones']) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-xs font-medium text-gray-500 uppercase">Ventas</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($indicadores['cantidad'], 0, ',', '.') }}</div>
            <div class="text-xs text-gray-500">{{ number_format($indicadores['unidades'], 0, ',', '.') }} unidades</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-xs font-medium text-gray-500 uppercase">Ticket promedio</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ $pesos($indicadores['ticket_promedio']) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-xs font-medium text-gray-500 uppercase">Descuentos</div>
            <div class="mt-1 text-2xl font-bold text-amber-600">{{ $pesos($indicadores['descuentos']) }}</div>
            <div class="text-xs text-gray-500">{{ $pesos($indicadores['descuentos_manuales']) }} manuales · bruto {{ $pesos($indicadores['bruto']) }}</div>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Por medio de pago --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-900 mb-3">Por medio de pago</h3>
            <table class="w-full text-sm">
                @forelse($porMedio as $m)
                    <tr class="border-b border-gray-100">
                        <td class="py-2 text-gray-700">{{ ReporteVentasService::nombreMedio($m['medio']) }}</td>
                        <td class="py-2 text-right text-gray-500">{{ $m['cantidad'] }}</td>
                        <td class="py-2 text-right font-medium text-gray-900">{{ $pesos($m['importe']) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-4 text-center text-gray-400">Sin ventas en el período</td></tr>
                @endforelse
            </table>
        </div>

        {{-- Evolución diaria --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-900 mb-3">Por día</h3>
            <div class="space-y-1.5 max-h-72 overflow-y-auto">
                @forelse($porDia as $d)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-20 text-gray-600">{{ \Illuminate\Support\Carbon::parse($d['dia'])->format('d/m') }}</span>
                        <div class="flex-1 bg-gray-100 rounded h-4 overflow-hidden">
                            <div class="bg-blue-500 h-4" style="width: {{ round($d['total'] * 100 / $maxDia) }}%"></div>
                        </div>
                        <span class="w-28 text-right text-gray-900">{{ $pesos($d['total']) }}</span>
                        <span class="w-10 text-right text-gray-400">{{ $d['cantidad'] }}</span>
                    </div>
                @empty
                    <p class="text-center text-gray-400 text-sm py-4">Sin ventas en el período</p>
                @endforelse
            </div>
        </div>

        {{-- Por cajero --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-900 mb-3">Por cajero</h3>
            <table class="w-full text-sm">
                <thead class="text-xs text-gray-500 uppercase">
                    <tr><th class="py-2 text-left">Cajero</th><th class="py-2 text-right">Ventas</th><th class="py-2 text-right">Total</th><th class="py-2 text-right">Promedio</th><th class="py-2 text-right">Desc. manual</th><th class="py-2 text-right">Devol.</th></tr>
                </thead>
                @forelse($porCajero as $c)
                    <tr class="border-t border-gray-100">
                        <td class="py-2 text-gray-900">{{ $c['cajero'] }}</td>
                        <td class="py-2 text-right text-gray-600">{{ $c['cantidad'] }}</td>
                        <td class="py-2 text-right font-medium text-gray-900">{{ $pesos($c['total']) }}</td>
                        <td class="py-2 text-right text-gray-600">{{ $pesos($c['ticket_promedio']) }}</td>
                        <td class="py-2 text-right {{ $c['descuentos_manuales'] > 0 ? 'text-amber-600' : 'text-gray-400' }}">{{ $pesos($c['descuentos_manuales']) }}</td>
                        <td class="py-2 text-right {{ $c['devoluciones'] > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $pesos($c['devoluciones']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-center text-gray-400">Sin ventas en el período</td></tr>
                @endforelse
            </table>
        </div>

        {{-- Por sucursal y caja --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-900 mb-3">Por sucursal y caja</h3>
            <table class="w-full text-sm">
                @forelse($porCaja as $c)
                    <tr class="border-b border-gray-100">
                        <td class="py-2 text-gray-900">{{ $c['sucursal'] }} <span class="text-gray-500">· {{ $c['caja'] }}</span></td>
                        <td class="py-2 text-right text-gray-500">{{ $c['cantidad'] }}</td>
                        <td class="py-2 text-right font-medium text-gray-900">{{ $pesos($c['total']) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-4 text-center text-gray-400">Sin ventas en el período</td></tr>
                @endforelse
            </table>
        </div>

        {{-- Tarjetas --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-900 mb-1">Tarjetas y QR</h3>
            <p class="text-xs text-gray-500 mb-3">Para conciliar con las liquidaciones de las tarjetas.</p>
            <table class="w-full text-sm">
                @forelse($tarjetas as $t)
                    <tr class="border-b border-gray-100">
                        <td class="py-2 text-gray-700">
                            {{ PagoVenta::MEDIOS[$t['medio']] ?? $t['medio'] }}
                            @if($t['tarjeta']) · {{ \App\Models\PromocionBancaria::TARJETAS[$t['tarjeta']] ?? ucfirst($t['tarjeta']) }} @endif
                            @if($t['banco']) <span class="text-gray-500">· {{ $t['banco'] }}</span> @endif
                            @if(($t['cuotas'] ?? 1) > 1) <span class="text-gray-500">· {{ $t['cuotas'] }} cuotas</span> @endif
                        </td>
                        <td class="py-2 text-right text-gray-500">{{ $t['cantidad'] }}</td>
                        <td class="py-2 text-right font-medium text-gray-900">{{ $pesos($t['importe']) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-4 text-center text-gray-400">Sin cobros con tarjeta o QR</td></tr>
                @endforelse
            </table>
        </div>

        {{-- Promociones --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-900 mb-3">Promociones bancarias</h3>
            <table class="w-full text-sm">
                @forelse($promociones as $p)
                    <tr class="border-b border-gray-100">
                        <td class="py-2 text-gray-700">{{ $p['promocion'] }} <span class="text-gray-400">×{{ $p['usos'] }}</span></td>
                        <td class="py-2 text-right text-amber-600">−{{ $pesos($p['descuento']) }}</td>
                        <td class="py-2 text-right text-gray-900">{{ $pesos($p['cobrado']) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-4 text-center text-gray-400">No se usaron promociones</td></tr>
                @endforelse
            </table>
        </div>
    </div>
</div>
