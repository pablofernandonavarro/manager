<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersionPos extends Model
{
    protected $table = 'versiones_pos';

    protected $fillable = [
        'version',
        'archivo',
        'hash',
        'tamano',
        'notas',
        'vigente',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'vigente' => 'boolean',
            'tamano' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function vigente(): ?self
    {
        return self::where('vigente', true)->latest('id')->first();
    }

    /**
     * Solo una version puede estar vigente: es la que van a bajar todas las cajas.
     */
    public function marcarVigente(): void
    {
        self::query()->update(['vigente' => false]);
        $this->update(['vigente' => true]);
    }
}
