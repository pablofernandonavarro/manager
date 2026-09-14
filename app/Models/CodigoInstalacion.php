<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CodigoInstalacion extends Model
{
    protected $table = 'codigos_instalacion';

    protected $fillable = [
        'punto_de_venta_id',
        'codigo',
        'expira_at',
        'usado_at',
        'usado_ip',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'expira_at' => 'datetime',
            'usado_at' => 'datetime',
        ];
    }

    /**
     * Alfabeto sin caracteres que se confunden al dictarlos o tipearlos
     * (0/O, 1/I/L). El código se lee por teléfono o se copia a mano.
     */
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function puntoDeVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoDeVenta::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUtilizables(Builder $query): Builder
    {
        return $query->whereNull('usado_at')->where('expira_at', '>', now());
    }

    public function estaVigente(): bool
    {
        return $this->usado_at === null && $this->expira_at->isFuture();
    }

    /**
     * Genera un código nuevo e invalida los anteriores del mismo POS, para que no
     * queden códigos viejos dando vueltas que sigan sirviendo.
     */
    public static function generarPara(PuntoDeVenta $pdv, ?int $userId = null, int $horasDeVigencia = 24): self
    {
        self::where('punto_de_venta_id', $pdv->id)
            ->whereNull('usado_at')
            ->update(['expira_at' => now()]);

        do {
            $codigo = self::codigoAleatorio();
        } while (self::where('codigo', $codigo)->exists());

        return self::create([
            'punto_de_venta_id' => $pdv->id,
            'codigo' => $codigo,
            'expira_at' => now()->addHours($horasDeVigencia),
            'user_id' => $userId,
        ]);
    }

    private static function codigoAleatorio(): string
    {
        $bloques = [];

        foreach (range(1, 2) as $ignorado) {
            $bloque = '';
            foreach (range(1, 4) as $ignorado2) {
                $bloque .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
            }
            $bloques[] = $bloque;
        }

        return implode('-', $bloques);
    }

    /**
     * Acepta el código tipeado con o sin guion, en minúsculas y con espacios.
     */
    public static function normalizar(string $codigo): string
    {
        $limpio = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo));

        return strlen($limpio) === 8
            ? substr($limpio, 0, 4).'-'.substr($limpio, 4)
            : Str::upper(trim($codigo));
    }
}
