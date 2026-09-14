<?php

namespace App\Livewire\Facturacion;

use App\Exceptions\AfipException;
use App\Models\ConfiguracionFiscal;
use App\Models\Sucursal;
use App\Services\Afip\Certificados;
use App\Services\Afip\Wsfe;
use App\Support\Cuit;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Configuración de la facturación electrónica: datos del emisor, certificado digital de
 * AFIP, punto de venta de cada sucursal y prueba de conexión.
 */
class Configuracion extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    // Datos del emisor
    public string $razonSocial = '';

    public string $cuit = '';

    public string $condicionIva = '';

    public string $ingresosBrutos = '';

    public string $inicioActividades = '';

    public string $domicilioComercial = '';

    // Entorno
    public string $entorno = 'homologacion';

    // Certificado
    public string $alias = 'manager-pos';

    public $archivoCertificado;

    public $archivoClave;

    /** @var array<int|string, string> sucursal_id => punto de venta */
    public array $puntosVenta = [];

    public ?string $mensaje = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->authorize('facturacion.configurar');

        $c = ConfiguracionFiscal::actual();

        $this->razonSocial = (string) $c->razon_social;
        $this->cuit = $c->cuit ? Cuit::formatear($c->cuit) : '';
        $this->condicionIva = (string) $c->condicion_iva;
        $this->ingresosBrutos = (string) $c->ingresos_brutos;
        $this->inicioActividades = (string) $c->inicio_actividades?->toDateString();
        $this->domicilioComercial = (string) $c->domicilio_comercial;
        $this->entorno = $c->entorno;
        $this->alias = $c->certificado_alias ?: 'manager-pos';
        $this->puntosVenta = Sucursal::orderBy('nombre')->pluck('afip_punto_venta', 'id')->map(fn ($pv) => $pv ? (string) $pv : '')->all();
    }

    public function guardarDatos(): void
    {
        $this->authorize('facturacion.configurar');
        $this->limpiar();

        $datos = $this->validate([
            'razonSocial' => 'required|string|max:150',
            'cuit' => ['required', fn ($a, $v, $fail) => Cuit::valido($v) ? null : $fail('El CUIT no es válido (revisá el dígito verificador).')],
            'condicionIva' => ['required', Rule::in(array_keys(ConfiguracionFiscal::CONDICIONES_IVA))],
            'ingresosBrutos' => 'nullable|string|max:30',
            'inicioActividades' => 'required|date|before_or_equal:today',
            'domicilioComercial' => 'required|string|max:200',
            'entorno' => 'required|in:homologacion,produccion',
        ], [
            'razonSocial.required' => 'Cargá la razón social.',
            'cuit.required' => 'Cargá el CUIT.',
            'condicionIva.required' => 'Elegí la condición frente al IVA.',
            'inicioActividades.required' => 'Cargá la fecha de inicio de actividades.',
            'domicilioComercial.required' => 'Cargá el domicilio comercial.',
        ]);

        $config = ConfiguracionFiscal::actual();
        $cuitNuevo = Cuit::normalizar($datos['cuit']);

        // Cambiar CUIT o entorno invalida el ticket de acceso (es de otro CUIT/servidor).
        $invalidaTicket = $config->cuit !== $cuitNuevo || $config->entorno !== $datos['entorno'];

        $config->update([
            'razon_social' => trim($datos['razonSocial']),
            'cuit' => $cuitNuevo,
            'condicion_iva' => $datos['condicionIva'],
            'ingresos_brutos' => trim((string) $datos['ingresosBrutos']) ?: null,
            'inicio_actividades' => $datos['inicioActividades'],
            'domicilio_comercial' => trim($datos['domicilioComercial']),
            'entorno' => $datos['entorno'],
            ...($invalidaTicket ? ['ta_token' => null, 'ta_sign' => null, 'ta_expira' => null, 'facturacion_activa' => false] : []),
        ]);

        $this->cuit = Cuit::formatear($cuitNuevo);
        $this->mensaje = 'Datos fiscales guardados.'.($invalidaTicket && $config->tieneCertificado() ? ' Cambió el CUIT o el entorno: probá la conexión de nuevo.' : '');
    }

    public function generarPedido(Certificados $certificados): void
    {
        $this->authorize('facturacion.configurar');
        $this->limpiar();

        $config = ConfiguracionFiscal::actual();

        if (! $config->cuit || ! $config->razon_social) {
            $this->error = 'Guardá primero la razón social y el CUIT.';

            return;
        }

        try {
            $generado = $certificados->generarClaveYCsr($config->cuit, $config->razon_social, $this->alias);
        } catch (AfipException $e) {
            $this->error = $e->getMessage();

            return;
        }

        // Una clave nueva deja inservible el certificado anterior.
        $config->update([
            'clave_privada' => $generado['clave'],
            'csr' => $generado['csr'],
            'certificado' => null,
            'certificado_alias' => $this->alias,
            'certificado_emisor' => null,
            'certificado_vence' => null,
            'ta_token' => null, 'ta_sign' => null, 'ta_expira' => null,
            'facturacion_activa' => false,
        ]);

        $this->mensaje = 'Clave y pedido de certificado generados. Descargá el CSR y subilo en AFIP.';
    }

    public function descargarCsr()
    {
        $this->authorize('facturacion.configurar');

        $config = ConfiguracionFiscal::actual();

        if (! $config->csr) {
            return null;
        }

        return response()->streamDownload(fn () => print ($config->csr), "pedido-afip-{$config->cuit}.csr", ['Content-Type' => 'application/pkcs10']);
    }

    public function subirCertificado(Certificados $certificados): void
    {
        $this->authorize('facturacion.configurar');
        $this->limpiar();

        $this->validate([
            'archivoCertificado' => 'required|file|max:20',
            'archivoClave' => 'nullable|file|max:20',
        ], ['archivoCertificado.required' => 'Elegí el archivo .crt que descargaste de AFIP.']);

        $config = ConfiguracionFiscal::actual();

        if (! $config->cuit) {
            $this->error = 'Guardá primero el CUIT.';

            return;
        }

        // Clave propia opcional: para quien ya tenía certificado generado por fuera.
        $clave = $this->archivoClave ? file_get_contents($this->archivoClave->getRealPath()) : $config->clave_privada;

        if (! $clave || ! $certificados->verificarClave($clave)) {
            $this->error = $this->archivoClave
                ? 'El archivo de clave privada no es válido.'
                : 'No hay clave privada: generá el pedido de certificado acá o subí tu clave junto con el certificado.';

            return;
        }

        try {
            $datos = $certificados->leer(file_get_contents($this->archivoCertificado->getRealPath()), $clave, $config->cuit);
        } catch (AfipException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $config->update([
            'clave_privada' => $clave,
            'certificado' => $datos['pem'],
            'certificado_alias' => $datos['alias'],
            'certificado_emisor' => $datos['emisor'],
            'certificado_vence' => $datos['vence'],
            'ta_token' => null, 'ta_sign' => null, 'ta_expira' => null,
        ]);

        $this->reset(['archivoCertificado', 'archivoClave']);
        $this->mensaje = 'Certificado cargado. Vence el '.$datos['vence']->timezone(config('app.display_timezone'))->format('d/m/Y').'.';

        if ($datos['homologacion'] !== ($config->entorno === 'homologacion')) {
            $this->error = 'Atención: el certificado parece de '.($datos['homologacion'] ? 'homologación' : 'producción')
                .' pero el entorno elegido es '.($config->entorno === 'homologacion' ? 'homologación' : 'producción').'.';
        }
    }

    public function guardarPuntosVenta(): void
    {
        $this->authorize('facturacion.configurar');
        $this->limpiar();

        $this->validate([
            'puntosVenta' => 'array',
            'puntosVenta.*' => 'nullable|integer|between:1,99998',
        ], ['puntosVenta.*.between' => 'El punto de venta va de 1 a 99998.', 'puntosVenta.*.integer' => 'El punto de venta es un número.']);

        $numeros = collect($this->puntosVenta)->filter(fn ($pv) => $pv !== '' && $pv !== null)->map(fn ($pv) => (int) $pv);

        if ($numeros->duplicates()->isNotEmpty()) {
            $this->error = 'Dos sucursales no pueden usar el mismo punto de venta: la numeración de comprobantes se mezclaría.';

            return;
        }

        DB::transaction(function () {
            // Primero se liberan todos, así intercambiar puntos entre sucursales no choca con el índice único.
            Sucursal::query()->update(['afip_punto_venta' => null]);

            foreach ($this->puntosVenta as $sucursalId => $pv) {
                if ($pv !== '' && $pv !== null) {
                    Sucursal::whereKey($sucursalId)->update(['afip_punto_venta' => (int) $pv]);
                }
            }
        });

        $this->mensaje = 'Puntos de venta guardados.';
    }

    public function probarConexion(Wsfe $wsfe): void
    {
        $this->authorize('facturacion.configurar');
        $this->limpiar();

        $config = ConfiguracionFiscal::actual();
        $pasos = [];
        $ok = true;

        $paso = function (string $titulo, callable $accion) use (&$pasos, &$ok) {
            if (! $ok) {
                $pasos[] = ['titulo' => $titulo, 'ok' => null, 'detalle' => 'No se probó (falló un paso anterior).'];

                return;
            }

            try {
                $pasos[] = ['titulo' => $titulo, 'ok' => true, 'detalle' => $accion()];
            } catch (AfipException $e) {
                $ok = false;
                $pasos[] = ['titulo' => $titulo, 'ok' => false, 'detalle' => $e->getMessage()];
            }
        };

        $paso('Servidores de AFIP', function () use ($wsfe, $config) {
            $estado = $wsfe->dummy($config);

            if (collect($estado)->contains(fn ($v) => $v !== 'OK')) {
                throw new AfipException('AFIP informa problemas: aplicación '.$estado['app'].', base '.$estado['db'].', autenticación '.$estado['auth'].'.');
            }

            return 'Funcionando';
        });

        $paso('Autenticación con el certificado', function () use ($config) {
            $ticket = app(\App\Services\Afip\Wsaa::class)->ticket($config);

            return 'Ticket de acceso vigente hasta '.$ticket['expira']->timezone(config('app.display_timezone'))->format('d/m/Y H:i');
        });

        $paso('Puntos de venta habilitados en AFIP', function () use ($wsfe, $config) {
            $habilitados = collect($wsfe->puntosDeVenta($config))->filter(fn ($p) => ! $p['bloqueado'] && $p['baja'] === null);
            $configurados = Sucursal::whereNotNull('afip_punto_venta')->pluck('afip_punto_venta', 'nombre');
            $faltan = $configurados->reject(fn ($pv) => $habilitados->contains('numero', $pv));

            if ($config->entorno === 'produccion' && $faltan->isNotEmpty()) {
                throw new AfipException('Estos puntos de venta no están dados de alta para web services en AFIP: '
                    .$faltan->map(fn ($pv, $suc) => "{$pv} ({$suc})")->implode(', ').'.');
            }

            return $habilitados->isEmpty()
                ? 'AFIP no lista puntos de venta (normal en homologación).'
                : 'Habilitados: '.$habilitados->pluck('numero')->implode(', ');
        });

        $paso('Último comprobante por sucursal', function () use ($wsfe, $config) {
            $tipo = $config->condicion_iva === 'responsable_inscripto' ? 6 : 11; // Factura B o C
            $detalle = [];

            foreach (Sucursal::whereNotNull('afip_punto_venta')->orderBy('nombre')->get() as $sucursal) {
                $ultimo = $wsfe->ultimoAutorizado($config, $sucursal->afip_punto_venta, $tipo);
                $detalle[] = "{$sucursal->nombre} (PV {$sucursal->afip_punto_venta}): Nº {$ultimo}";
            }

            return $detalle === [] ? 'No hay sucursales con punto de venta.' : implode(' · ', $detalle);
        });

        $config->update(['ultima_prueba_at' => now(), 'ultima_prueba_ok' => $ok, 'ultima_prueba_detalle' => $pasos]);

        $ok ? $this->mensaje = 'La conexión con AFIP funciona.' : $this->error = 'La prueba de conexión falló. Mirá el detalle abajo.';
    }

    public function alternarFacturacion(): void
    {
        $this->authorize('facturacion.configurar');
        $this->limpiar();

        $config = ConfiguracionFiscal::actual();

        if (! $config->facturacion_activa) {
            if ($faltan = $config->faltantes()) {
                $this->error = 'Para activar la facturación falta: '.implode(', ', $faltan).'.';

                return;
            }

            if (! $config->ultima_prueba_ok) {
                $this->error = 'Probá la conexión con AFIP y que salga bien antes de activar.';

                return;
            }
        }

        $config->update(['facturacion_activa' => ! $config->facturacion_activa]);
        $this->mensaje = $config->facturacion_activa ? 'Facturación electrónica activada.' : 'Facturación electrónica desactivada.';
    }

    private function limpiar(): void
    {
        $this->mensaje = null;
        $this->error = null;
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.facturacion.configuracion', [
            'config' => ConfiguracionFiscal::actual(),
            'sucursales' => Sucursal::orderBy('nombre')->get(['id', 'nombre', 'afip_punto_venta', 'activo']),
        ]);
    }
}
