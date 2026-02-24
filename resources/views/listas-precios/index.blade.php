<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listas de Precios - Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Listas de Precios</h1>
            <p class="text-gray-600 mt-2">Gestiona las listas de precios y consulta los precios efectivos por producto</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($listas as $lista)
                <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-bold text-gray-800">{{ $lista->nombre }}</h2>
                            @if($lista->activo)
                                <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">Activa</span>
                            @else
                                <span class="px-3 py-1 bg-gray-100 text-gray-800 text-xs font-semibold rounded-full">Inactiva</span>
                            @endif
                        </div>

                        @if($lista->descripcion)
                            <p class="text-gray-600 text-sm mb-4">{{ $lista->descripcion }}</p>
                        @endif

                        <div class="mb-4">
                            <div class="text-sm text-gray-500">Factor de precio</div>
                            <div class="text-2xl font-bold text-blue-600">{{ number_format($lista->factor, 2) }}x</div>
                        </div>

                        <div class="mb-4">
                            <div class="text-sm text-gray-500 mb-2">Sucursales asignadas</div>
                            @if($lista->sucursales->count() > 0)
                                <div class="flex flex-wrap gap-2">
                                    @foreach($lista->sucursales as $sucursal)
                                        <span class="px-2 py-1 bg-blue-50 text-blue-700 text-xs rounded">
                                            {{ $sucursal->nombre }}
                                            @if($sucursal->pivot->es_default)
                                                <span class="ml-1">⭐</span>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-gray-400 text-sm">Sin sucursales asignadas</p>
                            @endif
                        </div>

                        <a href="{{ route('listas-precios.show', $lista) }}" class="block w-full text-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                            Ver Productos y Precios
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        @if($listas->isEmpty())
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">No hay listas de precios</h3>
                <p class="text-gray-500">Crea una lista de precios para comenzar</p>
            </div>
        @endif
    </div>
</body>
</html>
