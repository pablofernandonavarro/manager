<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->nullable()->index();
            $table->string('busqueda', 160)->nullable();
            $table->string('codigo_interno', 100)->nullable()->default('0')->index();
            $table->decimal('precio', 10, 3)->nullable();
            $table->decimal('costo', 10, 3)->nullable();
            $table->decimal('costo_usd', 10, 3)->nullable();
            $table->decimal('costo_produccion', 10, 3)->nullable();
            $table->integer('stock')->nullable();
            $table->integer('stock_critico')->default(0);
            $table->string('plano', 100)->nullable();
            $table->text('descripcion_web')->nullable();
            $table->text('descripcion_tecnica')->nullable();
            $table->string('manual', 100)->nullable();
            $table->integer('linea')->nullable()->index();
            $table->tinyInteger('estado')->default(0)->index();
            $table->string('imagen', 100)->nullable();
            $table->string('imagen_ml', 100)->nullable();
            $table->string('imagen_url', 200)->nullable();
            $table->integer('stock_comprometido')->nullable();
            $table->string('denominacion', 100)->nullable();
            $table->unsignedInteger('marca')->nullable();
            $table->decimal('peso', 10, 3)->default(0.000);
            $table->string('dimension', 100)->default('');
            $table->string('abreviatura', 100)->default('');
            $table->decimal('iva', 10, 3)->default(21.000);
            $table->unsignedInteger('medida')->nullable();
            $table->decimal('pallet', 10, 3)->nullable();
            $table->unsignedInteger('presentacion')->nullable();
            $table->decimal('cantidad_um_x_presentacion', 10, 3)->nullable();
            $table->integer('cuenta')->nullable();
            $table->integer('sociedad')->nullable();
            $table->boolean('exportar')->default(false);
            $table->integer('orden')->nullable();
            $table->boolean('comision_especial')->default(false);
            $table->boolean('compuesto')->default(false);
            $table->boolean('remitible')->default(true);
            $table->tinyInteger('garantia')->nullable();
            $table->boolean('es_vendible')->default(false);
            $table->integer('articulo')->nullable()->index();
            $table->string('color', 100)->nullable();
            $table->string('color_ml', 100)->nullable();
            $table->boolean('publicar_ml')->default(false);
            $table->integer('stock_ml')->nullable();
            $table->string('codigo_barras', 100)->nullable()->index();
            $table->integer('metadata_detalle1')->nullable()->index();
            $table->integer('metadata_detalle2')->nullable()->index();
            $table->integer('metadata_detalle3')->nullable();
            $table->boolean('es_materia_prima')->default(false);
            $table->integer('edad')->nullable()->index();
            $table->integer('grupo')->nullable()->index();
            $table->integer('subgrupo')->nullable()->index();
            $table->integer('temporada')->nullable()->index();
            $table->string('modelista', 200)->nullable();
            $table->string('numero_molde', 100)->nullable();
            $table->integer('cliente')->nullable();
            $table->integer('proveedor')->nullable();
            $table->integer('proveedor2')->nullable();
            $table->integer('proveedor3')->nullable();
            $table->integer('grupo_proceso')->nullable();
            $table->string('n_proveedor', 100)->nullable();
            $table->tinyInteger('tipo_costeo')->nullable();
            $table->integer('proceso')->nullable();
            $table->decimal('rinde', 10, 2)->nullable();
            $table->integer('cantidad_a_fabricar')->nullable();
            $table->integer('disenador')->nullable();
            $table->integer('procedencia')->nullable();
            $table->decimal('costo_corte', 10, 2)->nullable();
            $table->tinyInteger('ingresado')->nullable();
            $table->integer('epc')->nullable()->index();
            $table->integer('reservados')->nullable();
            $table->integer('molde')->default(1);
            $table->integer('progresion')->default(1);
            $table->integer('ficha_tecnica')->default(1);
            $table->integer('estampa')->default(1);
            $table->integer('bordado')->default(1);
            $table->integer('tachas')->default(1);
            $table->integer('etiquetas')->default(1);
            $table->integer('avios')->default(1);
            $table->integer('lavado')->default(1);
            $table->integer('muestra')->default(1);
            $table->integer('muestrario')->default(1);
            $table->integer('encorte')->default(1);
            $table->string('observaciones', 600)->nullable();
            $table->integer('familia')->nullable();
            $table->integer('target')->nullable();
            $table->tinyInteger('estado_web')->nullable();
            $table->decimal('descuento_web', 5, 2)->nullable();
            $table->boolean('destacado_web')->nullable();
            $table->integer('unidad_medida')->default(1);
            $table->decimal('precio_usd', 16, 2)->nullable();
            $table->decimal('publico', 16, 2)->nullable();
            $table->boolean('no_aplica_descuento')->default(false);
            $table->integer('lista_publico')->nullable();
            $table->integer('lista_mayorista')->nullable()->index();
            $table->integer('articulo_segunda')->nullable();
            $table->boolean('procesado')->default(false);
            $table->string('dafiti_sku')->nullable();
            $table->tinyInteger('estado_dafiti')->nullable();
            $table->decimal('porcentaje', 4, 2)->nullable();
            $table->boolean('nube_actualizar')->default(false);
            $table->string('gtin')->nullable();
            $table->date('ploter_desde')->nullable();
            $table->date('ploter_hasta')->nullable();
            $table->tinyInteger('ploter_ok')->nullable();
            $table->date('medicion_desde')->nullable();
            $table->date('medicion_hasta')->nullable();
            $table->tinyInteger('medicion_ok')->nullable();
            $table->string('ancho_proveedor')->nullable();
            $table->string('ancho_real')->nullable();
            $table->boolean('precio_variable')->default(false);
            $table->date('fecha_costo')->nullable();
            $table->string('observaciones_modelaje')->nullable();
            $table->date('fecha_venta1')->nullable();
            $table->string('n_grupo', 100)->nullable();
            $table->string('n_grupo_ext', 100)->nullable();
            $table->string('n_subgrupo', 100)->nullable();
            $table->string('n_subgrupo_ext', 100)->nullable();
            $table->string('n_target', 100)->nullable();
            $table->string('n_target_ext', 100)->nullable();
            $table->string('n_talle', 50)->nullable();
            $table->string('n_color', 50)->nullable();
            $table->string('n_temporada', 100)->nullable();
            $table->string('n_temporada_ext', 100)->nullable();
            $table->string('n_procedencia', 100)->nullable();
            $table->string('n_procedencia_ext', 100)->nullable();
            $table->integer('primera')->default(0);
            $table->integer('segunda')->default(0);
            $table->string('mix', 100)->default('');
            $table->date('fecha_ingreso')->nullable();
            $table->string('composicion', 100)->nullable();
            $table->string('foto_modelo', 100)->default('');
            $table->string('foto_modelo_detalle', 100)->default('');
            $table->string('foto_medidas', 100)->default('');
            $table->string('foto_estampa', 100)->default('');
            $table->integer('complejidad')->nullable();
            $table->integer('etapa')->nullable();
            $table->integer('nivel_precio')->nullable();
            $table->integer('cant_colores')->nullable();
            $table->string('codigo_color_proveedor')->nullable();
            $table->string('codigo_articulo_proveedor')->nullable();
            $table->tinyInteger('magento_subido')->default(0);
            $table->tinyInteger('magento_actualizado')->default(0);
            $table->integer('categoria_dafiti')->default(0);
            $table->tinyInteger('dafiti_actualizar')->default(1);
            $table->tinyInteger('remitible_auto')->default(0);
            $table->decimal('costo_target', 15, 3)->nullable();
            $table->decimal('markup', 15, 3)->default(0.000);
            $table->decimal('precosto_avios', 15, 3)->default(0.000);
            $table->decimal('precosto_telas', 15, 3)->default(0.000);
            $table->string('url_ecomm', 750)->nullable();
            $table->integer('tipo_embalaje')->default(0);
            $table->decimal('costo_adicional', 15, 3)->default(0.000);
            $table->decimal('precosto_compra', 15, 3)->default(0.000);
            $table->string('articulo_origen', 100)->nullable()->comment('Código artículo origen (ej: BOSS)');
            $table->string('modelo_origen', 100)->nullable()->comment('Código modelo origen (ej: BOSS)');

            $table->timestamps();
            $table->softDeletes();

            // Índice único compuesto
            $table->unique(['articulo', 'metadata_detalle1', 'metadata_detalle2'], 'combinado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
