<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Show extends Component
{
    public Product $product;

    public function mount(int $productId): void
    {
        $this->product = Product::with(['images', 'variants.images', 'parent'])
            ->findOrFail($productId);
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.products.show');
    }
}
