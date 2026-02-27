<?php

namespace App\Models;

use App\Enums\EstadoAjuste;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AjusteInventario extends Model
{
    use HasFactory;

    protected $table = 'ajustes_inventario';

    protected $fillable = [
        'sucursal_id',
        'user_id',
        'descripcion',
        'estado',
        'aplicado_at',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoAjuste::class,
            'aplicado_at' => 'datetime',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(AjusteInventarioLinea::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoStock::class);
    }

    public function esBorrador(): bool
    {
        return $this->estado === EstadoAjuste::Borrador;
    }
}
