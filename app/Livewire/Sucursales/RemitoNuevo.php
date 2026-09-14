<?php

namespace App\Livewire\Sucursales;

use App\Exceptions\RemitoException;
use App\Models\Product;
use App\Models\StockSucursal;
use App\Models\Sucursal;
use App\Services\RemitoService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Remito con varios artículos entre dos sucursales cualesquiera, Central incluida,
 * en cualquier sentido.
 */
class RemitoNuevo extends Component
{
    use AuthorizesRequests;

    public ?int $origenId = null;

    public ?int $destinoId = null;

    public string $busqueda = '';

    /**
     * @var array<int, int> product_id => cantidad
     */
    public array $items = [];

    public string $observaciones = '';

    public ?string $error = null;

    public function mount(): void
    {
        $this->authorize('remitos.crear');

        $this->origenId = Sucursal::where('activo', true)->orderByDesc('is_central')->orderBy('nombre')->value('id');
    }

    public function updatedOrigenId(): void
    {
        if ($this->destinoId === $this->origenId) {
            $this->destinoId = null;
        }

        $this->error = null;
    }

    public function agregar(int $productId): void
    {
        if (! Product::whereKey($productId)->exists()) {
            return;
        }

        $this->items[$productId] = ($this->items[$productId] ?? 0) + 1;
        $this->busqueda = '';
        $this->error = null;
    }

    /**
     * Enter en el buscador: si queda un solo artículo se agrega. Es lo que manda un lector
     * de códigos de barras al terminar de escanear.
     */
    public function agregarUnico(): void
    {
        $resultados = $this->buscar(2);

        if ($resultados->count() === 1) {
            $this->agregar($resultados->first()->id);
        }
    }

    public function quitar(int $productId): void
    {
        unset($this->items[$productId]);
    }

    public function crear(RemitoService $remitos): void
    {
        $this->authorize('remitos.crear');

        $this->error = null;

        if (! $this->origenId || ! $this->destinoId) {
            $this->error = 'Elegí el origen y el destino.';

            return;
        }

        try {
            $remito = $remitos->crear($this->origenId, $this->destinoId, $this->items, auth()->user(), trim($this->observaciones));
        } catch (RemitoException $e) {
            $this->error = $e->getMessage();

            return;
        }

        session()->flash('success', "Remito #{$remito->id} creado: {$remito->detalles->count()} artículo(s), "
            .number_format($remito->detalles->sum('cantidad')).' unidades en tránsito. El destino tiene que confirmar la recepción.');

        $this->redirectRoute('sucursales.remitos', ['sucursal' => $remito->sucursal_origen_id, 'direccion' => 'enviados']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function buscar(int $limite)
    {
        $texto = trim($this->busqueda);

        if (mb_strlen($texto) < 2) {
            return collect();
        }

        return Product::query()
            ->where(function ($q) use ($texto) {
                $q->where('codigo_interno', $texto)
                    ->orWhere('codigo_barras', $texto);
            })
            ->limit(1)
            ->get()
            ->whenEmpty(fn () => Product::query()
                ->where(function ($q) use ($texto) {
                    $q->where('nombre', 'like', "%{$texto}%")
                        ->orWhere('codigo_interno', 'like', "%{$texto}%")
                        ->orWhere('codigo_barras', 'like', "%{$texto}%");
                })
                ->orderBy('nombre')
                ->limit($limite)
                ->get());
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $sucursales = Sucursal::where('activo', true)->orderByDesc('is_central')->orderBy('nombre')->get();

        $resultados = $this->buscar(10);

        $ids = array_unique([...array_keys($this->items), ...$resultados->pluck('id')->all()]);

        // Disponible en el origen, solo como referencia: el control real lo hace el servicio.
        $disponible = $this->origenId
            ? StockSucursal::where('sucursal_id', $this->origenId)->whereIn('product_id', $ids)->pluck('cantidad', 'product_id')
            : collect();

        $productos = Product::whereIn('id', array_keys($this->items))->get()->keyBy('id');

        return view('livewire.sucursales.remito-nuevo', [
            'sucursales' => $sucursales,
            'resultados' => $resultados,
            'disponible' => $disponible,
            'productos' => $productos,
            'totalUnidades' => array_sum(array_map(fn ($c) => max(0, (int) $c), $this->items)),
        ]);
    }
}
