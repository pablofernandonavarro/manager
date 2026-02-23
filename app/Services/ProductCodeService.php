<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductConfiguration;

/**
 * Servicio para generar códigos automáticos de productos según la configuración.
 */
class ProductCodeService
{
    protected ProductConfiguration $config;

    public function __construct()
    {
        $this->config = ProductConfiguration::current();
    }

    /**
     * Genera el próximo código interno automáticamente.
     */
    public function generateCode(): string
    {
        if (!$this->config->auto_generate_code) {
            return '';
        }

        $code = $this->config->generateNextCode();
        $this->config->incrementCodeCounter();

        return $code;
    }

    /**
     * Genera un SKU para un producto.
     */
    public function generateSKU(array $productData): string
    {
        return $this->config->generateSKU($productData);
    }

    /**
     * Valida que un código sea único si es requerido.
     */
    public function validateUniqueCode(?string $code): bool
    {
        if (!$this->config->require_unique_code || empty($code)) {
            return true;
        }

        return !Product::where('codigo_interno', $code)->exists();
    }

    /**
     * Valida que un código de barras sea único si es requerido.
     */
    public function validateUniqueBarcode(?string $barcode): bool
    {
        if (!$this->config->require_unique_barcode || empty($barcode)) {
            return true;
        }

        return !Product::where('codigo_barras', $barcode)->exists();
    }

    /**
     * Aplica valores por defecto a los datos del producto según la configuración.
     */
    public function applyDefaults(array $data): array
    {
        $defaults = [
            'estado' => $this->config->default_estado,
            'es_vendible' => $this->config->default_es_vendible,
            'remitible' => $this->config->default_remitible,
            'stock_critico' => $this->config->default_stock_critical,
            'iva' => $this->config->default_tax_rate,
        ];

        if ($this->config->default_marca) {
            $defaults['marca'] = $this->config->default_marca;
        }

        if ($this->config->default_linea) {
            $defaults['linea'] = $this->config->default_linea;
        }

        if ($this->config->default_familia) {
            $defaults['familia'] = $this->config->default_familia;
        }

        if ($this->config->default_temporada) {
            $defaults['temporada'] = $this->config->default_temporada;
        }

        // Aplicar solo si no están definidos en $data
        foreach ($defaults as $key => $value) {
            if (!isset($data[$key]) || $data[$key] === null || $data[$key] === '') {
                $data[$key] = $value;
            }
        }

        // Calcular precio automáticamente si está habilitado
        if ($this->config->auto_calculate_price && isset($data['costo']) && $data['costo'] > 0) {
            if (!isset($data['precio']) || $data['precio'] === null || $data['precio'] == 0) {
                $data['precio'] = $this->config->calculatePrice($data['costo']);
            }
        }

        return $data;
    }

    /**
     * Obtiene la configuración actual.
     */
    public function getConfig(): ProductConfiguration
    {
        return $this->config;
    }
}
