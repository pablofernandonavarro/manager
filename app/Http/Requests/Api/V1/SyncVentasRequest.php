<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SyncVentasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ventas' => 'required|array|min:1',
            'ventas.*.numero_venta' => 'nullable|string|max:50',
            'ventas.*.fecha' => 'required|date',
            'ventas.*.lista_precio_id' => 'nullable|integer|exists:listas_precios,id',
            'ventas.*.subtotal' => 'required|numeric|min:0',
            'ventas.*.descuento' => 'nullable|numeric|min:0',
            'ventas.*.total' => 'required|numeric|min:0',
            'ventas.*.items' => 'required|array|min:1',
            'ventas.*.items.*.product_id' => 'required|integer|exists:products,id',
            'ventas.*.items.*.cantidad' => 'required|integer|min:1',
            'ventas.*.items.*.precio_unitario' => 'required|numeric|min:0',
            'ventas.*.items.*.subtotal' => 'required|numeric|min:0',
        ];
    }
}
