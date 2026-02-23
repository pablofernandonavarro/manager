<?php

namespace App\Livewire\Configuration;

use App\Models\ProductConfiguration;
use Livewire\Attributes\Layout;
use Livewire\Component;

class ProductSettings extends Component
{
    public ProductConfiguration $config;

    // Configuración de códigos internos
    public bool $auto_generate_code = false;
    public string $code_format = 'sequential';
    public ?string $code_prefix = null;
    public ?string $code_suffix = null;
    public int $code_length = 6;
    public string $code_separator = '-';
    public int $code_next_number = 1;

    // Configuración de SKU
    public bool $auto_generate_sku = false;
    public ?string $sku_format = null;
    public bool $sku_uppercase = true;

    // Configuración de códigos de barras
    public bool $auto_generate_barcode = false;
    public string $barcode_format = 'ean13';
    public ?string $barcode_prefix = null;

    // Validaciones
    public bool $require_unique_code = true;
    public bool $require_unique_barcode = true;
    public bool $require_images = false;
    public int $min_images = 1;
    public int $max_images = 10;

    // Stock y precios
    public bool $allow_negative_stock = false;
    public bool $track_stock = true;
    public float $default_stock_critical = 10;
    public bool $require_cost = false;
    public bool $require_price = true;
    public bool $auto_calculate_price = false;
    public float $default_markup = 50;

    // Impuestos
    public float $default_tax_rate = 21;

    // Valores por defecto
    public ?string $default_marca = null;
    public ?string $default_linea = null;
    public ?string $default_familia = null;
    public ?string $default_temporada = null;
    public bool $default_estado = true;
    public bool $default_es_vendible = true;
    public bool $default_remitible = true;

    // Variantes
    public bool $require_variants_for_configurable = true;
    public int $min_variants = 1;

    public function mount(): void
    {
        $this->config = ProductConfiguration::current();

        // Cargar valores actuales
        $this->auto_generate_code = $this->config->auto_generate_code;
        $this->code_format = $this->config->code_format;
        $this->code_prefix = $this->config->code_prefix;
        $this->code_suffix = $this->config->code_suffix;
        $this->code_length = $this->config->code_length;
        $this->code_separator = $this->config->code_separator;
        $this->code_next_number = $this->config->code_next_number;

        $this->auto_generate_sku = $this->config->auto_generate_sku;
        $this->sku_format = $this->config->sku_format;
        $this->sku_uppercase = $this->config->sku_uppercase;

        $this->auto_generate_barcode = $this->config->auto_generate_barcode;
        $this->barcode_format = $this->config->barcode_format;
        $this->barcode_prefix = $this->config->barcode_prefix;

        $this->require_unique_code = $this->config->require_unique_code;
        $this->require_unique_barcode = $this->config->require_unique_barcode;
        $this->require_images = $this->config->require_images;
        $this->min_images = $this->config->min_images;
        $this->max_images = $this->config->max_images;

        $this->allow_negative_stock = $this->config->allow_negative_stock;
        $this->track_stock = $this->config->track_stock;
        $this->default_stock_critical = $this->config->default_stock_critical;
        $this->require_cost = $this->config->require_cost;
        $this->require_price = $this->config->require_price;
        $this->auto_calculate_price = $this->config->auto_calculate_price;
        $this->default_markup = $this->config->default_markup;

        $this->default_tax_rate = $this->config->default_tax_rate;

        $this->default_marca = $this->config->default_marca;
        $this->default_linea = $this->config->default_linea;
        $this->default_familia = $this->config->default_familia;
        $this->default_temporada = $this->config->default_temporada;
        $this->default_estado = $this->config->default_estado;
        $this->default_es_vendible = $this->config->default_es_vendible;
        $this->default_remitible = $this->config->default_remitible;

        $this->require_variants_for_configurable = $this->config->require_variants_for_configurable;
        $this->min_variants = $this->config->min_variants;
    }

    public function save(): void
    {
        $this->config->update([
            'auto_generate_code' => $this->auto_generate_code,
            'code_format' => $this->code_format,
            'code_prefix' => $this->code_prefix,
            'code_suffix' => $this->code_suffix,
            'code_length' => $this->code_length,
            'code_separator' => $this->code_separator,
            'code_next_number' => $this->code_next_number,

            'auto_generate_sku' => $this->auto_generate_sku,
            'sku_format' => $this->sku_format,
            'sku_uppercase' => $this->sku_uppercase,

            'auto_generate_barcode' => $this->auto_generate_barcode,
            'barcode_format' => $this->barcode_format,
            'barcode_prefix' => $this->barcode_prefix,

            'require_unique_code' => $this->require_unique_code,
            'require_unique_barcode' => $this->require_unique_barcode,
            'require_images' => $this->require_images,
            'min_images' => $this->min_images,
            'max_images' => $this->max_images,

            'allow_negative_stock' => $this->allow_negative_stock,
            'track_stock' => $this->track_stock,
            'default_stock_critical' => $this->default_stock_critical,
            'require_cost' => $this->require_cost,
            'require_price' => $this->require_price,
            'auto_calculate_price' => $this->auto_calculate_price,
            'default_markup' => $this->default_markup,

            'default_tax_rate' => $this->default_tax_rate,

            'default_marca' => $this->default_marca,
            'default_linea' => $this->default_linea,
            'default_familia' => $this->default_familia,
            'default_temporada' => $this->default_temporada,
            'default_estado' => $this->default_estado,
            'default_es_vendible' => $this->default_es_vendible,
            'default_remitible' => $this->default_remitible,

            'require_variants_for_configurable' => $this->require_variants_for_configurable,
            'min_variants' => $this->min_variants,
        ]);

        session()->flash('success', 'Configuración guardada correctamente.');
    }

    public function generatePreview(): string
    {
        $preview = '';

        if ($this->code_prefix) {
            $preview .= $this->code_prefix . $this->code_separator;
        }

        switch ($this->code_format) {
            case 'sequential':
                $preview .= str_pad($this->code_next_number, $this->code_length, '0', STR_PAD_LEFT);
                break;
            case 'timestamp':
                $preview .= date('YmdHis');
                break;
            case 'sku_based':
                $preview .= strtoupper(substr(uniqid(), -$this->code_length));
                break;
        }

        if ($this->code_suffix) {
            $preview .= $this->code_separator . $this->code_suffix;
        }

        return $preview ?: 'N/A';
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.configuration.product-settings', [
            'codePreview' => $this->generatePreview(),
        ]);
    }
}
