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
            'busqueda' => $this->busqueda,
            'precio' => $this->precio,
            'costo' => $this->costo,
            'stock' => $this->stock,
            'stock_critico' => $this->stock_critico,
            'imagen_url' => $this->imagen_url,
            'descripcion_web' => $this->descripcion_web,
            'marca' => $this->marca,
            'color' => $this->color,
            'n_talle' => $this->n_talle,
            'genero' => $this->genero,
            'n_grupo' => $this->n_grupo,
            'n_subgrupo' => $this->n_subgrupo,
            'n_temporada' => $this->n_temporada,
            'product_type' => $this->product_type,
            'parent_id' => $this->parent_id,
            'es_vendible' => $this->es_vendible,
            'atributos_extra' => $this->atributos_extra,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
