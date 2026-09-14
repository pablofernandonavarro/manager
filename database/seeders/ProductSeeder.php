<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Models\AttributeType;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Services\ProductConfigurableService;
use App\Support\Ean13;
use Illuminate\Database\Seeder;

/**
 * Catálogo de indumentaria de ejemplo, reproducible: correrlo de nuevo actualiza en vez de
 * duplicar.
 *
 * Los configurables se crean con ProductConfigurableService, igual que desde la pantalla de
 * productos: el padre no se vende y cada combinación color + talle es un producto simple
 * hijo con su SKU (`CONF-4301-NEG-M`), su EAN-13 y su stock.
 *
 * Los EAN-13 salen de la posición en estas listas: agregar colores, talles o artículos
 * **al final**, nunca reordenar, o cambian los códigos de barras ya impresos.
 */
class ProductSeeder extends Seeder
{
    /** Prefijo GS1 Argentina (779) + empresa de ejemplo. */
    private const PREFIJO_EAN = '7791234';

    /** @var list<array{codigo: string, nombre: string, precio: int, costo: int, colores: list<string>, talles: list<string>}> */
    public const CONFIGURABLES = [
        ['codigo' => 'CONF-4301', 'nombre' => 'Remera básica algodón', 'precio' => 12900, 'costo' => 5200, 'colores' => ['Negro', 'Blanco'], 'talles' => ['S', 'M', 'L']],
        ['codigo' => 'CONF-4302', 'nombre' => 'Buzo canguro frisa', 'precio' => 34900, 'costo' => 14800, 'colores' => ['Negro', 'Gris', 'Azul'], 'talles' => ['S', 'M', 'L', 'XL']],
        ['codigo' => 'CONF-4303', 'nombre' => 'Jean slim tiro medio', 'precio' => 42900, 'costo' => 18500, 'colores' => ['Azul', 'Negro'], 'talles' => ['38', '40', '42', '44', '46']],
        ['codigo' => 'CONF-4304', 'nombre' => 'Campera rompeviento', 'precio' => 58900, 'costo' => 24000, 'colores' => ['Negro', 'Verde'], 'talles' => ['M', 'L', 'XL']],
        ['codigo' => 'CONF-4305', 'nombre' => 'Vestido de lino', 'precio' => 39900, 'costo' => 16200, 'colores' => ['Blanco', 'Rosa'], 'talles' => ['XS', 'S', 'M']],
    ];

    /** @var list<array{codigo: string, nombre: string, precio: int, costo: int}> */
    public const SIMPLES = [
        ['codigo' => 'PANT-001', 'nombre' => 'Pantalón básico gabardina', 'precio' => 29900, 'costo' => 12500],
        ['codigo' => 'ACC-001', 'nombre' => 'Cinturón de cuero', 'precio' => 15900, 'costo' => 6100],
        ['codigo' => 'ACC-002', 'nombre' => 'Gorra de gabardina', 'precio' => 9900, 'costo' => 3800],
        ['codigo' => 'ACC-003', 'nombre' => 'Medias de algodón pack x3', 'precio' => 6900, 'costo' => 2400],
        ['codigo' => 'ACC-004', 'nombre' => 'Bufanda tejida', 'precio' => 13900, 'costo' => 5300],
    ];

    public function __construct(
        private readonly ProductConfigurableService $productService,
    ) {}

    public function run(): void
    {
        $color = AttributeType::where('slug', 'color')->firstOrFail();
        $talle = AttributeType::where('slug', 'talle')->firstOrFail();

        $this->asegurarValores($color, array_merge(...array_column(self::CONFIGURABLES, 'colores')));
        $this->asegurarValores($talle, array_merge(...array_column(self::CONFIGURABLES, 'talles')));

        foreach (self::CONFIGURABLES as $i => $articulo) {
            $this->configurable($i + 1, $articulo, $color, $talle);
        }

        foreach (self::SIMPLES as $i => $articulo) {
            Product::updateOrCreate(
                ['codigo_interno' => $articulo['codigo']],
                [
                    ...$this->datosComunes($articulo),
                    'product_type' => ProductType::SIMPLE,
                    'parent_id' => null,
                    'codigo_barras' => self::ean(2, 0, $i + 1),
                    'es_vendible' => true,
                ]
            );
        }

        $this->reconstruirBusqueda();

        $this->command?->info(sprintf('Indumentaria: %d configurables con %d variantes, %d simples.',
            Product::configurable()->count(), Product::whereNotNull('parent_id')->count(), Product::simple()->whereNull('parent_id')->count()));
    }

    /**
     * @param  array{codigo: string, nombre: string, precio: int, costo: int, colores: list<string>, talles: list<string>}  $articulo
     */
    private function configurable(int $numero, array $articulo, AttributeType $color, AttributeType $talle): void
    {
        $variantes = [];

        foreach ($articulo['colores'] as $ci => $valorColor) {
            foreach ($articulo['talles'] as $ti => $valorTalle) {
                $variantes[] = [
                    'attributes' => [
                        ['slug' => $color->slug, 'product_column' => $color->product_column, 'value' => $valorColor],
                        ['slug' => $talle->slug, 'product_column' => $talle->product_column, 'value' => $valorTalle],
                    ],
                    // Posición color × talle: estable si se agregan colores o talles al final.
                    'codigo_barras' => self::ean(1, $numero, $ci * 10 + $ti),
                    // El stock real lo carga StockSeeder por sucursal.
                    'stock' => 0,
                ];
            }
        }

        $datos = [...$this->datosComunes($articulo), 'codigo_interno' => $articulo['codigo']];
        $padre = Product::configurable()->where('codigo_interno', $articulo['codigo'])->first();

        if (! $padre) {
            $this->productService->createConfigurableWithVariants($datos, $variantes);

            return;
        }

        $padre->update($this->datosComunes($articulo));

        foreach ($variantes as $variante) {
            [$attrColor, $attrTalle] = $variante['attributes'];

            $existente = Product::where('parent_id', $padre->id)
                ->where('color', $attrColor['value'])
                ->where('n_talle', $attrTalle['value'])
                ->first();

            $existente
                ? $existente->update(['codigo_barras' => $variante['codigo_barras'], 'precio' => $articulo['precio'], 'costo' => $articulo['costo'], 'es_vendible' => true, 'estado' => 1])
                : $this->productService->addVariantsToConfigurable($padre, [$variante]);
        }
    }

    /**
     * DatabaseSeeder corre sin eventos de modelo (WithoutModelEvents), y `busqueda` se arma en
     * el evento saving de Product: sin esto el catálogo quedaba sin texto de búsqueda.
     */
    private function reconstruirBusqueda(): void
    {
        $codigos = array_column(self::SIMPLES, 'codigo');

        Product::query()
            ->where(fn ($q) => $q->whereIn('codigo_interno', $codigos)
                ->orWhereIn('codigo_interno', array_column(self::CONFIGURABLES, 'codigo'))
                ->orWhereIn('parent_id', Product::configurable()->whereIn('codigo_interno', array_column(self::CONFIGURABLES, 'codigo'))->select('id')))
            ->each(fn (Product $p) => $p->forceFill(['busqueda' => $p->buildBusqueda()])->saveQuietly());
    }

    /**
     * @param  array{nombre: string, precio: int, costo: int}  $articulo
     * @return array<string, mixed>
     */
    private function datosComunes(array $articulo): array
    {
        return [
            'nombre' => $articulo['nombre'],
            'precio' => $articulo['precio'],
            'costo' => $articulo['costo'],
            'publico' => $articulo['precio'],
            'iva' => 21,
            'stock_critico' => 3,
            'estado' => 1,
            'remitible' => true,
        ];
    }

    /** @param  list<string>  $valores */
    private function asegurarValores(AttributeType $tipo, array $valores): void
    {
        $orden = (int) AttributeValue::where('attribute_type_id', $tipo->id)->max('orden');

        foreach (array_unique($valores) as $valor) {
            AttributeValue::firstOrCreate(
                ['attribute_type_id' => $tipo->id, 'valor' => $valor],
                ['orden' => ++$orden, 'activo' => true]
            );
        }
    }

    /** EAN-13: prefijo + tipo (1 variante, 2 simple) + artículo (2) + posición (2) + verificador. */
    public static function ean(int $tipo, int $articulo, int $posicion): string
    {
        return Ean13::conVerificador(self::PREFIJO_EAN.$tipo.sprintf('%02d%02d', $articulo, $posicion));
    }
}
