<?php

namespace App\Livewire\Promociones;

use App\Models\PromocionBancaria;
use App\Models\Sucursal;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Index extends Component
{
    use AuthorizesRequests;

    public bool $modalAbierto = false;

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $banco = '';

    /** @var array<int, string> */
    public array $medios = [];

    /** @var array<int, string> */
    public array $tarjetas = [];

    /** @var array<int, int|string> */
    public array $diasSemana = [];

    /** @var array<int, int|string> */
    public array $sucursales = [];

    public ?string $vigenciaDesde = null;

    public ?string $vigenciaHasta = null;

    public string $modalidad = 'descuento';

    public string|float|null $porcentaje = null;

    public string|float|null $tope = null;

    public string|float|null $montoMinimo = null;

    public string|int|null $cuotasSinInteres = null;

    public string $observaciones = '';

    public bool $activa = true;

    public function mount(): void
    {
        $this->authorize('promociones.gestionar');
    }

    public function crear(): void
    {
        $this->authorize('promociones.gestionar');

        $this->resetFormulario();
        $this->modalAbierto = true;
    }

    public function editar(int $id): void
    {
        $this->authorize('promociones.gestionar');

        $p = PromocionBancaria::findOrFail($id);

        $this->resetFormulario();
        $this->editandoId = $p->id;
        $this->nombre = $p->nombre;
        $this->banco = (string) $p->banco;
        $this->medios = $p->medios ?? [];
        $this->tarjetas = $p->tarjetas ?? [];
        $this->diasSemana = $p->dias_semana ?? [];
        $this->sucursales = $p->sucursales ?? [];
        $this->vigenciaDesde = $p->vigencia_desde?->toDateString();
        $this->vigenciaHasta = $p->vigencia_hasta?->toDateString();
        $this->modalidad = $p->modalidad;
        $this->porcentaje = $p->porcentaje > 0 ? (float) $p->porcentaje : null;
        $this->tope = $p->tope !== null ? (float) $p->tope : null;
        $this->montoMinimo = $p->monto_minimo !== null ? (float) $p->monto_minimo : null;
        $this->cuotasSinInteres = $p->cuotas_sin_interes;
        $this->observaciones = (string) $p->observaciones;
        $this->activa = $p->activa;
        $this->modalAbierto = true;
    }

    public function guardar(): void
    {
        $this->authorize('promociones.gestionar');

        $datos = $this->validate([
            'nombre' => 'required|string|max:120',
            'banco' => 'nullable|string|max:80',
            'medios' => 'required|array|min:1',
            'medios.*' => Rule::in(array_keys(PromocionBancaria::MEDIOS)),
            'tarjetas' => 'array',
            'tarjetas.*' => Rule::in(array_keys(PromocionBancaria::TARJETAS)),
            'diasSemana' => 'array',
            'diasSemana.*' => 'integer|between:1,7',
            'sucursales' => 'array',
            'sucursales.*' => 'integer|exists:sucursales,id',
            'vigenciaDesde' => 'nullable|date',
            'vigenciaHasta' => 'nullable|date|after_or_equal:vigenciaDesde',
            'modalidad' => 'required|in:descuento,reintegro',
            'porcentaje' => 'nullable|numeric|min:0|max:100',
            'tope' => 'nullable|numeric|min:0',
            'montoMinimo' => 'nullable|numeric|min:0',
            'cuotasSinInteres' => 'nullable|integer|between:2,36',
            'observaciones' => 'nullable|string|max:1000',
            'activa' => 'boolean',
        ], [
            'medios.required' => 'Elegí al menos un medio de pago.',
            'vigenciaHasta.after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.',
        ]);

        // Una promo sin beneficio no hace nada y confunde en la caja.
        if ((float) $datos['porcentaje'] <= 0 && empty($datos['cuotasSinInteres'])) {
            $this->addError('porcentaje', 'Poné un porcentaje o una cantidad de cuotas sin interés.');

            return;
        }

        $vacioANull = fn ($v) => ($v === '' || $v === null || $v === []) ? null : $v;

        PromocionBancaria::updateOrCreate(['id' => $this->editandoId], [
            'nombre' => $datos['nombre'],
            'banco' => $vacioANull(trim((string) $datos['banco'])),
            'medios' => array_values($datos['medios']),
            'tarjetas' => $vacioANull(array_values($datos['tarjetas'])),
            'dias_semana' => $vacioANull(array_map('intval', array_values($datos['diasSemana']))),
            'sucursales' => $vacioANull(array_map('intval', array_values($datos['sucursales']))),
            'vigencia_desde' => $vacioANull($datos['vigenciaDesde']),
            'vigencia_hasta' => $vacioANull($datos['vigenciaHasta']),
            'modalidad' => $datos['modalidad'],
            'porcentaje' => (float) ($datos['porcentaje'] ?? 0),
            'tope' => $vacioANull($datos['tope']),
            'monto_minimo' => $vacioANull($datos['montoMinimo']),
            'cuotas_sin_interes' => $vacioANull($datos['cuotasSinInteres']),
            'observaciones' => $vacioANull(trim((string) $datos['observaciones'])),
            'activa' => $datos['activa'],
        ]);

        $this->modalAbierto = false;
        session()->flash('success', 'Promoción guardada. Las cajas la reciben en el próximo minuto.');
    }

    public function alternarActiva(int $id): void
    {
        $this->authorize('promociones.gestionar');

        $p = PromocionBancaria::findOrFail($id);
        $p->update(['activa' => ! $p->activa]);
    }

    /**
     * Borrar no rompe ventas viejas: el pago conserva el nombre de la promoción y la FK
     * queda en null.
     */
    public function eliminar(int $id): void
    {
        $this->authorize('promociones.gestionar');

        PromocionBancaria::findOrFail($id)->delete();
        session()->flash('success', 'Promoción eliminada.');
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
    }

    private function resetFormulario(): void
    {
        $this->reset([
            'editandoId', 'nombre', 'banco', 'medios', 'tarjetas', 'diasSemana', 'sucursales',
            'vigenciaDesde', 'vigenciaHasta', 'modalidad', 'porcentaje', 'tope', 'montoMinimo',
            'cuotasSinInteres', 'observaciones', 'activa',
        ]);
        $this->resetErrorBag();
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.promociones.index', [
            'promociones' => PromocionBancaria::orderByDesc('activa')->orderBy('nombre')->get(),
            'listaSucursales' => Sucursal::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }
}
