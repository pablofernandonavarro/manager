<?php

namespace App\Models;

use App\Enums\ComandoPos as ComandoPosEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComandoPos extends Model
{
    protected $table = 'comandos_pos';

    public const PENDIENTE = 'pendiente';

    public const TOMADO = 'tomado';

    public const COMPLETADO = 'completado';

    public const FALLIDO = 'fallido';

    protected $fillable = [
        'punto_de_venta_id',
        'comando',
        'estado',
        'resultado',
        'user_id',
        'tomado_at',
        'finalizado_at',
    ];

    protected function casts(): array
    {
        return [
            'comando' => ComandoPosEnum::class,
            'tomado_at' => 'datetime',
            'finalizado_at' => 'datetime',
        ];
    }

    public function puntoDeVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoDeVenta::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', self::PENDIENTE);
    }

    /**
     * Encola una orden evitando duplicados: si ya hay una igual sin ejecutar para esa
     * caja, no tiene sentido apilar otra — la caja las procesa en orden y el efecto
     * de correr dos veces lo mismo es el mismo que correrlo una vez.
     */
    public static function encolar(PuntoDeVenta $pdv, ComandoPosEnum $comando, ?int $userId = null): self
    {
        $existente = self::where('punto_de_venta_id', $pdv->id)
            ->where('comando', $comando->value)
            ->whereIn('estado', [self::PENDIENTE, self::TOMADO])
            ->first();

        if ($existente) {
            return $existente;
        }

        return self::create([
            'punto_de_venta_id' => $pdv->id,
            'comando' => $comando,
            'estado' => self::PENDIENTE,
            'user_id' => $userId,
        ]);
    }
}
