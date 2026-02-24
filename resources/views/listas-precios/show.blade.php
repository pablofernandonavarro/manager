<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $lista->nombre }} - Lista de Precios</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
            <div class="container mx-auto px-4 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <a href="{{ route('listas-precios.index') }}" class="text-gray-600 hover:text-gray-900">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                        </a>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">{{ $lista->nombre }}</h1>
                            <p class="text-sm text-gray-600">Factor: {{ number_format($lista->factor, 2) }}x • {{ $productos->count() }} productos</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <button onclick="window.print()" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                            Imprimir
                        </button>
                        <button onclick="exportToExcel()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Exportar Excel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla tipo Excel -->
        <div class="flex-1 overflow-auto bg-white">
            <table class="w-full text-sm border-collapse">
                <thead class="bg-gray-100 sticky top-0 z-10 border-b-2 border-gray-300">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300 w-24">Código</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300">Producto</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300 w-32">Precio Base</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300 w-32">Precio Lista</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300 w-28">Diferencia</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider w-24">% Margen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($productos as $producto)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 text-gray-900 font-mono text-xs border-r border-gray-200">
                                {{ $producto['codigo'] ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-gray-900 border-r border-gray-200">
                                {{ $producto['nombre'] }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-700 border-r border-gray-200">
                                ${{ number_format($producto['precio_base'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-blue-700 border-r border-gray-200">
                                ${{ number_format($producto['precio_lista'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold border-r border-gray-200 {{ $producto['diferencia'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $producto['diferencia'] >= 0 ? '+' : '' }}${{ number_format($producto['diferencia'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold {{ $producto['porcentaje'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $producto['porcentaje'] >= 0 ? '+' : '' }}{{ number_format($producto['porcentaje'], 1) }}%
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-100 border-t-2 border-gray-300 font-bold">
                    <tr>
                        <td colspan="2" class="px-4 py-3 text-right text-gray-700 border-r border-gray-300">
                            TOTAL DE PRODUCTOS:
                        </td>
                        <td class="px-4 py-3 text-right text-gray-900 border-r border-gray-300">
                            {{ $productos->count() }}
                        </td>
                        <td colspan="3" class="px-4 py-3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <script>
        function exportToExcel() {
            const table = document.querySelector('table');
            const html = table.outerHTML;
            const url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
            const link = document.createElement('a');
            link.href = url;
            link.download = '{{ $lista->nombre }}_precios_' + new Date().toISOString().split('T')[0] + '.xls';
            link.click();
        }
    </script>

    <style>
        @media print {
            button { display: none !important; }
            .sticky { position: relative !important; }
            body { background: white !important; }
        }
    </style>
</body>
</html>
