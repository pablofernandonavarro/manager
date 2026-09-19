@php
    $pesos = fn ($v) => '$ '.number_format((float) $v, 0, ',', '.');
    // Variación contra el período anterior: null si no hay base para comparar.
    $variacion = fn ($actual, $anterior) => $anterior > 0 ? round(($actual - $anterior) / $anterior * 100) : null;
    $nombreSucursal = $sucursales->firstWhere('id', $sucursalId)?->nombre;
    $plural = fn ($n, string $uno, string $varios) => number_format($n, 0, ',', '.').' '.($n == 1 ? $uno : $varios);
@endphp

<div class="space-y-6" wire:poll.60s>
    {{-- Encabezado --}}
    <div class="sm:flex sm:items-end sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Hola, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h1>
            <p class="mt-1 text-sm text-gray-600 first-letter:uppercase">
                {{ $hoy->locale('es')->translatedFormat('l j \d\e F') }} · {{ $nombreSucursal ?? 'Todas las sucursales' }}
                <span class="text-gray-400">· se actualiza cada minuto</span>
            </p>
        </div>
        <div class="mt-4 sm:mt-0">
            <select wire:model.live="sucursalId" class="block w-full sm:w-56 px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                <option value="">Todas las sucursales</option>
                @foreach($sucursales as $s)
                    <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Alertas: lo que necesita atención ahora --}}
    @php
        $alertas = collect([
            $cajas && $cajas['conteo']['critico'] > 0 ? ['rojo', $plural($cajas['conteo']['critico'], 'caja', 'cajas').' con problemas', '/puntos-de-venta'] : null,
            $facturacion && $facturacion['rechazados'] > 0 ? ['rojo', $plural($facturacion['rechazados'], 'factura rechazada', 'facturas rechazadas').' por AFIP', '/facturacion/comprobantes'] : null,
            $cajas && $cajas['conteo']['alerta'] > 0 ? ['ambar', $plural($cajas['conteo']['alerta'], 'caja', 'cajas').' en alerta', '/puntos-de-venta'] : null,
            $stockCritico && $stockCritico['cantidad'] > 0 ? ['ambar', $plural($stockCritico['cantidad'], 'artículo', 'artículos').' con stock crítico', '/sucursales/stock'] : null,
            $remitos && $remitos['cantidad'] > 0 ? ['azul', $plural($remitos['cantidad'], 'remito', 'remitos').' en tránsito', '/sucursales/remitos'] : null,
            $facturacion && $facturacion['pendientes'] > 0 ? ['azul', $plural($facturacion['pendientes'], 'factura', 'facturas').' esperando CAE', '/facturacion/comprobantes'] : null,
        ])->filter();
        $estilos = ['rojo' => 'bg-red-50 text-red-800 border-red-200', 'ambar' => 'bg-amber-50 text-amber-800 border-amber-200', 'azul' => 'bg-blue-50 text-blue-800 border-blue-200'];
    @endphp
    @if($alertas->isNotEmpty())
        <div class="flex flex-wrap gap-2">
            @foreach($alertas as [$color, $texto, $url])
                <a href="{{ $url }}" wire:navigate class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-sm font-medium hover:shadow-sm {{ $estilos[$color] }}">
                    <span class="w-2 h-2 rounded-full {{ ['rojo' => 'bg-red-500', 'ambar' => 'bg-amber-500', 'azul' => 'bg-blue-500'][$color] }}"></span>
                    {{ $texto }}
                </a>
            @endforeach
        </div>
    @endif

    @if($verVentas)
        {{-- Indicadores --}}
        @php
            $kpis = [
                ['Ventas de hoy', $pesos($ventas['hoy']['neto']), $variacion($ventas['hoy']['neto'], $ventas['ayer']['neto']), 'vs. ayer ('.$pesos($ventas['ayer']['neto']).')'],
                ['Tickets de hoy', number_format($ventas['hoy']['cantidad'], 0, ',', '.'), $variacion($ventas['hoy']['cantidad'], $ventas['ayer']['cantidad']), $plural($ventas['hoy']['unidades'], 'unidad', 'unidades')],
                ['Ticket promedio', $pesos($ventas['hoy']['ticket_promedio']), $variacion($ventas['hoy']['ticket_promedio'], $ventas['ayer']['ticket_promedio']), 'vs. ayer'],
                ['Ventas del mes', $pesos($ventas['mes']['neto']), $variacion($ventas['mes']['neto'], $ventas['mes_anterior']['neto']), 'vs. mismos días del mes anterior'],
            ];
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach($kpis as [$titulo, $valor, $cambio, $detalle])
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <p class="text-sm font-medium text-gray-500">{{ $titulo }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900 tabular-nums">{{ $valor }}</p>
                    <p class="mt-2 text-xs text-gray-500 flex items-center gap-1.5">
                        @if($cambio !== null)
                            <span class="font-semibold {{ $cambio >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $cambio >= 0 ? '▲' : '▼' }} {{ abs($cambio) }}%</span>
                        @endif
                        <span>{{ $detalle }}</span>
                    </p>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            {{-- Evolución --}}
            @php $maximo = max(1, collect($evolucion)->max('total')); @endphp
            <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-baseline justify-between mb-4">
                    <h2 class="text-base font-semibold text-gray-900">Ventas de los últimos 14 días</h2>
                    <a href="/reportes/ventas" wire:navigate class="text-sm text-blue-600 hover:text-blue-800">Ver reporte</a>
                </div>
                <div class="flex items-end gap-1.5 h-48">
                    @foreach($evolucion as $d)
                        <div class="flex-1 h-full flex flex-col justify-end items-center group relative" wire:key="dia-{{ $d['dia'] }}">
                            <div class="absolute bottom-full mb-1 hidden group-hover:block whitespace-nowrap rounded bg-gray-900 px-2 py-1 text-xs text-white z-10">
                                {{ $pesos($d['total']) }} · {{ $plural($d['cantidad'], 'ticket', 'tickets') }}
                            </div>
                            <div class="w-full rounded-t {{ $loop->last ? 'bg-blue-600' : 'bg-blue-300 group-hover:bg-blue-400' }}"
                                 style="height: {{ $d['total'] > 0 ? max(2, round($d['total'] / $maximo * 100)) : 0 }}%"></div>
                        </div>
                    @endforeach
                </div>
                <div class="flex gap-1.5 mt-2">
                    @foreach($evolucion as $d)
                        <div class="flex-1 text-center text-[10px] leading-tight text-gray-500">{{ $d['etiqueta'] }}</div>
                    @endforeach
                </div>
            </div>

            {{-- Medios de pago de hoy --}}
            @php $totalMedios = max(0.01, collect($distribucion['por_medio'])->sum('importe')); @endphp
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Cobrado hoy por medio</h2>
                <div class="space-y-3">
                    @forelse($distribucion['por_medio'] as $m)
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-700">{{ $medios[$m['medio']] ?? $m['medio'] }}</span>
                                <span class="font-medium text-gray-900 tabular-nums">{{ $pesos($m['importe']) }}</span>
                            </div>
                            <div class="mt-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full bg-indigo-500 rounded-full" style="width: {{ round($m['importe'] / $totalMedios * 100) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">Todavía no hay cobros hoy.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            {{-- Por caja --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h2 class="text-base font-semibold text-gray-900 mb-3">Ventas de hoy por caja</h2>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @forelse($distribucion['por_caja'] as $c)
                            <tr>
                                <td class="py-2 text-gray-900">{{ $c['caja'] }} <span class="text-gray-400">· {{ $c['sucursal'] }}</span></td>
                                <td class="py-2 text-right text-gray-500 tabular-nums">{{ $plural($c['cantidad'], 'ticket', 'tickets') }}</td>
                                <td class="py-2 text-right font-medium text-gray-900 tabular-nums">{{ $pesos($c['total']) }}</td>
                            </tr>
                        @empty
                            <tr><td class="py-6 text-center text-gray-400">Todavía no hay ventas hoy.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Más vendidos --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h2 class="text-base font-semibold text-gray-900 mb-3">Más vendidos del mes</h2>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @forelse($masVendidos as $p)
                            <tr>
                                <td class="py-2 w-6 text-gray-400 tabular-nums">{{ $loop->iteration }}</td>
                                <td class="py-2">
                                    <div class="text-gray-900">{{ $p['nombre'] }}</div>
                                    <div class="text-xs font-mono text-gray-400">{{ $p['codigo'] }}</div>
                                </td>
                                <td class="py-2 text-right text-gray-500 tabular-nums whitespace-nowrap">{{ $p['unidades'] }} u.</td>
                                <td class="py-2 text-right font-medium text-gray-900 tabular-nums whitespace-nowrap">{{ $pesos($p['importe']) }}</td>
                            </tr>
                        @empty
                            <tr><td class="py-6 text-center text-gray-400">Sin ventas este mes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
        {{-- Cajas --}}
        @if($cajas)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-baseline justify-between mb-3">
                    <h2 class="text-base font-semibold text-gray-900">Cajas</h2>
                    <a href="/puntos-de-venta" wire:navigate class="text-sm text-blue-600 hover:text-blue-800">Ver todas</a>
                </div>
                <div class="grid grid-cols-4 gap-2 text-center mb-4">
                    <div class="rounded-lg bg-green-50 py-2"><p class="text-xl font-bold text-green-700">{{ $cajas['conteo']['ok'] }}</p><p class="text-xs text-green-700">Bien</p></div>
                    <div class="rounded-lg bg-blue-50 py-2"><p class="text-xl font-bold text-blue-700">{{ $cajas['conteo']['en_proceso'] }}</p><p class="text-xs text-blue-700">En proceso</p></div>
                    <div class="rounded-lg bg-amber-50 py-2"><p class="text-xl font-bold text-amber-700">{{ $cajas['conteo']['alerta'] }}</p><p class="text-xs text-amber-700">Alerta</p></div>
                    <div class="rounded-lg bg-red-50 py-2"><p class="text-xl font-bold text-red-700">{{ $cajas['conteo']['critico'] }}</p><p class="text-xs text-red-700">Problemas</p></div>
                </div>
                @foreach(array_slice($cajas['problemas'], 0, 4) as $pr)
                    <p class="text-sm {{ $pr['nivel'] === 'critico' ? 'text-red-700' : 'text-amber-700' }}">
                        <strong>{{ $pr['caja'] }}</strong> <span class="text-gray-400">({{ $pr['sucursal'] }})</span>: {{ $pr['texto'] }}
                    </p>
                @endforeach
                <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-gray-500">Abiertas ahora</h3>
                <div class="mt-1 divide-y divide-gray-100">
                    @forelse($cajas['abiertas'] as $a)
                        <div class="py-1.5 flex justify-between text-sm">
                            <span class="text-gray-900">{{ $a['caja'] }} <span class="text-gray-400">· {{ $a['cajero'] ?? 'sin cajero' }}</span></span>
                            <span class="text-gray-500">desde {{ $a['desde']?->timezone(config('app.display_timezone'))->format('H:i') }}</span>
                        </div>
                    @empty
                        <p class="py-1.5 text-sm text-gray-400">Ninguna caja abierta.</p>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- Stock crítico --}}
        @if($stockCritico)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-baseline justify-between mb-3">
                    <h2 class="text-base font-semibold text-gray-900">Stock crítico <span class="text-gray-400 font-normal">({{ $stockCritico['cantidad'] }})</span></h2>
                    @can('remitos.crear')
                        <a href="/sucursales/remitos/nuevo" wire:navigate class="text-sm text-blue-600 hover:text-blue-800">Reponer con remito</a>
                    @endcan
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse($stockCritico['items'] as $s)
                        <div class="py-2 flex justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <p class="text-gray-900 truncate">{{ $s['nombre'] }}</p>
                                <p class="text-xs text-gray-400"><span class="font-mono">{{ $s['codigo'] }}</span> · {{ $s['sucursal'] }}</p>
                            </div>
                            <span class="shrink-0 font-semibold tabular-nums {{ $s['cantidad'] === 0 ? 'text-red-600' : 'text-amber-600' }}">{{ $s['cantidad'] }} / {{ $s['critico'] }}</span>
                        </div>
                    @empty
                        <p class="py-2 text-sm text-gray-400">Todo el stock está por encima del mínimo.</p>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- Remitos, facturación y cuentas corrientes --}}
        @if($remitos || $facturacion || $cuentas)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 space-y-5">
                @if($remitos)
                    <div>
                        <div class="flex items-baseline justify-between mb-2">
                            <h2 class="text-base font-semibold text-gray-900">Remitos en tránsito <span class="text-gray-400 font-normal">({{ $remitos['cantidad'] }})</span></h2>
                            <a href="/sucursales/remitos" wire:navigate class="text-sm text-blue-600 hover:text-blue-800">Ver</a>
                        </div>
                        @forelse($remitos['ultimos'] as $r)
                            <p class="text-sm text-gray-700 py-0.5">
                                <span class="font-mono text-xs text-gray-400">#{{ str_pad($r['id'], 6, '0', STR_PAD_LEFT) }}</span>
                                {{ $r['origen'] }} → {{ $r['destino'] }}
                                <span class="text-gray-400">· {{ $r['remitido_at']?->locale('es')->diffForHumans() }}</span>
                            </p>
                        @empty
                            <p class="text-sm text-gray-400">No hay mercadería en camino.</p>
                        @endforelse
                    </div>
                @endif

                @if($facturacion)
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 mb-2">Facturación electrónica</h2>
                        <div class="grid grid-cols-2 gap-2 text-center">
                            <a href="/facturacion/comprobantes" wire:navigate class="rounded-lg bg-blue-50 py-2 hover:bg-blue-100"><p class="text-xl font-bold text-blue-700">{{ $facturacion['pendientes'] }}</p><p class="text-xs text-blue-700">Esperando CAE</p></a>
                            <a href="/facturacion/comprobantes" wire:navigate class="rounded-lg {{ $facturacion['rechazados'] ? 'bg-red-50 hover:bg-red-100' : 'bg-gray-50' }} py-2"><p class="text-xl font-bold {{ $facturacion['rechazados'] ? 'text-red-700' : 'text-gray-500' }}">{{ $facturacion['rechazados'] }}</p><p class="text-xs {{ $facturacion['rechazados'] ? 'text-red-700' : 'text-gray-500' }}">Rechazadas</p></a>
                        </div>
                    </div>
                @endif

                @if($cuentas)
                    <div>
                        <div class="flex items-baseline justify-between mb-1">
                            <h2 class="text-base font-semibold text-gray-900">Cuentas corrientes</h2>
                            <a href="/clientes" wire:navigate class="text-sm text-blue-600 hover:text-blue-800">Clientes</a>
                        </div>
                        <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ $pesos($cuentas['total']) }}</p>
                        <p class="text-xs text-gray-500">a cobrar de {{ $plural($cuentas['clientes'], 'cliente', 'clientes') }}</p>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @if(! $verVentas && ! $cajas && ! $stockCritico && ! $remitos && ! $facturacion && ! $cuentas)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-sm text-gray-500">
            Tu usuario no tiene acceso a indicadores. Usá el menú para ir a tus secciones.
        </div>
    @endif
</div>
