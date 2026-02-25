<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remito #{{ str_pad($remito->id, 6, '0', STR_PAD_LEFT) }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .no-print { display: none !important; }
            @page { margin: 1.5cm; }
        }
    </style>
</head>
<body class="bg-white text-gray-900 font-sans" onload="window.print()">

    <!-- Botón imprimir (solo pantalla) -->
    <div class="no-print fixed top-4 right-4 flex gap-2">
        <button onclick="window.print()"
                class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg shadow hover:bg-blue-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Imprimir
        </button>
        <a href="{{ route('sucursales.remitos') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg shadow hover:bg-gray-50">
            Volver
        </a>
    </div>

    <!-- Documento -->
    <div class="max-w-3xl mx-auto p-10">

        <!-- Encabezado -->
        <div class="flex items-start justify-between pb-6 border-b-2 border-gray-800">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 uppercase tracking-wide">Remito de Transferencia</h1>
                <p class="text-sm text-gray-500 mt-1">Transferencia interna entre sucursales</p>
            </div>
            <div class="text-right">
                <div class="text-3xl font-bold text-gray-900">#{{ str_pad($remito->id, 6, '0', STR_PAD_LEFT) }}</div>
                <div class="mt-1">
                    @php
                        $estadoColor = match($remito->estado->value) {
                            'remitido'   => 'bg-yellow-100 text-yellow-800 border border-yellow-300',
                            'confirmado' => 'bg-green-100 text-green-800 border border-green-300',
                            'cancelado'  => 'bg-red-100 text-red-800 border border-red-300',
                            default      => 'bg-gray-100 text-gray-800 border border-gray-300',
                        };
                    @endphp
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase {{ $estadoColor }}">
                        {{ $remito->estado->label() }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Datos del remito -->
        <div class="grid grid-cols-2 gap-8 py-6 border-b border-gray-200">
            <div class="space-y-3">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Origen</p>
                    <p class="text-base font-bold text-gray-900 mt-0.5">{{ $remito->sucursalOrigen->nombre }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Destino</p>
                    <p class="text-base font-bold text-gray-900 mt-0.5">{{ $remito->sucursalDestino->nombre }}</p>
                </div>
            </div>
            <div class="space-y-3">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Fecha de envío</p>
                    <p class="text-base text-gray-900 mt-0.5">{{ $remito->remitido_at->format('d/m/Y H:i') }}</p>
                </div>
                @if($remito->confirmado_at)
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Fecha de confirmación</p>
                        <p class="text-base text-gray-900 mt-0.5">{{ $remito->confirmado_at->format('d/m/Y H:i') }}</p>
                    </div>
                @endif
                @if($remito->user)
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Emitido por</p>
                        <p class="text-base text-gray-900 mt-0.5">{{ $remito->user->name }}</p>
                    </div>
                @endif
            </div>
        </div>

        @if($remito->observaciones)
            <div class="py-4 border-b border-gray-200">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Observaciones</p>
                <p class="text-sm text-gray-700">{{ $remito->observaciones }}</p>
            </div>
        @endif

        <!-- Tabla de productos -->
        <div class="py-6">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-gray-800 text-white">
                        <th class="px-4 py-2.5 text-left font-semibold text-xs uppercase tracking-wide w-10">#</th>
                        <th class="px-4 py-2.5 text-left font-semibold text-xs uppercase tracking-wide w-28">Código</th>
                        <th class="px-4 py-2.5 text-left font-semibold text-xs uppercase tracking-wide">Producto</th>
                        <th class="px-4 py-2.5 text-center font-semibold text-xs uppercase tracking-wide w-24">Cantidad</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($remito->detalles as $i => $detalle)
                        <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }} border-b border-gray-200">
                            <td class="px-4 py-3 text-gray-400 text-xs">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600">
                                {{ $detalle->product->codigo_interno ?? $detalle->product->codigo_barras ?? '-' }}
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $detalle->product->nombre }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-block px-3 py-1 bg-gray-100 rounded font-bold text-gray-900">
                                    {{ number_format($detalle->cantidad) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-800 text-white">
                        <td colspan="3" class="px-4 py-2.5 text-xs font-semibold uppercase text-right">Total unidades</td>
                        <td class="px-4 py-2.5 text-center font-bold">
                            {{ number_format($remito->detalles->sum('cantidad')) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Firmas -->
        <div class="mt-8 pt-6 border-t-2 border-gray-300 grid grid-cols-2 gap-16">
            <div>
                <div class="border-b border-gray-400 h-12 mb-2"></div>
                <p class="text-xs text-gray-500 font-medium">Firma y aclaración — Origen</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ $remito->sucursalOrigen->nombre }}</p>
            </div>
            <div>
                <div class="border-b border-gray-400 h-12 mb-2"></div>
                <p class="text-xs text-gray-500 font-medium">Firma y aclaración — Destino</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ $remito->sucursalDestino->nombre }}</p>
            </div>
        </div>

        <!-- Pie de página -->
        <div class="mt-10 pt-4 border-t border-gray-200 text-center">
            <p class="text-xs text-gray-400">
                Documento generado el {{ now()->format('d/m/Y H:i') }} — Remito #{{ str_pad($remito->id, 6, '0', STR_PAD_LEFT) }}
            </p>
        </div>

    </div>
</body>
</html>
