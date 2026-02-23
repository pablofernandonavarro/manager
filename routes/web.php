<?php

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Configuration\ProductSettings;
use App\Livewire\Permissions\Index as PermissionsIndex;
use App\Livewire\Products\Create as ProductsCreate;
use App\Livewire\Products\Edit as ProductsEdit;
use App\Livewire\Products\Index as ProductsIndex;
use App\Livewire\Products\Show as ProductsShow;
use App\Livewire\Roles\Create as RolesCreate;
use App\Livewire\Roles\Edit as RolesEdit;
use App\Livewire\Roles\Index as RolesIndex;
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
});
