<div class="space-y-6">
    <nav class="flex items-center text-sm text-gray-600">
        <a href="/productos" wire:navigate class="hover:text-gray-900">Productos</a>
        <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-900">Importar desde Excel</span>
    </nav>

    <div class="sm:flex sm:items-end sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Importar productos</h1>
            <p class="mt-2 text-sm text-gray-600 max-w-3xl">
                Una fila por artículo. Sin <strong>modelo</strong> es un producto simple (se busca por <strong>codigo</strong>);
                con modelo es una variante color + talle. Los productos que ya existen se actualizan. Las columnas
                <strong>stock &lt;sucursal&gt;</strong> dejan el stock en ese número; vacío no se toca.
            </p>
        </div>
        <a href="{{ route('productos.importar.plantilla') }}"
           class="mt-4 sm:mt-0 inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Descargar plantilla
        </a>
    </div>

    @if($importaciones->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" @if($hayEnCurso) wire:poll.3s @endif>
            <div class="px-6 py-3 border-b border-gray-200 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-gray-900">Importaciones recientes</h2>
                @if($hayEnCurso)
                    <span class="inline-flex items-center gap-2 text-xs font-medium text-blue-700">
                        <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                        La importación continúa en segundo plano. Podés salir de esta pantalla sin cancelarla.
                    </span>
                @endif
            </div>
            <div class="divide-y divide-gray-100">
                @foreach($importaciones as $imp)
                    @php
                        [$claseEstado, $textoEstado] = [
                            'pendiente' => ['bg-gray-100 text-gray-700', 'En cola'],
                            'procesando' => ['bg-blue-100 text-blue-800', 'Procesando'],
                            'completada' => ['bg-green-100 text-green-800', 'Completada'],
                            'completada_con_errores' => ['bg-amber-100 text-amber-800', 'Completada con errores'],
                            'fallida' => ['bg-red-100 text-red-800', 'Fallida'],
                        ][$imp->estado] ?? ['bg-gray-100 text-gray-700', $imp->estado];
                        $porcentaje = $imp->porcentaje();
                    @endphp
                    <div class="px-6 py-4 space-y-2" wire:key="imp-{{ $imp->id }}">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $claseEstado }}">{{ $textoEstado }}</span>
                            <span class="font-medium text-gray-900">{{ $imp->nombre_original }}</span>
                            <span class="text-gray-400">#{{ $imp->id }} · {{ $imp->user?->name }} · {{ $imp->created_at->timezone(config('app.display_timezone'))->format('d/m H:i') }}</span>
                            <span class="ml-auto text-sm font-semibold tabular-nums text-gray-700">{{ $porcentaje }}%</span>
                        </div>

                        <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500 {{ $imp->estado === 'fallida' ? 'bg-red-500' : ($imp->filas_con_error > 0 ? 'bg-amber-500' : 'bg-green-500') }}"
                                 style="width: {{ $porcentaje }}%"></div>
                        </div>

                        <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-gray-600 tabular-nums">
                            <span>Total: <strong class="text-gray-900">{{ number_format($imp->total_filas, 0, ',', '.') }}</strong></span>
                            <span>Procesadas: <strong class="text-gray-900">{{ number_format($imp->filas_procesadas, 0, ',', '.') }}</strong></span>
                            <span>Exitosas: <strong class="text-green-700">{{ number_format($imp->filas_exitosas, 0, ',', '.') }}</strong></span>
                            <span>Con error: <strong class="{{ $imp->filas_con_error ? 'text-red-700' : 'text-gray-900' }}">{{ number_format($imp->filas_con_error, 0, ',', '.') }}</strong></span>
                            @if($imp->filas_exitosas)
                                <span class="text-gray-400">{{ $imp->creados }} creados · {{ $imp->actualizados }} actualizados · {{ $imp->modelos_nuevos }} modelos nuevos · {{ $imp->cambios_stock }} cambios de stock</span>
                            @endif
                            @if($imp->finalizado_at && $imp->iniciado_at)
                                <span class="text-gray-400">{{ max(1, (int) ceil($imp->iniciado_at->diffInSeconds($imp->finalizado_at) / 60)) }} min</span>
                            @endif
                        </div>

                        @if($imp->mensaje)
                            <p class="text-xs {{ $imp->estado === 'fallida' ? 'text-red-700' : 'text-gray-600' }}">{{ $imp->mensaje }}</p>
                        @endif

                        @if($imp->filas_con_error > 0)
                            <button type="button" wire:click="alternarErrores({{ $imp->id }})" class="text-xs font-medium text-blue-600 hover:text-blue-800">
                                {{ $verErroresDe === $imp->id ? 'Ocultar errores' : 'Ver filas con error' }}
                            </button>
                            @if($verErroresDe === $imp->id)
                                <div class="max-h-80 overflow-auto rounded-lg border border-gray-200">
                                    <table class="min-w-full text-xs">
                                        <thead class="bg-gray-50 sticky top-0">
                                            <tr>
                                                <th class="px-3 py-2 text-left font-medium text-gray-500">Fila</th>
                                                <th class="px-3 py-2 text-left font-medium text-gray-500">Código</th>
                                                <th class="px-3 py-2 text-left font-medium text-gray-500">Error</th>
                                                <th class="px-3 py-2 text-left font-medium text-gray-500">Datos</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach($erroresDeImportacion as $error)
                                                <tr wire:key="err-{{ $error->id }}">
                                                    <td class="px-3 py-1.5 tabular-nums text-gray-500">{{ $error->fila }}</td>
                                                    <td class="px-3 py-1.5 font-mono text-gray-700">{{ $error->codigo }}</td>
                                                    <td class="px-3 py-1.5 text-red-700">{{ $error->mensaje }}</td>
                                                    <td class="px-3 py-1.5 text-gray-500">{{ collect($error->datos)->map(fn ($v, $k) => "{$k}: {$v}")->implode(' · ') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    @if($imp->filas_con_error > $erroresDeImportacion->count())
                                        <p class="px-3 py-2 text-xs text-gray-500 border-t border-gray-200">Se muestran {{ $erroresDeImportacion->count() }} de {{ $imp->filas_con_error }}.</p>
                                    @endif
                                </div>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <label class="block text-sm font-semibold text-gray-700 mb-2">Archivo (.xlsx, .xls o .csv)</label>
        <input type="file" wire:model="archivo" accept=".xlsx,.xls,.csv"
               class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-600 file:text-white hover:file:bg-blue-700">
        <div wire:loading wire:target="archivo" class="mt-2 text-sm text-gray-500">Leyendo el archivo…</div>
        @error('archivo') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    @if($errores)
        <div class="rounded-lg bg-red-50 p-4 border-l-4 border-red-400">
            <p class="text-sm font-semibold text-red-800 mb-2">No se puede importar: corregí el archivo y volvé a subirlo.</p>
            <ul class="list-disc ml-5 text-sm text-red-700 space-y-0.5 max-h-72 overflow-y-auto">
                @foreach($errores as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($totalFilas > 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm text-gray-700">
                    <p><strong>{{ number_format($totalFilas, 0, ',', '.') }}</strong> filas en el archivo.</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        En las primeras {{ count($previa) + count($avisos) }}: {{ $resumen['crear'] ?? 0 }} para crear ·
                        {{ $resumen['actualizar'] ?? 0 }} para actualizar · {{ $resumen['modelos_nuevos'] ?? 0 }} modelos nuevos ·
                        {{ $resumen['con_stock'] ?? 0 }} con stock
                    </p>
                </div>
                <div class="flex gap-2">
                    <button type="button" wire:click="descartar" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Descartar</button>
                    <button type="button" wire:click="aplicar" wire:loading.attr="disabled" @disabled($errores)
                            class="px-4 py-2 text-sm font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="aplicar">Importar {{ number_format($totalFilas, 0, ',', '.') }} filas</span>
                        <span wire:loading wire:target="aplicar">Enviando…</span>
                    </button>
                </div>
            </div>

            @if($avisos)
                <div class="px-6 py-3 bg-amber-50 border-b border-amber-200">
                    <p class="text-xs font-semibold text-amber-800 mb-1">Estas filas van a quedar con error; el resto se importa igual:</p>
                    <ul class="list-disc ml-5 text-xs text-amber-800 space-y-0.5 max-h-40 overflow-y-auto">
                        @foreach($avisos as $aviso)
                            <li>{{ $aviso }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            @foreach(['Fila', 'Acción', 'Modelo / código', 'Nombre', 'Variante', 'Cód. barras', 'Precio', 'Stock'] as $th)
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">{{ $th }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($previa as $f)
                            <tr wire:key="fila-{{ $f['fila'] }}">
                                <td class="px-4 py-2 text-gray-400">{{ $f['fila'] }}</td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $f['accion'] === 'crear' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                        {{ $f['accion'] === 'crear' ? 'Nuevo' : 'Actualiza' }}{{ $f['tipo'] === 'variante' && ! $f['modelo_id'] ? ' · modelo nuevo' : '' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs text-gray-700">{{ $f['modelo'] ?: $f['codigo'] }}</td>
                                <td class="px-4 py-2 text-gray-900">{{ $f['nombre'] ?: '—' }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ $f['tipo'] === 'variante' ? $f['color'].' / '.$f['talle'] : '' }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-gray-500">{{ $f['codigo_barras'] ?: ($f['accion'] === 'crear' ? 'automático' : '') }}</td>
                                <td class="px-4 py-2 text-gray-700 whitespace-nowrap">{{ $f['precio'] !== null ? '$ '.number_format($f['precio'], 2, ',', '.') : '' }}</td>
                                <td class="px-4 py-2 text-gray-700 whitespace-nowrap">
                                    {{ collect($f['stock'])->map(fn ($c, $id) => ($sucursales[$id] ?? "#{$id}").': '.$c)->implode(' · ') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($totalFilas > count($previa))
                <p class="px-6 py-3 text-xs text-gray-500 border-t border-gray-200">Vista previa de las primeras filas; se importan las {{ number_format($totalFilas, 0, ',', '.') }}.</p>
            @endif
        </div>
    @endif
</div>
