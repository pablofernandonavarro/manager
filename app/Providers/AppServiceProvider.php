<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Subidas de Livewire: por defecto 12 MB. La importación de productos acepta CSV de
        // hasta 200.000 filas (~20-30 MB); PHP y nginx están en 50/55 MB.
        config(['livewire.temporary_file_upload.rules' => ['required', 'file', 'max:51200']]);
    }
}
