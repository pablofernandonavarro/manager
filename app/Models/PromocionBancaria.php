<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Promoción de un banco o billetera sobre un medio de pago: descuento en caja o
 * reintegro del banco (informativo), y/o cuotas sin interés. Se definen acá y bajan a
 * las cajas por sync/promociones; la caja la aplica al cobrar.
 */
class PromocionBancaria extends Model
{
    protected $table = 'promociones_bancarias';

    public const MEDIOS = [
        'credito' => 'Tarjeta de crédito',
        'debito' => 'Tarjeta de débito',
        'qr' => 'QR / billetera',
    ];

    public const TARJETAS = [
        'visa' => 'Visa',
        'mastercard' => 'Mastercard',
        'amex' => 'American Express',
        'cabal' => 'Cabal',
        'naranja' => 'Naranja',
        'maestro' => 'Maestro',
    ];

    public const DIAS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    protected $fillable = [
        'nombre',
        'banco',
        'medios',
        'tarjetas',
        'dias_semana',
        'sucursales',
        'vigencia_desde',
        'vigencia_hasta',
        'modalidad',
        'porcentaje',
        'tope',
        'monto_minimo',
        'cuotas_sin_interes',
        'observaciones',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'medios' => 'array',
            'tarjetas' => 'array',
            'dias_semana' => 'array',
            'sucursales' => 'array',
            'vigencia_desde' => 'date',
            'vigencia_hasta' => 'date',
            'porcentaje' => 'decimal:2',
            'tope' => 'decimal:2',
            'monto_minimo' => 'decimal:2',
            'cuotas_sin_interes' => 'integer',
            'activa' => 'boolean',
        ];
    }

    /**
     * Las que una sucursal puede usar hoy o más adelante. Las vencidas no se mandan: la
     * caja las borraría igual, y así el payload no crece con el historial.
     */
    public function scopeParaSucursal(Builder $query, int $sucursalId): Builder
    {
        return $query->where('activa', true)
            ->where(fn ($q) => $q->whereNull('vigencia_hasta')->orWhereDate('vigencia_hasta', '>=', now()->toDateString()))
            ->where(fn ($q) => $q->whereNull('sucursales')->orWhereJsonContains('sucursales', $sucursalId));
    }

    public function estaVigente(): bool
    {
        return $this->activa
            && ($this->vigencia_desde === null || $this->vigencia_desde->lte(today()))
            && ($this->vigencia_hasta === null || $this->vigencia_hasta->gte(today()));
    }
}
