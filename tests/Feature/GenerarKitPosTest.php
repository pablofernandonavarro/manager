<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class GenerarKitPosTest extends TestCase
{
    private string $origen;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->origen = storage_path('framework/testing/pos-origen-'.uniqid());

        foreach ([
            'artisan' => '<?php',
            'VERSION' => '2026.09.14.1200',
            'app/Models/Venta.php' => '<?php',
            'vendor/autoload.php' => '<?php',
            'node_modules/.package-lock.json' => '{}',
            'instalar-pos.bat' => "@echo off\necho hola\n",
            // Identidad y secretos de la instalación de origen: nunca al kit.
            '.env' => 'APP_KEY=base64:caja',
            '.env.escritorio' => 'APP_KEY=base64:escritorio',
            '.pos-info' => 'Caja 1',
            'database/database.sqlite' => 'ventas',
            'storage/logs/laravel.log' => 'log',
        ] as $ruta => $contenido) {
            File::ensureDirectoryExists(dirname("{$this->origen}/{$ruta}"));
            File::put("{$this->origen}/{$ruta}", $contenido);
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->origen);

        parent::tearDown();
    }

    public function test_el_kit_lleva_el_codigo_pero_no_la_identidad_ni_los_secretos_de_la_caja(): void
    {
        $this->artisan('pos:kit', ['ruta' => $this->origen])->assertSuccessful();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path('pos-kit/instalador-pos.zip')));

        $archivos = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $archivos[] = $zip->getNameIndex($i);
        }

        $this->assertContains('app/Models/Venta.php', $archivos);
        $this->assertContains('vendor/autoload.php', $archivos);
        $this->assertSame("@echo off\r\necho hola\r\n", $zip->getFromName('instalar-pos.bat'));

        foreach (['.env', '.env.escritorio', '.pos-info', 'database/database.sqlite', 'storage/logs/laravel.log'] as $prohibido) {
            $this->assertNotContains($prohibido, $archivos, "El kit no puede llevar {$prohibido}");
        }

        $zip->close();
    }
}
