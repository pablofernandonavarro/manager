<?php

namespace App\Console\Commands;

use App\Models\VersionPos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class EmpaquetarPosCommand extends Command
{
    protected $signature = 'pos:empaquetar
        {ruta : Carpeta del proyecto POS a empaquetar}
        {--etiqueta= : Etiqueta de la versión (por defecto, fecha y hora). No se llama --version porque Artisan ya usa ese nombre}
        {--notas= : Qué cambió en esta versión}';

    protected $description = 'Arma el paquete de actualización que van a bajar las cajas';

    /**
     * Qué viaja en el paquete.
     *
     * Se incluye public/build ya compilado: así la caja no necesita Node para actualizarse,
     * y de paso se evita el problema de assets viejos que ya nos mordió dos veces.
     *
     * @var array<int, string>
     */
    private const INCLUIR = [
        'app', 'bootstrap/app.php', 'bootstrap/providers.php', 'config',
        'database/migrations', 'database/factories', 'database/seeders',
        'public', 'resources', 'routes',
        'artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
        '.env.example',
    ];

    /**
     * Scripts sueltos de la raíz (instalador, accesos directos, servicios). Van por patrón
     * porque son varios y se agregan seguido; si no viajan, una actualización no puede
     * corregirlos y quedan congelados en la versión con la que se instaló la caja.
     *
     * @var array<int, string>
     */
    private const PATRONES_RAIZ = ['*.bat', '*.ps1', '*.vbs'];

    /**
     * Nunca se empaquetan: son de cada instalación, no del código.
     *
     * @var array<int, string>
     */
    private const EXCLUIR = [
        '.env', 'database/database.sqlite', 'storage', 'vendor', 'node_modules',
        '.git', '.pos-info', 'bootstrap/cache',
    ];

    public function handle(): int
    {
        $ruta = rtrim($this->argument('ruta'), '\\/');

        if (! is_dir($ruta) || ! file_exists("{$ruta}/artisan")) {
            $this->error("No parece un proyecto Laravel: {$ruta}");

            return self::FAILURE;
        }

        $version = $this->option('etiqueta') ?: now()->format('Y.m.d.Hi');

        if (VersionPos::where('version', $version)->exists()) {
            $this->error("La versión {$version} ya existe. Usá otra etiqueta.");

            return self::FAILURE;
        }

        $this->info("Empaquetando {$ruta} como versión {$version}...");

        $nombreArchivo = "pos-{$version}.zip";
        $rutaDestino = Storage::disk('local')->path("pos-paquetes/{$nombreArchivo}");

        if (! is_dir(dirname($rutaDestino))) {
            mkdir(dirname($rutaDestino), 0755, true);
        }

        $zip = new ZipArchive;

        if ($zip->open($rutaDestino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error('No se pudo crear el zip.');

            return self::FAILURE;
        }

        // La caja lee este archivo para saber qué versión tiene instalada.
        $zip->addFromString('VERSION', $version);

        $agregados = 0;

        foreach (self::INCLUIR as $item) {
            $origen = "{$ruta}/{$item}";

            if (is_file($origen)) {
                $zip->addFile($origen, $item);
                $agregados++;

                continue;
            }

            if (is_dir($origen)) {
                $agregados += $this->agregarCarpeta($zip, $ruta, $item);
            }
        }

        foreach (self::PATRONES_RAIZ as $patron) {
            foreach (glob("{$ruta}/{$patron}") ?: [] as $encontrado) {
                $nombre = basename($encontrado);

                if ($this->estaExcluido($nombre)) {
                    continue;
                }

                // Se fuerza CRLF: cmd.exe con saltos LF ejecuta la primera linea y despues
                // se comporta de forma erratica — los "set /p" no llegan a preguntar nada.
                // Cualquier editor que guarde en LF vuelve a romper el instalador, asi que
                // se normaliza al empaquetar en vez de confiar en el origen.
                $zip->addFromString($nombre, $this->conCrlf(file_get_contents($encontrado)));
                $agregados++;
            }
        }

        $zip->close();

        $hash = hash_file('sha256', $rutaDestino);
        $tamano = filesize($rutaDestino);

        $registro = VersionPos::create([
            'version' => $version,
            'archivo' => "pos-paquetes/{$nombreArchivo}",
            'hash' => $hash,
            'tamano' => $tamano,
            'notas' => $this->option('notas'),
            'user_id' => null,
        ]);

        $registro->marcarVigente();

        $this->info('✅ Paquete creado y marcado como vigente');
        $this->line("   Versión : {$version}");
        $this->line("   Archivos: {$agregados}");
        $this->line('   Tamaño  : '.number_format($tamano / 1024 / 1024, 1).' MB');
        $this->line("   SHA256  : {$hash}");
        $this->line('');
        $this->line('Las cajas lo van a bajar cuando reciban la orden "Actualizar el POS".');

        return self::SUCCESS;
    }

    private function agregarCarpeta(ZipArchive $zip, string $base, string $relativa): int
    {
        $agregados = 0;

        $iterador = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator("{$base}/{$relativa}", \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterador as $archivo) {
            $rutaRelativa = str_replace('\\', '/', substr($archivo->getPathname(), strlen($base) + 1));

            if ($this->estaExcluido($rutaRelativa)) {
                continue;
            }

            if ($archivo->isFile()) {
                $zip->addFile($archivo->getPathname(), $rutaRelativa);
                $agregados++;
            }
        }

        return $agregados;
    }

    /**
     * Normaliza a LF primero para no generar CR CR LF en los que ya estaban bien.
     */
    private function conCrlf(string $contenido): string
    {
        return str_replace("\n", "\r\n", str_replace("\r\n", "\n", $contenido));
    }

    private function estaExcluido(string $rutaRelativa): bool
    {
        foreach (self::EXCLUIR as $excluido) {
            if ($rutaRelativa === $excluido || str_starts_with($rutaRelativa, $excluido.'/')) {
                return true;
            }
        }

        return false;
    }
}
