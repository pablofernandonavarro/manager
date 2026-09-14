<?php

namespace App\Livewire;

use App\Models\PagoVenta;
use App\Models\Sucursal;
use App\Services\DashboardService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tablero de inicio. Cada bloque se muestra según los permisos del usuario, y se refresca
 * solo cada minuto (las cajas mandan ventas en segundos).
 */
class Dashboard extends Component
{
    #[Url(as: 'sucursal', except: null)]
    public ?int $sucursalId = null;

    #[Layout('layouts.app')]
    public function render(DashboardService $tablero): mixed
    {
        $usuario = auth()->user();
        $hoy = Carbon::now(config('app.display_timezone'))->startOfDay();
        $sucursal = $this->sucursalId ? Sucursal::find($this->sucursalId) : null;
        $sucursalId = $sucursal?->id;
        $verVentas = $usuario->can('reportes.ver');

        return view('livewire.dashboard', [
            'hoy' => $hoy,
            'sucursales' => Sucursal::where('activo', true)->orderByDesc('is_central')->orderBy('nombre')->get(['id', 'nombre']),
            'verVentas' => $verVentas,
            'ventas' => $verVentas ? $tablero->ventas($sucursalId, $hoy) : null,
            'evolucion' => $verVentas ? $tablero->evolucion($sucursalId, $hoy) : [],
            'distribucion' => $verVentas ? $tablero->distribucionDeHoy($sucursalId, $hoy) : null,
            'masVendidos' => $verVentas ? $tablero->masVendidos($sucursalId, $hoy) : [],
            'cajas' => $usuario->can('terminales.ver') || $usuario->can('cajas.ver') ? $tablero->cajas($sucursalId) : null,
            'remitos' => $usuario->can('remitos.ver') ? $tablero->remitosEnTransito($sucursalId) : null,
            'stockCritico' => $usuario->can('productos.ver') ? $tablero->stockCritico($sucursalId) : null,
            'facturacion' => $usuario->can('facturacion.ver') ? $tablero->facturacion() : null,
            'cuentas' => $usuario->can('clientes.gestionar') ? $tablero->cuentasCorrientes() : null,
            'medios' => PagoVenta::MEDIOS + ['sin_detalle' => 'Sin detalle'],
        ]);
    }
}
