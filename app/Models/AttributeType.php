<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttributeType extends Model
{
    /** @var list<string> */
    protected $fillable = ['nombre', 'slug', 'product_column', 'activo', 'orden'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('orden');
    }

    public function activeValues(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->where('activo', true)->orderBy('orden');
    }

    /** @return Collection<int, self> */
    public static function forVariants(): Collection
    {
        return static::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->with('activeValues')
            ->get();
    }
}
