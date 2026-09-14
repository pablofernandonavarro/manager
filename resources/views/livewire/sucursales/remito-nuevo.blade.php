<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Nuevo remito</h1>
            <p class="mt-2 text-sm text-gray-700">Enviá mercadería entre sucursales, Central incluida</p>
        </div>
        <a href="{{ route('sucursales.remitos') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver a remitos</a>
    </div>

    <!-- Origen y destino -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Sale de</label>
                <select wire:model.live="origenId"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    @foreach($sucursales as $sucursal)
                        <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}{{ $sucursal->isCentral() ? ' (Central)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="hidden md:flex pb-2 text-gray-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Va a</label>
                <select wire:model.live="destinoId"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">Elegí el destino…</option>
                    @foreach($sucursales as $sucursal)
                        @if($sucursal->id !== $origenId)
                            <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}{{ $sucursal->isCentral() ? ' (Central)' : '' }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Buscador -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <label class="block text-sm font-medium text-gray-700 mb-2">Agregar artículos</label>
        <input type="text"
               wire:model.live.debounce.300ms="busqueda"
               wire:keydown.enter.prevent="agregarUnico"
               placeholder="Nombre, código o código de barras · Enter agrega si hay uno solo"
               autofocus
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">

        @if($resultados->isNotEmpty())
            <div class="mt-3 border border-gray-200 rounded-lg divide-y divide-gray-100">
                @foreach($resultados as $producto)
                    <button type="button" wire:key="resultado-{{ $producto->id }}" wire:click="agregar({{ $producto->id }})"
                            class="w-full flex items-center justify-between px-4 py-2.5 text-left hover:bg-blue-50 transition-colors">
                        <span>
                            <span class="text-sm font-medium text-gray-900">{{ $producto->nombre }}</span>
                            <span class="ml-2 text-xs font-mono text-gray-500">{{ $producto->codigo_interno ?: $producto->codigo_barras }}</span>
                        </span>
                        <span class="text-xs {{ ($disponible[$producto->id] ?? 0) > 0 ? 'text-green-700' : 'text-gray-400' }}">
                            {{ number_format($disponible[$producto->id] ?? 0) }} en origen · Agregar
                        </span>
                    </button>
                @endforeach
            </div>
        @elseif(mb_strlen(trim($busqueda)) >= 2)
            <p class="mt-3 text-sm text-gray-400">No se encontraron artículos.</p>
        @endif
    </div>

    <!-- Artículos del remito -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Artículo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Disponible en origen</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase w-40">Cantidad</th>
                    <th class="px-6 py-3 w-12"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($items as $productId => $cantidad)
                    @php
                        $producto = $productos[$productId] ?? null;
                        $hay = (int) ($disponible[$productId] ?? 0);
                        $excede = (int) $cantidad > $hay;
                    @endphp
                    @if($producto)
                        <tr wire:key="item-{{ $productId }}" class="{{ $excede ? 'bg-red-50' : '' }}">
                            <td class="px-6 py-3 font-medium text-gray-900">{{ $producto->nombre }}</td>
                            <td class="px-6 py-3 font-mono text-xs text-gray-600">{{ $producto->codigo_interno ?: ($producto->codigo_barras ?: '-') }}</td>
                            <td class="px-6 py-3 text-center {{ $excede ? 'text-red-700 font-semibold' : 'text-gray-700' }}">{{ number_format($hay) }}</td>
                            <td class="px-6 py-3 text-center">
                                <input type="number" min="1" wire:model.live.debounce.400ms="items.{{ $productId }}"
                                       class="w-24 px-2 py-1.5 border {{ $excede ? 'border-red-400' : 'border-gray-300' }} rounded text-sm text-center focus:ring-2 focus:ring-blue-500">
                            </td>
                            <td class="px-6 py-3 text-right">
                                <button type="button" wire:click="quitar({{ $productId }})" class="text-gray-400 hover:text-red-600" title="Quitar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-400">
                            Todavía no agregaste artículos. Buscalos arriba o escaneá el código.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if(!empty($items))
                <tfoot class="bg-gray-50 border-t border-gray-200">
                    <tr>
                        <td colspan="3" class="px-6 py-3 text-sm font-semibold text-gray-700">
                            {{ count($items) }} artículo(s)
                        </td>
                        <td class="px-6 py-3 text-center text-sm font-bold text-gray-900">{{ number_format($totalUnidades) }} u.</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <!-- Observaciones y confirmación -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Observaciones (opcional)</label>
            <textarea wire:model="observaciones" rows="2" maxlength="1000"
                      placeholder="Ej: transporte, número de bulto, quién retira"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"></textarea>
        </div>

        @if($error)
            <div class="flex items-start gap-2 text-sm text-red-700 bg-red-50 rounded-lg px-4 py-3">
                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ $error }}
            </div>
        @endif

        <div class="flex justify-end">
            <button type="button" wire:click="crear" wire:loading.attr="disabled"
                    @disabled(empty($items) || !$destinoId)
                    class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="crear">Crear remito</span>
                <span wire:loading wire:target="crear">Creando…</span>
            </button>
        </div>
    </div>
</div>
