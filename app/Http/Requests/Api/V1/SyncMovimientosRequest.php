<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TipoMovimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SyncMovimientosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'movimientos' => 'required|array|min:1',
            'movimientos.*.product_id' => 'required|integer|exists:products,id',
            'movimientos.*.tipo' => ['required', new Enum(TipoMovimiento::class)],
            'movimientos.*.cantidad' => 'required|integer',
            'movimientos.*.referencia' => 'nullable|string|max:100',
            'movimientos.*.fecha' => 'required|date',
        ];
    }
}
