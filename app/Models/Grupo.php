<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grupo extends Model
{
    protected $fillable = ['nombre', 'activo'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Product::class, 'grupo');
    }
}
