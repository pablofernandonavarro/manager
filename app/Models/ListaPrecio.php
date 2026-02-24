<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ListaPrecio extends Model
{
    use HasFactory;

    protected $table = 'listas_precios';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
        'factor',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'factor' => 'decimal:4',
        ];
    }

    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class, 'sucursal_lista_precio')
            ->withPivot('es_default')
            ->withTimestamps();
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePrecio::class);
    }

    /**
     * Precio efectivo para un producto con un único JOIN.
     *
     * Lógica (en orden de prioridad):
     * 1. Override explícito en detalle_lista_precios para la variante
     * 2. Override explícito en detalle_lista_precios para el padre configurable
     * 3. products.precio del padre (o del propio producto si no tiene padre) × lista.factor
     */
    public function precioEfectivoParaProducto(int $productId): ?float
    {
        $hoy = now()->toDateString();

        $result = DB::selectOne('
            SELECT COALESCE(
                -- 1. Override propio de la variante
                ov.precio_override,
                -- 2. Override del padre configurable
                op.precio_override,
                -- 3. Precio base del padre (o propio) × factor de la lista
                COALESCE(padre.precio, prod.precio) * :factor
            ) AS precio_efectivo
            FROM products prod
            LEFT JOIN products padre ON padre.id = prod.parent_id
            LEFT JOIN detalle_lista_precios ov
                ON ov.product_id = prod.id
               AND ov.lista_precio_id = :lista_id
               AND (ov.vigencia_desde IS NULL OR ov.vigencia_desde <= :hoy1)
               AND (ov.vigencia_hasta IS NULL OR ov.vigencia_hasta >= :hoy2)
            LEFT JOIN detalle_lista_precios op
                ON op.product_id = prod.parent_id
               AND op.lista_precio_id = :lista_id2
               AND (op.vigencia_desde IS NULL OR op.vigencia_desde <= :hoy3)
               AND (op.vigencia_hasta IS NULL OR op.vigencia_hasta >= :hoy4)
            WHERE prod.id = :product_id
        ', [
            'factor' => $this->factor,
            'lista_id' => $this->id,
            'lista_id2' => $this->id,
            'hoy1' => $hoy,
            'hoy2' => $hoy,
            'hoy3' => $hoy,
            'hoy4' => $hoy,
            'product_id' => $productId,
        ]);

        return $result?->precio_efectivo !== null
            ? (float) $result->precio_efectivo
            : null;
    }
}
