@php
    use App\Models\Comprobante;
    use App\Models\MovimientoCuentaCorriente;
    use App\Models\PagoVenta;
    $pesos = fn ($v) => '$'.number_format((float) $v, 2, ',', '.');
    $zona = config('app.display_timezone');
@endphp

<div class="space-y-6">
    <div class="sm:flex sm:items-end sm:justify-between">
        <div>
            <a href="{{ route('clientes.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Clientes</a>
            <h1 class="mt-1 text-3xl font-bold text-gray-900">{{ $cliente->nombre }}</h1>
            <p class="mt-1 text-sm text-gray-600">
                {{ $cliente->documentoFormateado() ?? 'Sin documento' }} · {{ Comprobante::CONDICIONES_IVA[$cliente->condicion_iva] ?? '' }}
                @if($cliente->telefono) · {{ $cliente->telefono }} @endif
            </p>
        </div>
        <div class="mt-4 sm:mt-0 text-right">
            <p class="text-sm text-gray-500">Saldo</p>
            <p class="text-3xl font-bold {{ $saldo > 0 ? 'text-red-700' : 'text-gray-900' }}">{{ $pesos($saldo) }}</p>
            <p class="text-xs text-gray-500">
                @if(! $cliente->cuenta_corriente) Cuenta corriente deshabilitada
                @elseif($cliente->limite_credito !== null) Límite {{ $pesos($cliente->limite_credito) }} · disponible {{ $pesos(max(0, (float) $cliente->limite_credito - $saldo)) }}
                @else Sin límite de crédito
                @endif
            </p>
        </div>
    </div>

    @can('clientes.cuenta_corriente')
        <form wire:submit="registrar" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 space-y-3">
            <p class="text-sm font-semibold text-gray-900">Registrar pago o ajuste</p>
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Tipo</label>
                    <select wire:model.live="tipo" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="pago">Pago</option>
                        <option value="ajuste">Ajuste (+ suma deuda / − resta)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Importe</label>
                    <input type="number" step="0.01" wire:model="importe" class="w-36 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                @if($tipo === 'pago')
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Medio</label>
                        <select wire:model="medio" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            @foreach(\App\Services\CuentaCorrienteService::MEDIOS_DE_COBRO as $m)
                                <option value="{{ $m }}">{{ PagoVenta::MEDIOS[$m] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="flex-1 min-w-48">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Motivo / referencia</label>
                    <input type="text" wire:model="descripcion" maxlength="200" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700">Registrar</button>
            </div>
            @if($error)<p class="text-sm text-red-600">{{ $error }}</p>@endif
            @if($mensaje)<p class="text-sm text-green-700">{{ $mensaje }}</p>@endif
        </form>
    @endcan

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Movimiento</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Origen</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debe</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Haber</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Saldo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($movimientos as $m)
                    <tr wire:key="mov-{{ $m->id }}">
                        <td class="px-6 py-3 text-sm text-gray-700 whitespace-nowrap">{{ $m->fecha->timezone($zona)->format('d/m/Y H:i') }}</td>
                        <td class="px-6 py-3">
                            <div class="text-sm text-gray-900">{{ MovimientoCuentaCorriente::TIPOS[$m->tipo] }}@if($m->medio) · {{ PagoVenta::MEDIOS[$m->medio] ?? $m->medio }}@endif</div>
                            <div class="text-xs text-gray-500">{{ $m->descripcion }}</div>
                        </td>
                        <td class="px-6 py-3 text-xs text-gray-500">{{ $m->puntoDeVenta?->nombre ?? $m->user?->name ?? '—' }}</td>
                        <td class="px-6 py-3 text-right text-sm text-red-700">{{ (float) $m->importe > 0 ? $pesos($m->importe) : '' }}</td>
                        <td class="px-6 py-3 text-right text-sm text-green-700">{{ (float) $m->importe < 0 ? $pesos(-$m->importe) : '' }}</td>
                        <td class="px-6 py-3 text-right text-sm font-semibold text-gray-900">{{ $pesos($saldos[$m->id]) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-gray-400">Sin movimientos.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-3 border-t border-gray-200">{{ $movimientos->links() }}</div>
    </div>
</div>
