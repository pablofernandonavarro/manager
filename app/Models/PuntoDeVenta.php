<?php

namespace App\Models;

use App\Support\SaludCaja;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
            'ultima_conexion_at' => 'datetime',
            'estado_caja' => 'array',
            'estado_reportado_at' => 'datetime',
            'stock_descarga_iniciada_at' => 'datetime',
            'stock_descarga_avance_at' => 'datetime',
            'stock_descarga_terminada_at' => 'datetime',
            'version_actualizada_at' => 'datetime',
        ];
    }

    /**
     * @return array{nivel: string, problemas: array<int, array{nivel: string, texto: string}>}
     */
    public function salud(?string $ultimaVersion = null, ?CarbonInterface $publicadaAt = null): array
    {
        return SaludCaja::evaluar($this, $ultimaVersion, publicadaAt: $publicadaAt);
    }

    /** Conectada si habló con el Manager hace poco: la caja consulta cada minuto. */
    public function estaConectada(): bool
    {
        return $this->ultima_conexion_at !== null && $this->ultima_conexion_at->gt(now()->subMinutes(3));
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

    public function codigosInstalacion(): HasMany
    {
        return $this->hasMany(CodigoInstalacion::class);
    }

    public function comandos(): HasMany
    {
        return $this->hasMany(ComandoPos::class);
    }

    public function ultimoComando(): HasOne
    {
        return $this->hasOne(ComandoPos::class)->latestOfMany();
    }
}
