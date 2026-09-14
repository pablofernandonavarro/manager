<?php

namespace App\Console\Commands;

use App\Jobs\AutorizarComprobante;
use App\Models\Comprobante;
use Illuminate\Console\Command;

/**
 * Red de seguridad: vuelve a encolar los comprobantes pendientes (jobs que agotaron sus
 * intentos, worker caído, cortes largos de AFIP). El lock de numeración y el uniqueId del
 * job evitan que un comprobante se procese dos veces a la vez.
 */
class AutorizarComprobantesPendientesCommand extends Command
{
    protected $signature = 'facturacion:autorizar-pendientes';

    protected $description = 'Encola la autorización en AFIP de los comprobantes pendientes';

    public function handle(): int
    {
        $pendientes = Comprobante::where('estado', 'pendiente')
            ->where('updated_at', '<', now()->subMinutes(2))
            ->orderBy('id')
            ->limit(500)
            ->get();

        foreach ($pendientes as $comprobante) {
            AutorizarComprobante::dispatch($comprobante);
        }

        $this->info("Encolados {$pendientes->count()} comprobante(s) pendiente(s).");

        return self::SUCCESS;
    }
}
