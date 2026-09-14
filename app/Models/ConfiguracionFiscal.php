<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Datos del emisor para la facturación electrónica. Hay una sola fila (id 1).
 */
class ConfiguracionFiscal extends Model
{
    protected $table = 'configuracion_fiscal';

    public const CONDICIONES_IVA = [
        'responsable_inscripto' => 'Responsable inscripto',
        'monotributo' => 'Monotributo',
        'exento' => 'Exento',
    ];

    protected $fillable = [
        'razon_social', 'cuit', 'condicion_iva', 'ingresos_brutos', 'inicio_actividades', 'domicilio_comercial',
        'entorno', 'facturacion_activa',
        'clave_privada', 'csr', 'certificado', 'certificado_alias', 'certificado_emisor', 'certificado_vence',
        'ta_token', 'ta_sign', 'ta_expira',
        'ultima_prueba_at', 'ultima_prueba_ok', 'ultima_prueba_detalle',
    ];

    protected $hidden = ['clave_privada', 'certificado', 'ta_token', 'ta_sign'];

    /** Los defaults de la migración no se cargan en un modelo recién creado con firstOrCreate. */
    protected $attributes = [
        'entorno' => 'homologacion',
        'facturacion_activa' => false,
    ];

    protected function casts(): array
    {
        return [
            'inicio_actividades' => 'date',
            'facturacion_activa' => 'boolean',
            'clave_privada' => 'encrypted',
            'certificado' => 'encrypted',
            'certificado_vence' => 'datetime',
            'ta_token' => 'encrypted',
            'ta_sign' => 'encrypted',
            'ta_expira' => 'datetime',
            'ultima_prueba_at' => 'datetime',
            'ultima_prueba_ok' => 'boolean',
            'ultima_prueba_detalle' => 'array',
        ];
    }

    /**
     * La única fila. El id se fuerza: `id` no es fillable, y firstOrCreate(['id' => 1])
     * creaba la fila con el siguiente autoincremental; si no era 1, cada llamada creaba
     * otra fila y la configuración guardada "desaparecía".
     */
    public static function actual(): self
    {
        if ($config = self::find(1)) {
            return $config;
        }

        $config = (new self)->forceFill(['id' => 1]);
        $config->save();

        return $config->fresh();
    }

    public function tieneCertificado(): bool
    {
        return $this->certificado !== null && $this->clave_privada !== null;
    }

    public function certificadoVigente(): bool
    {
        return $this->tieneCertificado() && $this->certificado_vence?->isFuture();
    }

    /** Comprobantes que emite según su condición frente al IVA. */
    public function letraComprobantes(): ?string
    {
        return match ($this->condicion_iva) {
            'responsable_inscripto' => 'A y B',
            'monotributo', 'exento' => 'C',
            default => null,
        };
    }

    /** Lo que falta para poder facturar. Vacío = listo. @return array<int, string> */
    public function faltantes(): array
    {
        return array_values(array_filter([
            $this->razon_social ? null : 'Razón social',
            $this->cuit ? null : 'CUIT',
            $this->condicion_iva ? null : 'Condición frente al IVA',
            $this->domicilio_comercial ? null : 'Domicilio comercial',
            $this->inicio_actividades ? null : 'Inicio de actividades',
            $this->tieneCertificado() ? null : 'Certificado digital',
            ! $this->tieneCertificado() || $this->certificadoVigente() ? null : 'Certificado vencido',
            Sucursal::whereNotNull('afip_punto_venta')->exists() ? null : 'Punto de venta en al menos una sucursal',
        ]));
    }
}
