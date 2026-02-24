<?php

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Configuration\Grupos;
use App\Livewire\Configuration\Lineas;
use App\Livewire\Configuration\Marcas;
use App\Livewire\Configuration\Procedencias;
use App\Livewire\Configuration\ProductAttributes;
use App\Livewire\Configuration\ProductSettings;
use App\Livewire\Configuration\Subgrupos;
use App\Livewire\Configuration\Targets;
use App\Livewire\Configuration\Temporadas;
use App\Livewire\ListasPrecios\Buscador as ListasPreciosBuscador;
use App\Livewire\ListasPrecios\Edit as ListasPreciosEdit;
use App\Livewire\ListasPrecios\Index as ListasPreciosIndex;
use App\Livewire\Permissions\Index as PermissionsIndex;
use App\Livewire\Products\Create as ProductsCreate;
use App\Livewire\Products\Edit as ProductsEdit;
use App\Livewire\Products\Index as ProductsIndex;
use App\Livewire\Products\Show as ProductsShow;
use App\Livewire\PuntosDeVenta\Index as PuntosDeVentaIndex;
use App\Livewire\Roles\Create as RolesCreate;
use App\Livewire\Roles\Edit as RolesEdit;
use App\Livewire\Roles\Index as RolesIndex;
use App\Livewire\Sucursales\Edit as SucursalesEdit;
use App\Livewire\Sucursales\Index as SucursalesIndex;
use App\Livewire\Sucursales\ListasPrecios as SucursalesListasPrecios;
use App\Livewire\Sucursales\Stock as SucursalesStock;
use App\Livewire\Users\Create;
use App\Livewire\Users\Edit;
use App\Livewire\Users\Index;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

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
    Route::get('/productos/{productId}', ProductsShow::class)->name('productos.show');
    Route::get('/productos/{productId}/editar', ProductsEdit::class)->name('productos.edit');

    // Usuarios (CRUD)
    Route::get('/usuarios', Index::class)->name('usuarios.index');
    Route::get('/usuarios/crear', Create::class)->name('usuarios.create');
    Route::get('/usuarios/{userId}/editar', Edit::class)->name('usuarios.edit');

    // Roles (CRUD)
    Route::get('/roles', RolesIndex::class)->name('roles.index');
    Route::get('/roles/crear', RolesCreate::class)->name('roles.create');
    Route::get('/roles/{roleId}/editar', RolesEdit::class)->name('roles.edit');

    // Permisos (Solo lectura)
    Route::get('/permisos', PermissionsIndex::class)->name('permissions.index');

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
    Route::get('/sucursales/{id}/editar', SucursalesEdit::class)->name('sucursales.edit');

    // Puntos de venta
    Route::get('/puntos-de-venta', PuntosDeVentaIndex::class)->name('pdv.index');
});
