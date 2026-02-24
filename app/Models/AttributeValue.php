<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeValue extends Model
{
    /** @var list<string> */
    protected $fillable = ['attribute_type_id', 'valor', 'orden', 'activo'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AttributeType::class, 'attribute_type_id');
    }
}
