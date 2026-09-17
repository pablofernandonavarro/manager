<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class CompilarPosEscritorioCommand extends Command
{
    protected $signature = 'pos:compilar-escritorio {--version= : Versión a compilar (ej: 1.6.6)}';

    protected $description = 'Compila la app de escritorio NativePHP del POS';

    public function handle(): int
    {
        $posPath = base_path('../pos');

        if (! file_exists($posPath)) {
            $this->error("No se encontró el POS en: $posPath");

            return 1;
        }

        $version = $this->option('version');

        if (! $version) {
            $version = $this->detectarVersion($posPath);

            if (! $version) {
                $this->error('No se pudo detectar la versión. Usa: --version=1.6.6');

                return 1;
            }

            $this->info("Versión detectada: $version");
        }

        $script = "$posPath/compilar-escritorio.ps1";

        if (! file_exists($script)) {
            $this->error("No se encontró el script: $script");

            return 1;
        }

        $this->info("Compilando POS v$version...");
        $this->line('');

        $cmd = [
            'powershell',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-File',
            $script,
            '-Version',
            $version,
            '-Manager',
            base_path(),
        ];

        $process = new Process($cmd);
        $process->setWorkingDirectory($posPath);
        $process->setTimeout(600);

        $process->run(function ($type, $buffer) {
            $this->line($buffer);
        });

        if ($process->isSuccessful()) {
            $this->info('✅ Compilación completada exitosamente');

            return 0;
        }

        $this->error('❌ Error en la compilación');

        return 1;
    }

    private function detectarVersion(string $posPath): ?string
    {
        $envFile = "$posPath/.env.escritorio";

        if (! file_exists($envFile)) {
            return null;
        }

        $content = file_get_contents($envFile);

        if (preg_match('/NATIVEPHP_APP_VERSION=(.+)/', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
