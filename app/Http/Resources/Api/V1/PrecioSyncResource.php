<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrecioSyncResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'lista_precio_id' => $this->lista_precio_id,
            'product_id' => $this->product_id,
            'codigo_interno' => $this->product?->codigo_interno,
            'precio_override' => $this->precio_override,
            'vigencia_desde' => $this->vigencia_desde?->toDateString(),
            'vigencia_hasta' => $this->vigencia_hasta?->toDateString(),
        ];
    }
}
