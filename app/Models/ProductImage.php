<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'path',
        'label',
        'position',
        'is_base',
        'is_small',
        'is_thumbnail',
        'is_swatch',
        'disabled',
    ];

    protected function casts(): array
    {
        return [
            'is_base' => 'boolean',
            'is_small' => 'boolean',
            'is_thumbnail' => 'boolean',
            'is_swatch' => 'boolean',
            'disabled' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the roles assigned to this image.
     */
    public function getRoles(): array
    {
        $roles = [];
        if ($this->is_base) {
            $roles[] = 'base';
        }
        if ($this->is_small) {
            $roles[] = 'small';
        }
        if ($this->is_thumbnail) {
            $roles[] = 'thumbnail';
        }
        if ($this->is_swatch) {
            $roles[] = 'swatch';
        }
        return $roles;
    }
}
