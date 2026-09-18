<?php

namespace App\Livewire\Users\Concerns;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\WithFileUploads;
use Spatie\Permission\Models\Role;

/**
 * Formulario de alta y edición de usuarios, incluidos los que atienden cajas.
 *
 * Cajero: una sola sucursal, PIN obligatorio, sin email ni contraseña obligatorios (no
 * entra al Manager). Supervisor: una o más sucursales, PIN, y sí entra al Manager.
 */
trait DatosDeUsuario
{
    use WithFileUploads;

    /** Lado en píxeles de la foto guardada: sobra para un avatar y pesa decenas de KB. */
    private const LADO_FOTO = 512;

    private const CALIDAD_JPEG_FOTO = 82;

    public string $name = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $foto = null;

    public ?string $fotoActual = null;

    public string $email = '';

    public string $password = '';

    public string $role = '';

    public bool $active = true;

    public string $pin = '';

    /** @var array<int, int|string> */
    public array $sucursales = [];

    public function atiendeCaja(): bool
    {
        return in_array($this->role, User::ROLES_CAJA, true);
    }

    /** El cajero atiende en una sola sucursal: el formulario la elige con un radio. */
    public function elegirSucursal(int $sucursalId): void
    {
        $this->sucursales = [$sucursalId];
    }

    /**
     * @return array<string, mixed>
     */
    protected function reglasDeUsuario(?User $user): array
    {
        $esCajero = $this->role === 'cajero';
        $sinPasswordGuardada = ! $user?->getRawOriginal('password');
        $sinPinGuardado = ! $user?->getRawOriginal('pin_hash');

        return [
            'name' => 'required|string|max:255',
            'email' => [$esCajero ? 'nullable' : 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [(! $esCajero && $sinPasswordGuardada) ? 'required' : 'nullable', 'min:8'],
            'role' => ['required', Rule::exists(Role::class, 'name')->where('guard_name', 'web')],
            'active' => 'boolean',
            'pin' => $this->atiendeCaja()
                ? [$sinPinGuardado ? 'required' : 'nullable', 'digits_between:4,6']
                : ['nullable'],
            'sucursales' => $this->atiendeCaja()
                ? ['required', 'array', 'min:1', ...($esCajero ? ['max:1'] : [])]
                : ['array'],
            'sucursales.*' => ['integer', Rule::exists('sucursales', 'id')],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function mensajesDeUsuario(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede exceder :max caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Debes ingresar un correo electrónico válido.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'role.required' => 'Debes seleccionar un rol.',
            'pin.required' => 'El PIN de caja es obligatorio.',
            'pin.digits_between' => 'El PIN tiene que tener entre 4 y 6 números.',
            'sucursales.required' => 'Elegí al menos una sucursal.',
            'sucursales.min' => 'Elegí al menos una sucursal.',
            'sucursales.max' => 'Un cajero atiende en una sola sucursal.',
            'foto.image' => 'La foto tiene que ser una imagen.',
            'foto.mimes' => 'La foto tiene que ser JPG, PNG o WebP.',
            'foto.max' => 'La foto no puede pesar más de 5 MB.',
            'foto.dimensions' => 'La foto no puede medir más de 8000 píxeles de lado.',
        ];
    }

    protected function guardarUsuario(?User $user): User
    {
        $datos = $this->validate($this->reglasDeUsuario($user), $this->mensajesDeUsuario());

        $atributos = [
            'name' => trim($datos['name']),
            'email' => ($datos['email'] ?? '') !== '' ? $datos['email'] : null,
            'active' => $datos['active'],
        ];

        if (($datos['password'] ?? '') !== '') {
            $atributos['password'] = Hash::make($datos['password']);
        }

        if (! $this->atiendeCaja()) {
            $atributos['pin_hash'] = null;
        } elseif (($datos['pin'] ?? '') !== '') {
            $atributos['pin_hash'] = Hash::make($datos['pin']);
        }

        $fotoAnterior = $user?->foto;

        if ($this->foto) {
            $atributos['foto'] = $this->comprimirYGuardarFoto($this->foto);
        }

        $user ??= new User;
        $user->fill($atributos)->save();
        $user->syncRoles([$datos['role']]);
        $user->sucursales()->sync($this->atiendeCaja() ? array_map('intval', $datos['sucursales']) : []);

        if (isset($atributos['foto']) && $fotoAnterior) {
            Storage::disk('public')->delete($fotoAnterior);
        }

        $this->pin = '';
        $this->password = '';
        $this->foto = null;
        $this->fotoActual = $user->fotoUrl();

        return $user;
    }

    /**
     * Recorta al centro en cuadrado, achica a {@see LADO_FOTO} y guarda como JPEG: una foto
     * de celular de varios MB queda en decenas de KB sin pérdida visible en un avatar.
     */
    private function comprimirYGuardarFoto(UploadedFile $archivo): string
    {
        // GD descomprime la foto entera en RAM (~5 bytes por píxel): una de 48 MP ronda 250 MB.
        ini_set('memory_limit', '512M');

        $origen = imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));

        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $recorte = min($ancho, $alto);
        $lado = min(self::LADO_FOTO, $recorte);

        $destino = imagecreatetruecolor($lado, $lado);
        imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255));
        imagecopyresampled(
            $destino, $origen,
            0, 0, intdiv($ancho - $recorte, 2), intdiv($alto - $recorte, 2),
            $lado, $lado, $recorte, $recorte,
        );
        unset($origen);

        // El recorte centrado no depende del giro, así que se endereza ya achicada.
        $destino = $this->enderezarSegunExif($destino, $archivo);

        ob_start();
        imagejpeg($destino, null, self::CALIDAD_JPEG_FOTO);
        $binario = (string) ob_get_clean();

        $ruta = 'usuarios/fotos/'.Str::uuid().'.jpg';
        Storage::disk('public')->put($ruta, $binario);

        return $ruta;
    }

    /** Los celulares guardan la foto acostada y anotan el giro en el EXIF; GD lo ignora. */
    private function enderezarSegunExif(\GdImage $imagen, UploadedFile $archivo): \GdImage
    {
        if (! in_array($archivo->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $imagen;
        }

        // Un EXIF malformado dispara warnings; en ese caso se deja la foto como vino.
        $orientacion = @exif_read_data($archivo->getRealPath())['Orientation'] ?? 1;

        return match ((int) $orientacion) {
            3 => imagerotate($imagen, 180, 0),
            6 => imagerotate($imagen, -90, 0),
            8 => imagerotate($imagen, 90, 0),
            default => $imagen,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function datosDelFormulario(): array
    {
        return [
            'roles' => Role::where('guard_name', 'web')->orderBy('name')->get(),
            'listaSucursales' => Sucursal::where('activo', true)->orderBy('nombre')->get(),
        ];
    }
}
