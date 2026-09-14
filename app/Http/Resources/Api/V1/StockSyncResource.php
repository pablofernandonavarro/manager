<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockSyncResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'codigo_interno' => $this->product?->codigo_interno,
            'cantidad' => $this->cantidad,
        ];
    }
}
