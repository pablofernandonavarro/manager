<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AfipException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SyncVentasRequest;
use App\Jobs\AutorizarComprobante;
use App\Models\Comprobante;
use App\Models\ConfiguracionFiscal;
use App\Services\Facturacion\EmisionComprobantes;
use App\Services\RegistroVentasPos;
use App\Support\Cuit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Facturación vista desde la caja. La caja nunca habla con AFIP: le pide al Manager.
 */
class PosFacturasController extends Controller
{
    /**
     * Datos del emisor para imprimir la factura, y si esta caja puede facturar.
     */
    public function emisor(Request $request): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();
        $config = ConfiguracionFiscal::actual();
        $puntoVenta = $pdv->sucursal?->afip_punto_venta;

        return response()->json([
            'activa' => $config->facturacion_activa && (bool) $puntoVenta,
            'entorno' => $config->entorno,
            'razon_social' => $config->razon_social,
            'cuit' => $config->cuit ? Cuit::formatear($config->cuit) : null,
            'condicion_iva' => $config->condicion_iva,
            'condicion_iva_nombre' => ConfiguracionFiscal::CONDICIONES_IVA[$config->condicion_iva] ?? null,
            'ingresos_brutos' => $config->ingresos_brutos,
            'inicio_actividades' => $config->inicio_actividades?->toDateString(),
            'domicilio' => $config->domicilio_comercial,
            'punto_venta' => $puntoVenta,
        ]);
    }

    /**
     * Registra la venta (igual que sync/ventas) y pide el CAE en el momento, para que el
     * ticket salga como factura. Si AFIP no contesta, la venta queda registrada y la
     * factura pendiente: se autoriza en segundo plano y la caja la consulta después.
     */
    public function facturar(Request $request, RegistroVentasPos $registro, EmisionComprobantes $emision): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        $reglas = collect((new SyncVentasRequest)->rules())
            ->filter(fn ($regla, $campo) => Str::startsWith($campo, 'ventas.*.'))
            ->mapWithKeys(fn ($regla, $campo) => [Str::after($campo, 'ventas.*.') => $regla])
            ->put('factura', 'required|array')
            ->all();

        $datos = $request->validate($reglas);

        $registrada = DB::transaction(fn () => $registro->registrar($pdv, $datos, now()));
        $comprobante = $registrada['comprobante'];

        if ($comprobante?->estado === 'pendiente') {
            try {
                $comprobante = $emision->autorizar($comprobante);
            } catch (AfipException) {
                AutorizarComprobante::dispatch($comprobante);
                $comprobante->refresh();
            }
        }

        return response()->json([
            'venta' => ['uuid' => $registrada['venta']->uuid, 'status' => $registrada['status'], 'venta_id' => $registrada['venta']->id],
            'comprobante' => $comprobante?->paraCaja(ConfiguracionFiscal::actual()),
        ]);
    }

    /**
     * Estado de los comprobantes de ventas y devoluciones de esta caja.
     */
    public function estado(Request $request): JsonResponse
    {
        /** @var \App\Models\PuntoDeVenta $pdv */
        $pdv = $request->user();

        $datos = $request->validate([
            'ventas' => 'array|max:200',
            'ventas.*' => 'uuid',
            'devoluciones' => 'array|max:200',
            'devoluciones.*' => 'uuid',
        ]);

        $emisor = ConfiguracionFiscal::actual();

        $ventas = Comprobante::with(['venta', 'asociado'])
            ->whereHas('venta', fn ($q) => $q->whereIn('uuid', $datos['ventas'] ?? [])->where('punto_de_venta_id', $pdv->id))
            ->get()
            ->mapWithKeys(fn (Comprobante $c) => [$c->venta->uuid => $c->paraCaja($emisor)]);

        $devoluciones = Comprobante::with(['devolucion', 'asociado'])
            ->whereHas('devolucion', fn ($q) => $q->whereIn('uuid', $datos['devoluciones'] ?? [])->where('punto_de_venta_id', $pdv->id))
            ->get()
            ->mapWithKeys(fn (Comprobante $c) => [$c->devolucion->uuid => $c->paraCaja($emisor)]);

        return response()->json([
            'ventas' => (object) $ventas->all(),
            'devoluciones' => (object) $devoluciones->all(),
        ]);
    }
}
