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
            'ventas.*.uuid' => 'required|uuid',
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

            // Etapa caja/cobro. Todo opcional: las cajas con versiones anteriores no lo mandan
            // y sus ventas tienen que seguir entrando.
            'ventas.*.turno_uuid' => 'nullable|uuid',
            'ventas.*.descuento_manual' => 'nullable|numeric|min:0',
            'ventas.*.descuento_autorizado_por' => 'nullable|string|max:100',
            'ventas.*.cajero' => 'nullable|string|max:100',
            'ventas.*.metodo_pago' => 'nullable|string|max:20',
            'ventas.*.cliente_nombre' => 'nullable|string|max:150',
            'ventas.*.cliente_documento' => 'nullable|string|max:30',
            'ventas.*.pagos' => 'nullable|array',
            'ventas.*.pagos.*.medio' => 'required|in:efectivo,debito,credito,transferencia,qr',
            'ventas.*.pagos.*.monto' => 'required|numeric|min:0',
            'ventas.*.pagos.*.descuento' => 'nullable|numeric|min:0',
            'ventas.*.pagos.*.importe' => 'required|numeric|min:0',
            'ventas.*.pagos.*.tarjeta' => 'nullable|string|max:30',
            'ventas.*.pagos.*.banco' => 'nullable|string|max:80',
            'ventas.*.pagos.*.cuotas' => 'nullable|integer|min:1|max:99',
            // Sin exists: una promo borrada en el Manager mientras la caja estaba offline no
            // puede dejar la venta rechazada para siempre. Se vincula solo si existe.
            'ventas.*.pagos.*.promocion_id' => 'nullable|integer',
            'ventas.*.pagos.*.promocion_nombre' => 'nullable|string|max:120',
            'ventas.*.pagos.*.referencia' => 'nullable|string|max:60',
        ];
    }
}
