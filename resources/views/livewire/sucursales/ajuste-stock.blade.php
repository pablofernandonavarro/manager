<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Ajuste de Inventario</h1>
            <p class="text-sm text-gray-500 mt-1">El stock se ajusta por sucursal y el total global se actualiza automáticamente.</p>
        </div>
        <div class="flex items-center gap-3">
            <label class="text-sm font-medium text-gray-700">Sucursal:</label>
            <select wire:model.live="sucursalId" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @foreach($sucursales as $s)
                    <option value="{{ $s->id }}">
                        {{ $s->nombre }}{{ $s->is_central ? ' (Central)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Mensajes flash --}}
    @if(session('success'))
        <div class="flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('info'))
        <div class="flex items-center gap-2 px-4 py-3 bg-blue-50 border border-blue-200 rounded-lg text-blue-800 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
            {{ session('info') }}
        </div>
    @endif

    {{-- Banner de borrador activo --}}
    @if($ajusteId)
        <div class="flex items-center justify-between px-4 py-3 bg-amber-50 border border-amber-300 rounded-lg">
            <div class="flex items-center gap-2 text-amber-800 text-sm">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <strong>Borrador activo</strong> — Hay cambios guardados sin aplicar.
                @if($descripcion)
                    <span class="text-amber-600">"{{ $descripcion }}"</span>
                @endif
            </div>
            <button wire:click="descartarBorrador"
                    @click="confirm('¿Descartar el borrador? Se perderán los cambios guardados.') || $event.stopImmediatePropagation()"
                    class="text-xs text-amber-700 underline hover:text-amber-900">
                Descartar
            </button>
        </div>
    @endif

    {{-- Tabs --}}
    <div x-data="{ tab: 'manual' }" class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="border-b border-gray-200 px-4">
            <nav class="flex -mb-px gap-6">
                <button @click="tab = 'manual'" :class="tab === 'manual' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-4 border-b-2 text-sm font-medium transition-colors">
                    Ingreso manual
                </button>
                <button @click="tab = 'excel'" :class="tab === 'excel' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-4 border-b-2 text-sm font-medium transition-colors">
                    Importar Excel
                </button>
            </nav>
        </div>

        {{-- Tab Manual --}}
        <div x-show="tab === 'manual'" class="p-6 space-y-4">

            {{-- Búsqueda + Descripción --}}
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <input type="text" wire:model.live.debounce.300ms="busqueda"
                           placeholder="Buscar por nombre, código interno o barras..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="sm:w-72">
                    <input type="text" wire:model="descripcion" maxlength="255"
                           placeholder="Descripción del ajuste (ej: Inventario Feb 2026)"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            {{-- Tabla de productos --}}
            @if($productos->isEmpty())
                <div class="text-center py-12 text-gray-400 text-sm">
                    @if($busqueda)
                        No se encontraron productos para "{{ $busqueda }}".
                    @else
                        No hay productos con stock en esta sucursal. Usá el buscador para encontrar productos.
                    @endif
                </div>
            @else
                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Código</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Producto</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Stock actual</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Nueva cantidad</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Diferencia</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach($productos as $producto)
                                @php
                                    $nuevoVal = isset($stockNuevo[$producto->id]) ? (int) $stockNuevo[$producto->id] : $producto->stock_actual;
                                    $delta = $nuevoVal - $producto->stock_actual;
                                    $modificado = $delta !== 0;
                                @endphp
                                <tr wire:key="prod-{{ $producto->id }}" class="{{ $modificado ? 'bg-amber-50' : '' }}">
                                    <td class="px-4 py-2 text-gray-500 font-mono text-xs whitespace-nowrap">
                                        {{ $producto->codigo_interno ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-900 font-medium">{{ $producto->nombre }}</td>
                                    <td class="px-4 py-2 text-right text-gray-700 font-mono">{{ $producto->stock_actual }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <input type="number" min="0"
                                               wire:model.lazy="stockNuevo.{{ $producto->id }}"
                                               class="w-24 px-2 py-1 text-right border rounded text-sm font-mono {{ $modificado ? 'border-amber-400 bg-amber-50 font-semibold' : 'border-gray-300' }} focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono font-semibold">
                                        @if($delta > 0)
                                            <span class="text-green-600">+{{ $delta }}</span>
                                        @elseif($delta < 0)
                                            <span class="text-red-600">{{ $delta }}</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Acciones --}}
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 border-t border-gray-100">
                <p class="text-xs text-gray-400">
                    Las filas en amarillo tienen cantidades modificadas. Los cambios fluyen hacia el stock global (central).
                </p>
                <div class="flex items-center gap-2">
                    <button wire:click="guardarBorrador" wire:loading.attr="disabled"
                            class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors border border-gray-300">
                        <span wire:loading.remove wire:target="guardarBorrador">Guardar borrador</span>
                        <span wire:loading wire:target="guardarBorrador">Guardando...</span>
                    </button>
                    <button wire:click="aplicarAjuste" wire:loading.attr="disabled"
                            @click="confirm('¿Aplicar el ajuste? Esta acción actualizará el stock de la sucursal y no se puede deshacer.') || $event.stopImmediatePropagation()"
                            class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                        <span wire:loading.remove wire:target="aplicarAjuste">Aplicar ajuste</span>
                        <span wire:loading wire:target="aplicarAjuste">Aplicando...</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Tab Excel --}}
        <div x-show="tab === 'excel'" style="display:none" class="p-6 space-y-5">

            {{-- Descripción --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción del ajuste</label>
                <input type="text" wire:model="descripcion" maxlength="255"
                       placeholder="Ej: Inventario físico Feb 2026"
                       class="w-full sm:w-96 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            {{-- Descargar plantilla --}}
            <div class="flex items-center gap-3 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                <svg class="w-8 h-8 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900">Plantilla Excel</p>
                    <p class="text-xs text-gray-500">Descargá la planilla con los productos y el stock actual de la sucursal seleccionada. Completá la columna <strong>nueva_cantidad</strong> y subila.</p>
                </div>
                <a href="{{ route('sucursales.ajuste-stock.plantilla', ['sucursal_id' => $sucursalId]) }}"
                   class="px-3 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors whitespace-nowrap">
                    Descargar plantilla
                </a>
            </div>

            {{-- Upload --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Subir archivo completado (.xlsx)</label>
                <input type="file" wire:model="archivo" accept=".xlsx,.xls"
                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <div wire:loading wire:target="archivo" class="mt-2 text-sm text-blue-600">Procesando archivo...</div>
            </div>

            {{-- Errores de importación --}}
            @if(!empty($erroresImportacion))
                <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm font-semibold text-red-800 mb-2">Errores encontrados:</p>
                    <ul class="space-y-1">
                        @foreach($erroresImportacion as $error)
                            <li class="text-xs text-red-700">• {{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Preview --}}
            @if(!empty($previsualizacion))
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">Vista previa — {{ count($previsualizacion) }} producto(s)</h3>
                        <button wire:click="resetImportacion" class="text-xs text-gray-400 hover:text-gray-600 underline">
                            Cancelar
                        </button>
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Código</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Producto</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Stock actual</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Nueva cantidad</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Diferencia</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach($previsualizacion as $fila)
                                    <tr class="{{ $fila['delta'] > 0 ? 'bg-green-50' : ($fila['delta'] < 0 ? 'bg-red-50' : '') }}">
                                        <td class="px-4 py-2 text-gray-500 font-mono text-xs">{{ $fila['codigo_interno'] ?: '—' }}</td>
                                        <td class="px-4 py-2 text-gray-900">{{ $fila['nombre'] }}</td>
                                        <td class="px-4 py-2 text-right font-mono text-gray-700">{{ $fila['stock_actual'] }}</td>
                                        <td class="px-4 py-2 text-right font-mono font-semibold text-gray-900">{{ $fila['nueva_cantidad'] }}</td>
                                        <td class="px-4 py-2 text-right font-mono font-semibold">
                                            @if($fila['delta'] > 0)
                                                <span class="text-green-700">+{{ $fila['delta'] }}</span>
                                            @elseif($fila['delta'] < 0)
                                                <span class="text-red-700">{{ $fila['delta'] }}</span>
                                            @else
                                                <span class="text-gray-400">0</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="flex justify-end">
                        <button wire:click="aplicarImportacion"
                                @click="confirm('¿Aplicar la importación? Esta acción actualizará el stock y no se puede deshacer.') || $event.stopImmediatePropagation()"
                                wire:loading.attr="disabled"
                                class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                            <span wire:loading.remove wire:target="aplicarImportacion">Aplicar importación</span>
                            <span wire:loading wire:target="aplicarImportacion">Aplicando...</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Historial de ajustes aplicados --}}
    @if($historial->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Últimos ajustes aplicados</h2>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach($historial as $ajuste)
                    <div class="px-6 py-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                {{ $ajuste->descripcion ?: 'Sin descripción' }}
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ $ajuste->aplicado_at?->format('d/m/Y H:i') }}
                                @if($ajuste->user)
                                    — {{ $ajuste->user->name }}
                                @endif
                            </p>
                        </div>
                        <span class="text-xs font-medium text-gray-600 bg-gray-100 px-2.5 py-1 rounded-full whitespace-nowrap">
                            {{ $ajuste->lineas->count() }} producto(s)
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</div>
