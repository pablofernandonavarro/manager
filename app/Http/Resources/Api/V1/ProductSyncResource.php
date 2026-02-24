<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSyncResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'codigo_interno' => $this->codigo_interno,
            'codigo_barras' => $this->codigo_barras,
            'precio' => $this->precio,
            'stock' => $this->stock,
            'es_vendible' => $this->es_vendible,
            'parent_id' => $this->parent_id,
            'color' => $this->color,
            'n_talle' => $this->n_talle,
            'atributos_extra' => $this->atributos_extra,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
