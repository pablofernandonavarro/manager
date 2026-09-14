<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class GenerarKitPosCommand extends Command
{
    protected $signature = 'pos:kit {ruta : Carpeta de un POS ya instalado del que sacar el kit}';

    protected $description = 'Arma el instalador descargable del POS (incluye dependencias)';

    /**
     * Lo que identifica a la instalación de origen. Si algo de esto viaja, la máquina
     * que instale el kit arranca creyendo que ES esa caja y sincroniza con su token.
     *
     * @var array<int, string>
     */
    private const PROHIBIDO = [
        '.env', '.env.escritorio', '.pos-info', 'database/database.sqlite',
        '.git', 'storage/logs', 'storage/app', 'storage/framework', 'bootstrap/cache',
    ];

    public function handle(): int
    {
        $ruta = rtrim($this->argument('ruta'), '\\/');

        if (! is_dir($ruta) || ! file_exists("{$ruta}/artisan")) {
            $this->error("No parece un proyecto POS: {$ruta}");

            return self::FAILURE;
        }

        if (! is_dir("{$ruta}/vendor") || ! is_dir("{$ruta}/node_modules")) {
            $this->error('El origen tiene que tener vendor/ y node_modules/: el kit se distribuye con las dependencias adentro.');

            return self::FAILURE;
        }

        $version = file_exists("{$ruta}/VERSION") ? trim(File::get("{$ruta}/VERSION")) : 'sin-version';

        $destino = Storage::disk('local')->path('pos-kit/instalador-pos.zip');
        File::ensureDirectoryExists(dirname($destino));

        $this->info("Armando el kit desde {$ruta} (versión {$version})...");
        $this->warn('Son ~110 MB de archivos, puede tardar unos minutos.');

        $zip = new ZipArchive;

        if ($zip->open($destino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error('No se pudo crear el zip.');

            return self::FAILURE;
        }

        $agregados = 0;
        $omitidos = 0;

        $iterador = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($ruta, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterador as $archivo) {
            $relativa = str_replace('\\', '/', substr($archivo->getPathname(), strlen($ruta) + 1));

            if ($this->estaProhibido($relativa)) {
                $omitidos++;

                continue;
            }

            if ($archivo->isFile()) {
                // Los scripts de Windows se normalizan a CRLF: con saltos LF, cmd.exe
                // ejecuta la primera linea y despues se comporta de forma erratica, y el
                // instalador no llega ni a preguntar el nombre de la caja.
                if (in_array(strtolower($archivo->getExtension()), ['bat', 'cmd', 'vbs'], true)) {
                    $contenido = file_get_contents($archivo->getPathname());
                    $zip->addFromString($relativa, str_replace("\n", "\r\n", str_replace("\r\n", "\n", $contenido)));
                } else {
                    $zip->addFile($archivo->getPathname(), $relativa);
                }

                $agregados++;
            }
        }

        // Laravel necesita estas carpetas; van vacías.
        foreach (['storage/app', 'storage/framework/cache/data', 'storage/framework/sessions',
            'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $carpeta) {
            $zip->addFromString("{$carpeta}/.gitignore", "*\n!.gitignore\n");
        }

        $zip->close();

        $tamano = filesize($destino);

        File::put(
            Storage::disk('local')->path('pos-kit/instalador-pos.json'),
            json_encode([
                'version' => $version,
                'tamano' => $tamano,
                'archivos' => $agregados,
                'generado_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT)
        );

        $this->info('✅ Kit listo');
        $this->line("   Versión : {$version}");
        $this->line("   Archivos: {$agregados} (se omitieron {$omitidos} de la instalación de origen)");
        $this->line('   Tamaño  : '.number_format($tamano / 1024 / 1024, 1).' MB');
        $this->line('');
        $this->line('Ya se puede descargar desde Puntos de venta → Descargar instalador.');

        return self::SUCCESS;
    }

    private function estaProhibido(string $relativa): bool
    {
        foreach (self::PROHIBIDO as $p) {
            if ($relativa === $p || str_starts_with($relativa, $p.'/')) {
                return true;
            }
        }

        // Residuos de operación de la caja de origen.
        return str_starts_with($relativa, 'iniciar-')
            || str_contains($relativa, '/respaldo-')
            || str_contains($relativa, '/actualizacion-');
    }
}
