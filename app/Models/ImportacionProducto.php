<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportacionProducto extends Model
{
    protected $table = 'importaciones_productos';

    public const PENDIENTE = 'pendiente';

    public const PROCESANDO = 'procesando';

    public const COMPLETADA = 'completada';

    public const COMPLETADA_CON_ERRORES = 'completada_con_errores';

    public const FALLIDA = 'fallida';

    protected $fillable = [
        'user_id', 'archivo', 'nombre_original', 'estado', 'total_filas', 'filas_procesadas', 'filas_exitosas',
        'filas_con_error', 'creados', 'actualizados', 'modelos_nuevos', 'cambios_stock', 'lotes_total',
        'resumen', 'mensaje', 'iniciado_at', 'finalizado_at',
    ];

    protected function casts(): array
    {
        return [
            'resumen' => 'array',
            'iniciado_at' => 'datetime',
            'finalizado_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function errores(): HasMany
    {
        return $this->hasMany(ImportacionProductoError::class, 'importacion_id');
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(ImportacionProductoLote::class, 'importacion_id');
    }

    public function enCurso(): bool
    {
        return in_array($this->estado, [self::PENDIENTE, self::PROCESANDO], true);
    }

    public function porcentaje(): int
    {
        if ($this->total_filas <= 0) {
            return $this->enCurso() ? 0 : 100;
        }

        return (int) min(100, floor($this->filas_procesadas * 100 / $this->total_filas));
    }

    /** Texto de la referencia del ajuste de inventario: identifica a esta importación. */
    public function referencia(): string
    {
        return "Importación de productos #{$this->id} ({$this->nombre_original})";
    }
}
