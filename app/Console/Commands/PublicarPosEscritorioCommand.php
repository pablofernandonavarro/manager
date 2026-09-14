<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class PublicarPosEscritorioCommand extends Command
{
    protected $signature = 'pos:publicar-escritorio
        {ruta : Carpeta win-unpacked de la compilación NativePHP, o el Setup .exe}
        {--etiqueta= : Versión a mostrar (por defecto NATIVEPHP_APP_VERSION de la compilación)}';

    protected $description = 'Publica la app de escritorio del POS para descargarla desde Puntos de venta';

    /**
     * Archivos que, si aparecen en la compilación, significan que se coló la identidad o
     * los datos de una caja. En la app de escritorio los datos viven en %APPDATA%, así
     * que una compilación sana nunca los trae.
     */
    private const PROHIBIDO = ['.sqlite', '.sqlite-wal', '.sqlite-shm', '.pos-info'];

    public function handle(): int
    {
        $ruta = rtrim($this->argument('ruta'), '\\/');
        $carpeta = Storage::disk('local')->path('pos-escritorio');
        File::ensureDirectoryExists($carpeta);

        if (is_file($ruta) && str_ends_with(strtolower($ruta), '.exe')) {
            $formato = 'exe';
            $version = $this->option('etiqueta') ?: 'sin-version';
            $destino = "{$carpeta}/pos-escritorio.exe";

            $this->info('Copiando el instalador...');
            File::copy($ruta, $destino);
        } elseif (is_dir($ruta)) {
            $app = "{$ruta}/resources/build/app";

            if (! file_exists("{$app}/artisan") || ! glob("{$ruta}/*.exe")) {
                $this->error("No parece una compilación de NativePHP (falta el .exe o resources/build/app): {$ruta}");

                return self::FAILURE;
            }

            $formato = 'zip';
            $version = $this->option('etiqueta') ?: $this->versionDeLaCompilacion($app);
            $destino = "{$carpeta}/pos-escritorio.zip";

            $this->info("Comprimiendo {$ruta} (versión {$version})...");
            $this->warn('Son ~430 MB, puede tardar unos minutos.');

            if (! $this->comprimir($ruta, $destino)) {
                return self::FAILURE;
            }
        } else {
            $this->error('Pasá la carpeta win-unpacked o un Setup .exe.');

            return self::FAILURE;
        }

        // Queda publicado un solo formato: el viejo se borra para no ofrecer dos versiones.
        File::delete("{$carpeta}/pos-escritorio.".($formato === 'zip' ? 'exe' : 'zip'));

        File::put("{$carpeta}/pos-escritorio.json", json_encode([
            'version' => $version,
            'formato' => $formato,
            'tamano' => filesize($destino),
            'sha256' => hash_file('sha256', $destino),
            'generado_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));

        $this->info('✅ Publicado');
        $this->line("   Versión: {$version} ({$formato})");
        $this->line('   Tamaño : '.number_format(filesize($destino) / 1024 / 1024, 1).' MB');
        $this->line('Se descarga desde Puntos de venta → Cómo instalar un POS → Descargar POS de escritorio.');

        return self::SUCCESS;
    }

    private function comprimir(string $origen, string $destino): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($destino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error('No se pudo crear el zip.');

            return false;
        }

        // Todo adentro de una carpeta, para que "Extraer todo" no desparrame 80 archivos
        // sueltos en Descargas. No se llama POS: esa es la carpeta de la instalación
        // clásica, y una caja que se pasa a la app de escritorio mezclaría las dos.
        $raiz = 'POS-Escritorio';

        $iterador = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($origen, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterador as $archivo) {
            $relativa = str_replace('\\', '/', substr($archivo->getPathname(), strlen($origen) + 1));

            foreach (self::PROHIBIDO as $sufijo) {
                if (str_ends_with(strtolower($relativa), $sufijo)) {
                    $zip->close();
                    File::delete($destino);
                    $this->error("La compilación trae datos de una caja ({$relativa}). No se publica.");

                    return false;
                }
            }

            $zip->addFile($archivo->getPathname(), "{$raiz}/{$relativa}");
        }

        $zip->close();

        return true;
    }

    private function versionDeLaCompilacion(string $app): string
    {
        $env = @file_get_contents("{$app}/.env") ?: '';

        return preg_match('/^NATIVEPHP_APP_VERSION=(.+)$/m', $env, $m) ? trim($m[1]) : 'sin-version';
    }
}
