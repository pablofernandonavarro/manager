{{-- Sucursal donde entra el stock inicial del alta (producto simple o variantes). --}}
<div>
    <label class="block text-sm font-semibold text-gray-700 mb-2">Sucursal del stock inicial</label>
    <select wire:model="sucursalStockId" class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm @error('sucursalStockId') border-red-500 @enderror">
        <option value="">Sin stock inicial</option>
        @foreach($sucursales as $s)
            <option value="{{ $s->id }}">{{ $s->nombre }}</option>
        @endforeach
    </select>
    @error('sucursalStockId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    <p class="mt-1 text-xs text-gray-500">Las cantidades entran a esa sucursal y llegan a sus cajas. Después se mueve con remitos o ajustes.</p>
</div>
