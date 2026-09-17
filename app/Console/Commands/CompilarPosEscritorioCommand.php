<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class CompilarPosEscritorioCommand extends Command
{
    protected $signature = 'pos:compilar-escritorio {--pos-version= : Versión a compilar (ej: 1.6.6)} {--con-tests : Correr los tests antes de compilar (por defecto se omiten)}';

    protected $description = 'Compila la app de escritorio NativePHP del POS';

    public function handle(): int
    {
        $posPath = base_path('../pos');

        if (! file_exists($posPath)) {
            $this->error("No se encontró el POS en: $posPath");

            return 1;
        }

        $version = $this->option('pos-version');

        if (! $version) {
            $actual = $this->versionActual($posPath);

            if (! $actual) {
                $this->error('No se pudo detectar la versión actual. Usa: --pos-version=1.6.6');

                return 1;
            }

            $version = $this->incrementarPatch($actual);
            $this->info("Última versión compilada: $actual → compilando $version");
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

        if (! $this->option('con-tests')) {
            $cmd[] = '-SinTests';
        }

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

    private function versionActual(string $posPath): ?string
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

    private function incrementarPatch(string $version): string
    {
        $partes = explode('.', $version);
        $partes[2] = (int) ($partes[2] ?? 0) + 1;

        return implode('.', $partes);
    }
}
