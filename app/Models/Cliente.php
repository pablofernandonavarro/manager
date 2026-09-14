<?php

namespace App\Models;

use App\Support\Cuit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cliente con sus datos fiscales y, si está habilitada, cuenta corriente. El saldo es la
 * suma de sus movimientos: positivo = debe.
 */
class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = [
        'nombre', 'doc_tipo', 'documento', 'condicion_iva', 'email', 'telefono', 'domicilio',
        'cuenta_corriente', 'limite_credito', 'activo', 'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'doc_tipo' => 'integer',
            'condicion_iva' => 'integer',
            'cuenta_corriente' => 'boolean',
            'limite_credito' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCuentaCorriente::class);
    }

    public function scopeBuscar(Builder $query, string $texto): Builder
    {
        $texto = trim($texto);
        $digitos = preg_replace('/\D/', '', $texto);

        return $query->where(fn ($q) => $q->where('nombre', 'like', "%{$texto}%")
            ->when($digitos !== '', fn ($w) => $w->orWhere('documento', 'like', "%{$digitos}%")));
    }

    /** Saldo en pesos (positivo = debe). */
    public function saldo(): float
    {
        return round((float) $this->movimientos()->sum('importe'), 2);
    }

    public function documentoFormateado(): ?string
    {
        if (! $this->documento) {
            return null;
        }

        return in_array($this->doc_tipo, [80, 86], true) ? Cuit::formatear($this->documento) : $this->documento;
    }
}
