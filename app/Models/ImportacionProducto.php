<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportacionProducto extends Model
{
    protected $table = 'importaciones_productos';

    public const PENDIENTE = 'pendiente';

    public const PROCESANDO = 'procesando';

    public const TERMINADA = 'terminada';

    public const FALLIDA = 'fallida';

    protected $fillable = [
        'user_id', 'archivo', 'nombre_original', 'estado', 'filas', 'resumen', 'resultado', 'error', 'iniciado_at', 'terminado_at',
    ];

    protected function casts(): array
    {
        return [
            'resumen' => 'array',
            'resultado' => 'array',
            'iniciado_at' => 'datetime',
            'terminado_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enCurso(): bool
    {
        return in_array($this->estado, [self::PENDIENTE, self::PROCESANDO], true);
    }
}
