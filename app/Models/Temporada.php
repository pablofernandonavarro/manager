<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Temporada extends Model
{
    protected $fillable = ['nombre', 'anio', 'activo'];

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Product::class, 'temporada');
    }
}
