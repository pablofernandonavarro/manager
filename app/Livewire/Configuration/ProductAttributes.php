<?php

namespace App\Livewire\Configuration;

use App\Models\AttributeType;
use App\Models\AttributeValue;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

class ProductAttributes extends Component
{
    public string $newTypeName = '';

    /** @var array<int, string> */
    public array $newValueInputs = [];

    public function addType(): void
    {
        $this->validate(['newTypeName' => 'required|string|max:100']);

        AttributeType::create([
            'nombre' => $this->newTypeName,
            'slug' => Str::slug($this->newTypeName),
            'product_column' => null,
            'activo' => true,
            'orden' => AttributeType::max('orden') + 1,
        ]);

        $this->newTypeName = '';
    }

    public function deleteType(int $id): void
    {
        $type = AttributeType::withCount('values')->findOrFail($id);

        if ($type->values_count > 0) {
            session()->flash('error', 'No se puede eliminar el tipo porque tiene valores asociados.');

            return;
        }

        $type->delete();
    }

    public function toggleTypeActive(int $id): void
    {
        $type = AttributeType::findOrFail($id);
        $type->update(['activo' => ! $type->activo]);
    }

    public function addValue(int $typeId): void
    {
        $valor = trim($this->newValueInputs[$typeId] ?? '');

        if ($valor === '') {
            return;
        }

        AttributeValue::create([
            'attribute_type_id' => $typeId,
            'valor' => $valor,
            'orden' => AttributeValue::where('attribute_type_id', $typeId)->max('orden') + 1,
            'activo' => true,
        ]);

        $this->newValueInputs[$typeId] = '';
    }

    public function deleteValue(int $id): void
    {
        AttributeValue::findOrFail($id)->delete();
    }

    public function toggleValueActive(int $id): void
    {
        $value = AttributeValue::findOrFail($id);
        $value->update(['activo' => ! $value->activo]);
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.configuration.product-attributes', [
            'attributeTypes' => AttributeType::with('values')->orderBy('orden')->get(),
        ]);
    }
}
