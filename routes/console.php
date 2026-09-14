<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Facturas y notas de crédito que quedaron sin CAE. Necesita el scheduler (`schedule:work`
// o el cron de Laravel) y un `queue:work` que procese los jobs.
Schedule::command('facturacion:autorizar-pendientes')->everyFiveMinutes()->withoutOverlapping();
