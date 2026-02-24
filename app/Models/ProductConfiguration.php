<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductConfiguration extends Model
{
    protected $fillable = [
        'auto_generate_code',
        'code_format',
        'code_prefix',
        'code_suffix',
        'code_length',
        'code_separator',
        'code_next_number',
        'auto_generate_sku',
        'sku_format',
        'sku_uppercase',
        'auto_generate_barcode',
        'barcode_format',
        'barcode_prefix',
        'require_unique_code',
        'require_unique_barcode',
        'require_images',
        'min_images',
        'max_images',
        'child_inherits_parent_images',
        'allow_negative_stock',
        'track_stock',
        'default_stock_critical',
        'require_cost',
        'require_price',
        'auto_calculate_price',
        'default_markup',
        'default_tax_rate',
        'default_marca',
        'default_linea',
        'default_familia',
        'default_temporada',
        'default_estado',
        'default_es_vendible',
        'default_remitible',
        'require_variants_for_configurable',
        'min_variants',
        'variant_attributes',
    ];

    protected function casts(): array
    {
        return [
            'auto_generate_code' => 'boolean',
            'auto_generate_sku' => 'boolean',
            'sku_uppercase' => 'boolean',
            'auto_generate_barcode' => 'boolean',
            'require_unique_code' => 'boolean',
            'require_unique_barcode' => 'boolean',
            'require_images' => 'boolean',
            'child_inherits_parent_images' => 'boolean',
            'allow_negative_stock' => 'boolean',
            'track_stock' => 'boolean',
            'require_cost' => 'boolean',
            'require_price' => 'boolean',
            'auto_calculate_price' => 'boolean',
            'default_estado' => 'boolean',
            'default_es_vendible' => 'boolean',
            'default_remitible' => 'boolean',
            'require_variants_for_configurable' => 'boolean',
            'variant_attributes' => 'array',
            'default_stock_critical' => 'decimal:2',
            'default_markup' => 'decimal:2',
            'default_tax_rate' => 'decimal:2',
            'min_images' => 'integer',
            'max_images' => 'integer',
            'code_length' => 'integer',
            'code_next_number' => 'integer',
            'min_variants' => 'integer',
        ];
    }

    /**
     * Obtiene la configuración activa (siempre hay solo 1 registro).
     */
    public static function current(): self
    {
        return self::firstOrFail();
    }

    /**
     * Genera el próximo código interno según la configuración.
     */
    public function generateNextCode(): string
    {
        if (! $this->auto_generate_code) {
            return '';
        }

        $code = '';

        // Agregar prefijo
        if ($this->code_prefix) {
            $code .= $this->code_prefix.$this->code_separator;
        }

        // Generar el número según el formato
        switch ($this->code_format) {
            case 'sequential':
                $number = str_pad($this->code_next_number, $this->code_length, '0', STR_PAD_LEFT);
                $code .= $number;
                break;

            case 'timestamp':
                $code .= date('YmdHis');
                break;

            case 'sku_based':
                $code .= strtoupper(substr(uniqid(), -$this->code_length));
                break;

            case 'manual':
            default:
                return '';
        }

        // Agregar sufijo
        if ($this->code_suffix) {
            $code .= $this->code_separator.$this->code_suffix;
        }

        return $code;
    }

    /**
     * Incrementa el contador de códigos.
     */
    public function incrementCodeCounter(): void
    {
        if ($this->code_format === 'sequential') {
            $this->increment('code_next_number');
        }
    }

    /**
     * Genera un SKU para un producto basado en la configuración.
     */
    public function generateSKU(array $productData): string
    {
        if (! $this->auto_generate_sku || ! $this->sku_format) {
            return '';
        }

        $sku = $this->sku_format;

        // Reemplazar placeholders
        $replacements = [
            '{CODIGO}' => $productData['codigo_interno'] ?? '',
            '{MARCA}' => $productData['marca'] ?? '',
            '{FAMILIA}' => $productData['familia'] ?? '',
            '{GRUPO}' => $productData['grupo'] ?? '',
            '{COLOR}' => $productData['color'] ?? '',
            '{TALLE}' => $productData['n_talle'] ?? '',
            '{LINEA}' => $productData['linea'] ?? '',
        ];

        $sku = str_replace(array_keys($replacements), array_values($replacements), $sku);

        if ($this->sku_uppercase) {
            $sku = strtoupper($sku);
        }

        return $sku;
    }

    /**
     * Calcula el precio automáticamente desde el costo + markup.
     */
    public function calculatePrice(?float $cost): ?float
    {
        if (! $this->auto_calculate_price || ! $cost) {
            return null;
        }

        return $cost * (1 + ($this->default_markup / 100));
    }
}
