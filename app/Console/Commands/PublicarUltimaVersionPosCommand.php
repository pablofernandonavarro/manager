<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;

class PublicarUltimaVersionPosCommand extends Command
{
    protected $signature = 'pos:publicar-ultima-version {--notas= : Qué cambió en esta versión}';

    protected $description = 'Clona la última versión del POS desde GitHub, la compila y la empaqueta para las cajas';

    /**
     * URL pública del repo: no requiere credenciales para clonar, solo lectura.
     */
    private const REPO = 'https://github.com/pablofernandonavarro/pos.git';

    public function handle(): int
    {
        $temporal = storage_path('app/temp/pos-publicar-'.uniqid());

        try {
            $this->info('Clonando el repo del POS...');

            // --depth 1: no hace falta el historial, solo el código de la última versión de main.
            $clon = Process::timeout(120)->run(['git', 'clone', '--depth', '1', self::REPO, $temporal]);

            if (! $clon->successful()) {
                $this->error('No se pudo clonar el repo: '.$clon->errorOutput());

                return self::FAILURE;
            }

            $this->info('Instalando dependencias de Composer...');

            $composer = Process::path($temporal)->timeout(180)->run([
                'composer', 'install', '--no-dev', '--no-interaction', '--prefer-dist', '--optimize-autoloader',
            ]);

            if (! $composer->successful()) {
                $this->error('composer install falló: '.$composer->errorOutput());

                return self::FAILURE;
            }

            $this->info('Compilando assets (npm ci && npm run build)...');

            // Un solo Process con ambos comandos: npm ci deja node_modules listo para el build.
            $npm = Process::path($temporal)->timeout(180)->run('npm ci && npm run build');

            if (! $npm->successful()) {
                $this->error('npm build falló: '.$npm->errorOutput());

                return self::FAILURE;
            }

            $this->info('Empaquetando...');

            $codigo = Artisan::call('pos:empaquetar', array_filter([
                'ruta' => $temporal,
                '--notas' => $this->option('notas') ?: 'Publicado desde el admin',
            ]));

            $this->output->write(Artisan::output());

            return $codigo === 0 ? self::SUCCESS : self::FAILURE;
        } finally {
            $this->limpiar($temporal);
        }
    }

    /**
     * git dentro de .git deja archivos de solo lectura en Windows: File::deleteDirectory()
     * (unlink de PHP) falla en esos. Se limpia con el comando nativo de cada sistema.
     */
    private function limpiar(string $ruta): void
    {
        if (! is_dir($ruta)) {
            return;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            Process::run(['powershell', '-NoProfile', '-Command', "Remove-Item -LiteralPath '{$ruta}' -Recurse -Force"]);

            return;
        }

        Process::run(['rm', '-rf', $ruta]);
    }
}
