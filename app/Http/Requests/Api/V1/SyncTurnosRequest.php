<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SyncTurnosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'turnos' => 'required|array|min:1|max:50',
            'turnos.*.uuid' => 'required|uuid',
            'turnos.*.numero' => 'required|integer|min:1',
            'turnos.*.cajero' => 'required|string|max:100',
            'turnos.*.estado' => 'required|in:abierto,cerrado',
            'turnos.*.fondo_inicial' => 'required|numeric|min:0',
            'turnos.*.abierto_at' => 'required|date',
            'turnos.*.cerrado_at' => 'nullable|required_if:turnos.*.estado,cerrado|date',
            'turnos.*.cantidad_ventas' => 'required|integer|min:0',
            'turnos.*.total_ventas' => 'required|numeric',
            'turnos.*.efectivo_esperado' => 'nullable|numeric',
            'turnos.*.efectivo_contado' => 'nullable|numeric|min:0',
            'turnos.*.diferencia' => 'nullable|numeric',
            'turnos.*.resumen' => 'nullable|array',
            'turnos.*.observaciones' => 'nullable|string|max:1000',
            'turnos.*.movimientos' => 'nullable|array',
            'turnos.*.movimientos.*.uuid' => 'required|uuid',
            'turnos.*.movimientos.*.tipo' => 'required|in:ingreso,retiro,gasto',
            'turnos.*.movimientos.*.monto' => 'required|numeric|min:0.01',
            'turnos.*.movimientos.*.motivo' => 'required|string|max:200',
            'turnos.*.movimientos.*.fecha' => 'required|date',
        ];
    }
}
