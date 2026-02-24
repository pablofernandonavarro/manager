<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class PuntoDeVenta extends Model
{
    use HasApiTokens, HasFactory;

    protected $table = 'puntos_de_venta';

    protected $fillable = [
        'sucursal_id',
        'nombre',
        'secret',
        'activo',
    ];

    protected $hidden = [
        'secret',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoStock::class);
    }
}
