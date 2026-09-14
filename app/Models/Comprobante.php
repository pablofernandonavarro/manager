<?php

namespace App\Models;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Factura o nota de crédito electrónica. Nace pendiente cuando llega la venta (o la
 * devolución) de una caja y pasa a autorizada con el CAE de AFIP, o a rechazada.
 */
class Comprobante extends Model
{
    /** Códigos de AFIP (FEParamGetTiposCbte). */
    public const TIPOS = [
        1 => 'Factura A',
        6 => 'Factura B',
        11 => 'Factura C',
        3 => 'Nota de crédito A',
        8 => 'Nota de crédito B',
        13 => 'Nota de crédito C',
    ];

    public const NOTA_DE_CREDITO_DE = [1 => 3, 6 => 8, 11 => 13];

    /** Condición frente al IVA del receptor (FEParamGetCondicionIvaReceptor). */
    public const CONDICIONES_IVA = [
        5 => 'Consumidor final',
        1 => 'IVA Responsable inscripto',
        6 => 'Responsable monotributo',
        4 => 'IVA Sujeto exento',
    ];

    public const DOC_TIPOS = [80 => 'CUIT', 86 => 'CUIL', 96 => 'DNI', 99 => 'Sin identificar'];

    /** Alícuota de IVA (%) → código de AFIP. */
    public const ALICUOTAS_IVA = ['0' => 3, '2.5' => 9, '5' => 8, '10.5' => 4, '21' => 5, '27' => 6];

    protected $fillable = [
        'venta_id', 'devolucion_id', 'comprobante_asociado_id', 'sucursal_id', 'punto_de_venta_id',
        'entorno', 'afip_punto_venta', 'tipo', 'numero', 'fecha',
        'receptor_doc_tipo', 'receptor_doc_nro', 'receptor_nombre', 'receptor_condicion_iva',
        'importe_total', 'importe_neto', 'importe_iva', 'alicuotas',
        'estado', 'cae', 'cae_vencimiento', 'observaciones', 'error', 'intentos', 'autorizado_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'importe_total' => 'decimal:2',
            'importe_neto' => 'decimal:2',
            'importe_iva' => 'decimal:2',
            'alicuotas' => 'array',
            'observaciones' => 'array',
            'cae_vencimiento' => 'date',
            'autorizado_at' => 'datetime',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class);
    }

    public function asociado(): BelongsTo
    {
        return $this->belongsTo(self::class, 'comprobante_asociado_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function puntoDeVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoDeVenta::class);
    }

    public function estaAutorizado(): bool
    {
        return $this->estado === 'autorizado';
    }

    public function esNotaDeCredito(): bool
    {
        return in_array($this->tipo, self::NOTA_DE_CREDITO_DE, true);
    }

    public function letra(): string
    {
        return match ($this->tipo) {
            1, 3 => 'A',
            6, 8 => 'B',
            default => 'C',
        };
    }

    public function nombreTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? "Comprobante {$this->tipo}";
    }

    public function numeroFormateado(): ?string
    {
        return $this->numero === null
            ? null
            : sprintf('%05d-%08d', $this->afip_punto_venta, $this->numero);
    }

    /**
     * URL del QR fiscal (RG 4892): datos del comprobante en JSON y base64.
     */
    public function urlQr(string $cuitEmisor): ?string
    {
        if (! $this->estaAutorizado()) {
            return null;
        }

        $datos = [
            'ver' => 1,
            'fecha' => $this->fecha->toDateString(),
            'cuit' => (int) $cuitEmisor,
            'ptoVta' => $this->afip_punto_venta,
            'tipoCmp' => $this->tipo,
            'nroCmp' => (int) $this->numero,
            'importe' => (float) $this->importe_total,
            'moneda' => 'PES',
            'ctz' => 1,
            'tipoDocRec' => $this->receptor_doc_tipo,
            'nroDocRec' => (int) $this->receptor_doc_nro,
            'tipoCodAut' => 'E',
            'codAut' => (int) $this->cae,
        ];

        return 'https://www.afip.gob.ar/fe/qr/?p='.base64_encode(json_encode($datos));
    }

    /** QR como SVG para imprimir en el ticket: la caja lo guarda y lo usa sin conexión. */
    public function svgQr(string $cuitEmisor): ?string
    {
        $url = $this->urlQr($cuitEmisor);

        if ($url === null) {
            return null;
        }

        $opciones = new QROptions([
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'addQuietzone' => true,
            'quietzoneSize' => 1,
        ]);

        return (new QRCode($opciones))->render($url);
    }

    /**
     * Lo que la caja necesita para imprimir y mostrar el comprobante.
     *
     * @return array<string, mixed>
     */
    public function paraCaja(ConfiguracionFiscal $emisor): array
    {
        return [
            'estado' => $this->estado,
            'tipo' => $this->tipo,
            'nombre_tipo' => $this->nombreTipo(),
            'letra' => $this->letra(),
            'codigo' => sprintf('%03d', $this->tipo),
            'numero' => $this->numeroFormateado(),
            'fecha' => $this->fecha->toDateString(),
            'cae' => $this->cae,
            'cae_vencimiento' => $this->cae_vencimiento?->toDateString(),
            'receptor' => [
                'doc_tipo' => $this->receptor_doc_tipo,
                'doc_tipo_nombre' => self::DOC_TIPOS[$this->receptor_doc_tipo] ?? null,
                'doc_nro' => $this->receptor_doc_nro,
                'nombre' => $this->receptor_nombre,
                'condicion_iva' => self::CONDICIONES_IVA[$this->receptor_condicion_iva] ?? null,
            ],
            'importe_total' => (float) $this->importe_total,
            'importe_neto' => (float) $this->importe_neto,
            'importe_iva' => (float) $this->importe_iva,
            'alicuotas' => $this->alicuotas,
            'asociado' => $this->asociado ? [
                'nombre_tipo' => $this->asociado->nombreTipo(),
                'numero' => $this->asociado->numeroFormateado(),
            ] : null,
            'qr_svg' => $this->svgQr((string) $emisor->cuit),
            'error' => $this->estado === 'autorizado' ? null : $this->error,
        ];
    }
}
