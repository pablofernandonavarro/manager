<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sucursal extends Model
{
    use HasFactory;

    protected $table = 'sucursales';

    protected $fillable = [
        'nombre',
        'direccion',
        'telefono',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function listasPrecios(): BelongsToMany
    {
        return $this->belongsToMany(ListaPrecio::class, 'sucursal_lista_precio')
            ->withPivot('es_default')
            ->withTimestamps();
    }

    public function puntosDeVenta(): HasMany
    {
        return $this->hasMany(PuntoDeVenta::class);
    }

    public function stockSucursal(): HasMany
    {
        return $this->hasMany(StockSucursal::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }
}
