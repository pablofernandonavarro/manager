<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Cajero extends Model
{
    protected $table = 'cajeros';

    public const ROLES = [
        'cajero' => 'Cajero',
        'supervisor' => 'Supervisor',
    ];

    protected $fillable = ['nombre', 'pin_hash', 'rol', 'sucursales', 'activo'];

    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return [
            'sucursales' => 'array',
            'activo' => 'boolean',
        ];
    }

    public function scopeParaSucursal(Builder $query, int $sucursalId): Builder
    {
        return $query->where('activo', true)
            ->where(fn ($q) => $q->whereNull('sucursales')->orWhereJsonContains('sucursales', $sucursalId));
    }
}
