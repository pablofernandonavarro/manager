<?php

namespace App\Jobs;

use App\Exceptions\AfipException;
use App\Models\Comprobante;
use App\Services\Facturacion\EmisionComprobantes;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pide el CAE de un comprobante en segundo plano: ventas que llegaron sin conexión, AFIP
 * caído en el momento del cobro, notas de crédito que esperan a su factura. Si se agotan
 * los intentos, `facturacion:autorizar-pendientes` lo vuelve a encolar.
 */
class AutorizarComprobante implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    public int $uniqueFor = 900;

    public function __construct(
        public Comprobante $comprobante,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->comprobante->id;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 60, 120, 300, 600, 900];
    }

    public function handle(EmisionComprobantes $emision): void
    {
        try {
            $emision->autorizar($this->comprobante);
        } catch (AfipException $e) {
            // Sigue pendiente: se reintenta con el backoff.
            $this->release($this->backoff()[min($this->attempts() - 1, count($this->backoff()) - 1)]);
        }
    }
}
