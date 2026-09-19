<?php

namespace App\Livewire\Auditoria;

use App\Enums\ComandoPos as ComandoPosEnum;
use App\Models\ComandoPos;
use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use App\Models\VersionPos;
use App\Support\AppEscritorio;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Vista en vivo del estado de sincronización de cada caja instalada: todo lo que ya
 * informa `EstadoCajaService::reporte()` (POS) y hoy solo se ve resumido en "Puntos de
 * venta". Sin historial propio: `estado_caja` se pisa cada minuto, así que esto es una
 * foto del momento, no un registro a lo largo del tiempo.
 */
class Sincronizacion extends Component
{
    use AuthorizesRequests;

    public ?int $sucursalId = null;

    /** '', 'problemas' (alerta + critico), 'ok', 'sin_datos'. */
    public string $nivel = '';

    /**
     * Igual que en PuntosDeVenta\Index: cada método es un endpoint invocable directamente,
     * así que el permiso se chequea acá y no solo ocultando el botón.
     */
    public function mount(): void
    {
        $this->authorize('terminales.ver');
    }

    public function limpiarFallidos(int $id): void
    {
        $this->authorize('terminales.comandos');

        $pdv = PuntoDeVenta::findOrFail($id);

        ComandoPos::encolar($pdv, ComandoPosEnum::LimpiarFallidos, auth()->id());

        session()->flash('success', "Orden \"Limpiar envíos fallidos\" enviada a {$pdv->nombre}. La caja la ejecuta en menos de un minuto.");
    }

    /** Última versión publicada, por tipo de instalación — mismo cálculo que PuntosDeVenta\Index. */
    private function ultimaVersionPorTipo(): array
    {
        return [
            'escritorio' => AppEscritorio::ultimaVersion(),
            'clasica' => VersionPos::vigente()?->version,
        ];
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $ultimaVersion = $this->ultimaVersionPorTipo();

        $puntosDeVenta = PuntoDeVenta::with(['sucursal', 'ultimoComando'])
            ->withMax('codigosInstalacion as instalado_at', 'usado_at')
            ->when($this->sucursalId, fn ($q) => $q->where('sucursal_id', $this->sucursalId))
            ->orderBy('sucursal_id')
            ->orderBy('nombre')
            ->get()
            // Una caja dada de alta y sin instalar todavía no tiene nada que auditar.
            ->filter(fn ($p) => $p->instalado_at)
            ->values();

        $filas = $puntosDeVenta->map(fn ($pdv) => [
            'pdv' => $pdv,
            'salud' => $pdv->salud($ultimaVersion[$pdv->tipo_instalacion] ?? null),
        ])->filter(function (array $fila) {
            return match ($this->nivel) {
                'problemas' => in_array($fila['salud']['nivel'], ['critico', 'alerta'], true),
                'ok' => $fila['salud']['nivel'] === 'ok',
                'sin_datos' => in_array($fila['salud']['nivel'], ['sin_datos', 'inactiva'], true),
                default => true,
            };
        })->values();

        $todaLaSalud = $puntosDeVenta->map(fn ($pdv) => $pdv->salud($ultimaVersion[$pdv->tipo_instalacion] ?? null));

        // Cada caja informa su estado y consulta órdenes una vez por minuto (pos:comandos):
        // con una orden en camino conviene refrescar más seguido para ver el resultado apenas
        // llega, en vez de esperar a que alguien reabra la pantalla.
        $hayComandoEnCurso = $puntosDeVenta->contains(
            fn ($pdv) => $pdv->ultimoComando && in_array($pdv->ultimoComando->estado, [ComandoPos::PENDIENTE, ComandoPos::TOMADO], true)
        );

        return view('livewire.auditoria.sincronizacion', [
            'filas' => $filas,
            'hayComandoEnCurso' => $hayComandoEnCurso,
            'cajasCriticas' => $todaLaSalud->where('nivel', 'critico')->count(),
            'cajasEnAlerta' => $todaLaSalud->where('nivel', 'alerta')->count(),
            'cajasEnProceso' => $todaLaSalud->where('nivel', 'en_proceso')->count(),
            'cajasOk' => $todaLaSalud->where('nivel', 'ok')->count(),
            'cajasSinDatos' => $todaLaSalud->whereIn('nivel', ['sin_datos', 'inactiva'])->count(),
            'listaSucursales' => Sucursal::orderBy('nombre')->get(),
        ]);
    }
}
