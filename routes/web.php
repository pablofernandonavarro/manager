<?php

use App\Http\Controllers\ExportarVentasController;
use App\Http\Controllers\Sucursales\AjusteStockController;
use App\Livewire\Auditoria\Sincronizacion as AuditoriaSincronizacion;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Cajas\Cierres as CajasCierres;
use App\Livewire\Clientes\CuentaCorriente as ClientesCuentaCorriente;
use App\Livewire\Clientes\Index as ClientesIndex;
use App\Livewire\Configuration\Grupos;
use App\Livewire\Configuration\Lineas;
use App\Livewire\Configuration\Marcas;
use App\Livewire\Configuration\Procedencias;
use App\Livewire\Configuration\ProductAttributes;
use App\Livewire\Configuration\ProductSettings;
use App\Livewire\Configuration\Subgrupos;
use App\Livewire\Configuration\Targets;
use App\Livewire\Configuration\Temporadas;
use App\Livewire\Dashboard;
use App\Livewire\Facturacion\Comprobantes as FacturacionComprobantes;
use App\Livewire\Facturacion\Configuracion as FacturacionConfiguracion;
use App\Livewire\ListasPrecios\Buscador as ListasPreciosBuscador;
use App\Livewire\ListasPrecios\Edit as ListasPreciosEdit;
use App\Livewire\ListasPrecios\Index as ListasPreciosIndex;
use App\Livewire\Permissions\Index as PermissionsIndex;
use App\Livewire\Products\Create as ProductsCreate;
use App\Livewire\Products\Edit as ProductsEdit;
use App\Livewire\Products\Importar as ProductsImportar;
use App\Livewire\Products\Index as ProductsIndex;
use App\Livewire\Products\Precios as ProductsPrecios;
use App\Livewire\Products\Show as ProductsShow;
use App\Livewire\Products\Stock as ProductsStock;
use App\Livewire\Promociones\Index as PromocionesIndex;
use App\Livewire\PuntosDeVenta\Index as PuntosDeVentaIndex;
use App\Livewire\Remitos\Configuracion as RemitosConfiguracion;
use App\Livewire\Reportes\Ventas as ReportesVentas;
use App\Livewire\Roles\Create as RolesCreate;
use App\Livewire\Roles\Edit as RolesEdit;
use App\Livewire\Roles\Index as RolesIndex;
use App\Livewire\Sucursales\AjusteStock as SucursalesAjusteStock;
use App\Livewire\Sucursales\Edit as SucursalesEdit;
use App\Livewire\Sucursales\Index as SucursalesIndex;
use App\Livewire\Sucursales\ListasPrecios as SucursalesListasPrecios;
use App\Livewire\Sucursales\RemitoNuevo as SucursalesRemitoNuevo;
use App\Livewire\Sucursales\Remitos as SucursalesRemitos;
use App\Livewire\Sucursales\Stock as SucursalesStock;
use App\Livewire\Users\Create;
use App\Livewire\Users\Edit;
use App\Livewire\Users\Index;
use App\Livewire\Ventas\PorArticulo as VentasPorArticulo;
use App\Models\Remito;
use App\Services\ImportacionProductos;
use App\Support\AppEscritorio;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

// Redirección de la raíz al dashboard
Route::get('/', function () {
    return redirect('/dashboard');
});

// Rutas de autenticación (solo para invitados)
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

// Rutas protegidas (requieren autenticación)
Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    // Logout
    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/login');
    })->name('logout');

    // Productos (CRUD)
    Route::get('/productos', ProductsIndex::class)->name('productos.index');
    Route::get('/productos/crear', ProductsCreate::class)->name('productos.create');
    Route::get('/productos/precios', ProductsPrecios::class)->name('productos.precios');
    // Antes de /productos/{productId}, si no "stock" se toma como un id.
    Route::get('/productos/stock', ProductsStock::class)->name('productos.stock');
    Route::get('/productos/importar', ProductsImportar::class)->middleware('can:productos.crear')->name('productos.importar');
    Route::get('/productos/importar/plantilla', function (\Illuminate\Http\Request $request, ImportacionProductos $importacion) {
        $libro = $importacion->plantilla();

        // CSV para archivos grandes (más de 20.000 filas): mismo encabezado, separado por ;.
        if ($request->query('formato') === 'csv') {
            return response()->streamDownload(function () use ($libro): void {
                echo "\xEF\xBB\xBF".implode(';', $libro->getSheet(0)->toArray()[0])."\r\n";
            }, 'plantilla-productos.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(
            fn () => (new XlsxWriter($libro))->save('php://output'),
            'plantilla-productos.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    })->middleware('can:productos.crear')->name('productos.importar.plantilla');
    Route::get('/productos/{productId}', ProductsShow::class)->name('productos.show');
    Route::get('/productos/{productId}/editar', ProductsEdit::class)->name('productos.edit');

    // Usuarios (CRUD). Con permiso: quien crea usuarios o toca roles puede darse admin.
    Route::get('/usuarios', Index::class)->middleware('can:usuarios.ver')->name('usuarios.index');
    Route::get('/usuarios/crear', Create::class)->middleware('can:usuarios.crear')->name('usuarios.create');
    Route::get('/usuarios/{userId}/editar', Edit::class)->middleware('can:usuarios.editar')->name('usuarios.edit');

    // Roles (CRUD)
    Route::get('/roles', RolesIndex::class)->middleware('can:usuarios.editar')->name('roles.index');
    Route::get('/roles/crear', RolesCreate::class)->middleware('can:usuarios.editar')->name('roles.create');
    Route::get('/roles/{roleId}/editar', RolesEdit::class)->middleware('can:usuarios.editar')->name('roles.edit');

    // Permisos (Solo lectura)
    Route::get('/permisos', PermissionsIndex::class)->middleware('can:usuarios.editar')->name('permissions.index');

    // Configuración
    Route::get('/configuracion/productos', ProductSettings::class)->name('configuration.products');
    Route::get('/configuracion/atributos', ProductAttributes::class)->name('configuration.attributes');
    Route::get('/configuracion/marcas', Marcas::class)->name('configuration.marcas');
    Route::get('/configuracion/lineas', Lineas::class)->name('configuration.lineas');
    Route::get('/configuracion/temporadas', Temporadas::class)->name('configuration.temporadas');
    Route::get('/configuracion/grupos', Grupos::class)->name('configuration.grupos');
    Route::get('/configuracion/subgrupos', Subgrupos::class)->name('configuration.subgrupos');
    Route::get('/configuracion/targets', Targets::class)->name('configuration.targets');
    Route::get('/configuracion/procedencias', Procedencias::class)->name('configuration.procedencias');

    // Listas de precios
    Route::get('/listas-precios', ListasPreciosIndex::class)->name('listas-precios.index');
    Route::get('/listas-precios/{id}/editar', ListasPreciosEdit::class)->name('listas-precios.edit');
    Route::get('/listas-precios/{id}/productos', ListasPreciosBuscador::class)->name('listas-precios.show');

    // Sucursales
    Route::get('/sucursales', SucursalesIndex::class)->name('sucursales.index');
    Route::get('/sucursales/listas-precios', SucursalesListasPrecios::class)->name('sucursales.listas-precios');
    Route::get('/sucursales/stock', SucursalesStock::class)->name('sucursales.stock');
    // Ajuste de inventario: pisa el stock de una sucursal.
    Route::get('/sucursales/ajuste-stock', SucursalesAjusteStock::class)
        ->middleware('can:stock.ajustar')->name('sucursales.ajuste-stock');
    Route::get('/sucursales/ajuste-stock/plantilla', [AjusteStockController::class, 'plantilla'])
        ->middleware('can:stock.ajustar')->name('sucursales.ajuste-stock.plantilla');
    // Remitos: mueven stock entre sucursales, por eso cada acción tiene su permiso.
    Route::get('/sucursales/remitos', SucursalesRemitos::class)
        ->middleware('can:remitos.ver')->name('sucursales.remitos');
    Route::get('/sucursales/remitos/nuevo', SucursalesRemitoNuevo::class)
        ->middleware('can:remitos.crear')->name('sucursales.remitos.nuevo');
    Route::get('/sucursales/remitos/{id}/imprimir', function (int $id) {
        $remito = Remito::with(['sucursalOrigen', 'sucursalDestino', 'detalles.product', 'user'])->findOrFail($id);

        return view('remitos.imprimir', compact('remito'));
    })->middleware('can:remitos.ver')->whereNumber('id')->name('remitos.imprimir');
    Route::get('/remitos/configuracion', RemitosConfiguracion::class)
        ->middleware('can:remitos.configurar')->name('remitos.configuracion');
    Route::get('/sucursales/{id}/editar', SucursalesEdit::class)->name('sucursales.edit');

    // Puntos de venta
    Route::get('/puntos-de-venta', PuntosDeVentaIndex::class)->name('pdv.index');
    Route::get('/auditoria-sincronizacion', AuditoriaSincronizacion::class)->name('auditoria-sincronizacion.index');

    // Caja y cobro
    Route::get('/cajas/cierres', CajasCierres::class)->middleware('can:cajas.ver')->name('cajas.cierres');
    Route::get('/promociones-bancarias', PromocionesIndex::class)->middleware('can:promociones.gestionar')->name('promociones.index');
    // Clientes y cuenta corriente
    Route::get('/clientes', ClientesIndex::class)->middleware('can:clientes.gestionar')->name('clientes.index');
    Route::get('/clientes/{cliente}', ClientesCuentaCorriente::class)->middleware('can:clientes.gestionar')->name('clientes.show');

    // Facturación electrónica
    Route::get('/facturacion/configuracion', FacturacionConfiguracion::class)->middleware('can:facturacion.configurar')->name('facturacion.configuracion');
    Route::get('/facturacion/comprobantes', FacturacionComprobantes::class)->middleware('can:facturacion.ver')->name('facturacion.comprobantes');

    // Reportes
    Route::get('/reportes/ventas', ReportesVentas::class)->middleware('can:reportes.ver')->name('reportes.ventas');
    Route::get('/reportes/ventas/exportar', ExportarVentasController::class)->middleware('can:reportes.ver')->name('reportes.ventas.exportar');

    // Ventas
    Route::get('/ventas', VentasPorArticulo::class)->name('ventas.por-articulo');

    // Instalador del POS. Detrás del mismo permiso que instalar una caja: el kit lleva
    // el código completo del POS y quien lo tiene puede levantar una terminal.
    Route::get('/puntos-de-venta/instalador', function () {
        $zip = Storage::disk('local')->path('pos-kit/instalador-pos.zip');

        abort_unless(file_exists($zip), 404, 'Todavía no se generó el instalador. Ejecutá: php artisan pos:kit <ruta-de-un-pos>');

        return response()->download($zip, 'instalador-pos.zip');
    })->middleware('can:terminales.instalar')->name('pdv.instalador');

    // App de escritorio (NativePHP), Windows o Mac (?plataforma=mac). Mismo permiso: con el
    // ejecutable y un código se da de alta una caja.
    Route::get('/puntos-de-venta/instalador-escritorio', function (\Illuminate\Http\Request $request) {
        $plataforma = $request->query('plataforma', 'windows');
        $app = is_string($plataforma) ? AppEscritorio::publicada($plataforma) : null;

        abort_unless($app, 404, 'Todavía no se publicó la app de escritorio para esa plataforma.');

        $sufijo = $plataforma === 'mac' ? '-mac' : '';

        return response()->download($app['archivo'], "POS-Escritorio-{$app['version']}{$sufijo}.{$app['formato']}");
    })->middleware('can:terminales.instalar')->name('pdv.instalador-escritorio');
});
