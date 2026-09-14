<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportacionProductoLote extends Model
{
    protected $table = 'importacion_producto_lotes';

    protected $fillable = ['importacion_id', 'lote', 'desde', 'hasta', 'procesadas', 'exitosas', 'errores', 'duracion_ms', 'memoria_mb'];
}
