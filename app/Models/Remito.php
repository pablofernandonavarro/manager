<?php

namespace App\Models;

use App\Enums\EstadoRemito;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Remito extends Model
{
    protected $fillable = [
        'sucursal_origen_id',
        'sucursal_destino_id',
        'user_id',
        'estado',
        'observaciones',
        'remitido_at',
        'confirmado_at',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoRemito::class,
            'remitido_at' => 'datetime',
            'confirmado_at' => 'datetime',
        ];
    }

    public function sucursalOrigen(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_origen_id');
    }

    public function sucursalDestino(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_destino_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(RemitoDetalle::class);
    }
}
