<?php

namespace App\Livewire\PuntosDeVenta;

use App\Enums\ComandoPos as ComandoPosEnum;
use App\Jobs\PublicarUltimaVersionPosJob;
use App\Models\CodigoInstalacion;
use App\Models\ComandoPos;
use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use App\Models\VersionPos;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Index extends Component
{
    use AuthorizesRequests;

    public ?int $sucursalId = null;

    public string $nombre = '';

    /**
     * Los permisos se chequean acá y no solo ocultando botones: cada método es un endpoint
     * que un usuario autenticado puede invocar directamente con un request armado a mano.
     * Esconder el botón no protege nada.
     */
    public function mount(): void
    {
        $this->authorize('terminales.ver');
    }

    public function save(): void
    {
        $this->authorize('terminales.instalar');

        $this->validate([
            'sucursalId' => 'required|integer|exists:sucursales,id',
            'nombre' => 'required|string|max:100',
        ]);

        $pdv = PuntoDeVenta::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => $this->nombre,
            // Se pisa al canjear el código de instalación. Nunca se muestra este valor:
            // el secret real lo genera el canje y viaja directo a la caja.
            'secret' => Hash::make(Str::random(40)),
            'activo' => true,
        ]);

        $this->sucursalId = null;
        $this->nombre = '';

        // Un POS recién creado siempre necesita instalarse en alguna máquina.
        $this->generarCodigoInstalacion($pdv->id);
    }

    /**
     * Genera el código que el técnico tipea en la máquina destino. Sirve igual para
     * un alta nueva o para reinstalar una caja rota: al canjearse, el Manager rota
     * el secret y revoca los tokens anteriores.
     */
    public function generarCodigoInstalacion(int $id): void
    {
        // Un código de instalación rota el secret de la caja: quien lo tiene, se la queda.
        $this->authorize('terminales.instalar');

        $pdv = PuntoDeVenta::findOrFail($id);

        $codigo = CodigoInstalacion::generarPara($pdv, auth()->id());

        $this->dispatch(
            'codigo-instalacion-generado',
            codigo: $codigo->codigo,
            nombre: $pdv->nombre,
            expira: $codigo->expira_at->format('d/m/Y H:i'),
        );
    }

    public function regenerarSecret(int $id): void
    {
        $this->authorize('terminales.instalar');

        $pdv = PuntoDeVenta::findOrFail($id);
        $secret = Str::random(40);
        $pdv->update(['secret' => Hash::make($secret)]);

        $this->dispatch('secret-generado', secret: $secret, nombre: $pdv->nombre, pdvId: $pdv->id);
    }

    /**
     * Deja una orden encolada para que la caja la ejecute cuando la consulte (cada minuto).
     * No es inmediato a propósito: la caja tira, el Manager no empuja.
     */
    public function enviarComando(int $id, string $comando): void
    {
        // "Actualizar" hace que la caja baje y ejecute código. No es una acción cualquiera.
        $this->authorize('terminales.comandos');

        $enum = ComandoPosEnum::tryFrom($comando);

        if (! $enum) {
            return;
        }

        $pdv = PuntoDeVenta::findOrFail($id);

        ComandoPos::encolar($pdv, $enum, auth()->id());

        session()->flash('success', "Orden \"{$enum->label()}\" enviada a {$pdv->nombre}. La caja la ejecuta en menos de un minuto.");
    }

    /**
     * Clona el código del POS desde GitHub, lo compila y arma el paquete que las cajas
     * bajan con "Actualizar el POS" — sin tocar la consola ni SSH.
     *
     * Corre en cola (clonar + composer + npm build tarda uno o dos minutos) y el estado
     * se sigue por polling desde la vista, leyendo la misma cache que actualiza el Job.
     */
    public function publicarUltimaVersionPos(): void
    {
        $this->authorize('terminales.instalar');

        $estado = Cache::get(PublicarUltimaVersionPosJob::CACHE_KEY);

        if ($estado && $estado['estado'] === 'procesando') {
            return;
        }

        Cache::put(PublicarUltimaVersionPosJob::CACHE_KEY, ['estado' => 'procesando', 'mensaje' => null], now()->addMinutes(10));

        PublicarUltimaVersionPosJob::dispatch();
    }

    /**
     * @return array{estado: string, mensaje: ?string}|null
     */
    public function estadoPublicacionPos(): ?array
    {
        return Cache::get(PublicarUltimaVersionPosJob::CACHE_KEY);
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('terminales.instalar');

        $pdv = PuntoDeVenta::findOrFail($id);
        $pdv->update(['activo' => ! $pdv->activo]);
    }

    public function revocarTokens(int $id): void
    {
        $this->authorize('terminales.instalar');

        PuntoDeVenta::findOrFail($id)->tokens()->delete();
    }

    public function delete(int $id): void
    {
        $this->authorize('terminales.instalar');

        $pdv = PuntoDeVenta::findOrFail($id);
        $pdv->tokens()->delete();
        $pdv->delete();
    }

    /**
     * Datos del instalador descargable, o null si todavía no se generó.
     *
     * @return array{version: string, tamano: int, generado_at: string}|null
     */
    private function infoDelKit(): ?array
    {
        $meta = Storage::disk('local')->path('pos-kit/instalador-pos.json');
        $zip = Storage::disk('local')->path('pos-kit/instalador-pos.zip');

        if (! file_exists($meta) || ! file_exists($zip)) {
            return null;
        }

        return json_decode(file_get_contents($meta), true);
    }

    /**
     * Datos de la app de escritorio publicada, o null si todavía no se publicó.
     *
     * @return array{version: string, formato: string, tamano: int, sha256: string, generado_at: string}|null
     */
    private function infoDelEscritorio(): ?array
    {
        $carpeta = Storage::disk('local')->path('pos-escritorio');
        $meta = json_decode(@file_get_contents("{$carpeta}/pos-escritorio.json") ?: 'null', true);

        if (! $meta || ! file_exists("{$carpeta}/pos-escritorio.{$meta['formato']}")) {
            return null;
        }

        return $meta;
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        $escritorio = $this->infoDelEscritorio();
        $ultimaVersion = [
            'escritorio' => $escritorio['version'] ?? null,
            'clasica' => VersionPos::vigente()?->version,
        ];

        $puntosDeVenta = PuntoDeVenta::with(['sucursal', 'ultimoComando'])
            // Para mostrar si la caja ya se instaló alguna vez y si tiene un código
            // sin usar. Sin esto no hay forma de saber desde el Manager en qué estado
            // quedó cada terminal.
            ->withMax('codigosInstalacion as instalado_at', 'usado_at')
            ->withCount([
                'codigosInstalacion as codigos_pendientes' => fn ($q) => $q->utilizables(),
            ])
            ->orderBy('sucursal_id')
            ->orderBy('nombre')
            ->get();

        // Solo las instaladas: una caja dada de alta y sin instalar no es un problema.
        $salud = $puntosDeVenta->filter(fn ($p) => $p->instalado_at)
            ->mapWithKeys(fn ($p) => [$p->id => $p->salud($ultimaVersion[$p->tipo_instalacion] ?? null)]);

        return view('livewire.puntos-de-venta.index', [
            'salud' => $salud,
            'cajasConProblemas' => $salud->filter(fn ($s) => in_array($s['nivel'], ['critico', 'alerta'], true))->count(),
            'cajasCriticas' => $salud->where('nivel', 'critico')->count(),
            'comandosDisponibles' => ComandoPosEnum::cases(),
            'kit' => $this->infoDelKit(),
            'escritorio' => $escritorio,
            'publicacionPos' => $this->estadoPublicacionPos(),
            // Contra qué se compara la versión que informa cada caja, según cómo esté instalada.
            'ultimaVersion' => $ultimaVersion,
            // Lo que la caja tiene que poder abrir. manager.test solo existe en esta PC:
            // en producción es la dirección pública o de red del Manager.
            'urlManager' => url('/'),
            'puntosDeVenta' => $puntosDeVenta,
            'sucursales' => Sucursal::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }
}
