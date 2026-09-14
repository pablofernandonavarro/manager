<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\V1\PosAuthController;
use App\Http\Controllers\Api\V1\PosClientesController;
use App\Http\Controllers\Api\V1\PosComandosController;
use App\Http\Controllers\Api\V1\PosDevolucionesController;
use App\Http\Controllers\Api\V1\PosEstadoController;
use App\Http\Controllers\Api\V1\PosFacturasController;
use App\Http\Controllers\Api\V1\PosProvisionController;
use App\Http\Controllers\Api\V1\PosRemitosController;
use App\Http\Controllers\Api\V1\PosTurnosController;
use App\Http\Controllers\Api\V1\PosVersionController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Middleware\RegistrarConexionPos;
use Illuminate\Support\Facades\Route;

// Rutas de autenticación para la API (POS)
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas con Sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});

// API v1 — POS sync
Route::prefix('v1')->group(function (): void {
    // Con rate limit: sin esto el secret de una caja se puede probar por fuerza bruta.
    Route::post('pos/auth', [PosAuthController::class, 'token'])
        ->middleware('throttle:10,1');

    // Canje del código de instalación. Sin auth (el código es la credencial), por eso
    // se limita el ritmo: evita que alguien pruebe códigos por fuerza bruta.
    Route::post('pos/provision', PosProvisionController::class)
        ->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', RegistrarConexionPos::class])->group(function (): void {
        Route::get('sync/productos', [SyncController::class, 'productos']);
        Route::get('sync/precios', [SyncController::class, 'precios']);
        Route::get('sync/stock', [SyncController::class, 'stock']);
        Route::get('sync/remitos', [SyncController::class, 'remitos']);
        Route::post('sync/remitos/{id}/confirmar', [SyncController::class, 'confirmarRemito']);
        Route::post('sync/ventas', [SyncController::class, 'ventas']);
        Route::post('sync/movimientos', [SyncController::class, 'movimientos']);
        Route::get('sync/promociones', [SyncController::class, 'promociones']);
        Route::get('sync/cajeros', [SyncController::class, 'cajeros']);
        Route::get('sync/clientes', [PosClientesController::class, 'index']);
        Route::post('sync/cobros-cuenta-corriente', [PosClientesController::class, 'cobros']);
        Route::post('sync/devoluciones', [PosDevolucionesController::class, 'sync']);
        Route::post('sync/turnos', [PosTurnosController::class, 'sync']);
        Route::get('precios/{productId}', [SyncController::class, 'precio']);

        // Canal de órdenes: la caja pregunta qué tiene pendiente y reporta el resultado.
        Route::get('pos/comandos', [PosComandosController::class, 'index']);
        Route::post('pos/estado', [PosEstadoController::class, 'reportar']);
        Route::post('pos/comandos/{comando}/resultado', [PosComandosController::class, 'resultado']);

        // Remitos que vienen en camino a la sucursal de la caja, y su recepción.
        Route::get('pos/remitos', [PosRemitosController::class, 'index']);
        Route::post('pos/remitos/{remito}/recibir', [PosRemitosController::class, 'recibir'])->whereNumber('remito');

        // Facturación electrónica: la caja pide la factura al Manager, que habla con AFIP.
        Route::get('pos/facturacion', [PosFacturasController::class, 'emisor']);
        Route::post('pos/facturas', [PosFacturasController::class, 'facturar']);
        Route::post('pos/comprobantes/estado', [PosFacturasController::class, 'estado']);

        // Actualización del código de la caja.
        Route::get('pos/version', [PosVersionController::class, 'actual']);
        Route::get('pos/paquete', [PosVersionController::class, 'descargar']);
    });
});
