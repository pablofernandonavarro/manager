<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportacionProductoError extends Model
{
    protected $table = 'importacion_producto_errores';

    protected $fillable = ['importacion_id', 'fila', 'codigo', 'mensaje', 'datos'];

    protected function casts(): array
    {
        return ['datos' => 'array'];
    }
}
