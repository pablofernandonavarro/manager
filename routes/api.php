<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\V1\PosAuthController;
use App\Http\Controllers\Api\V1\SyncController;
use Illuminate\Support\Facades\Route;

// Rutas de autenticación para la API (POS)
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas con Sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});

// API v1 — POS sync
Route::prefix('v1')->group(function (): void {
    Route::post('pos/auth', [PosAuthController::class, 'token']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('sync/productos', [SyncController::class, 'productos']);
        Route::get('sync/precios', [SyncController::class, 'precios']);
        Route::get('sync/stock', [SyncController::class, 'stock']);
        Route::post('sync/ventas', [SyncController::class, 'ventas']);
        Route::post('sync/movimientos', [SyncController::class, 'movimientos']);
        Route::get('precios/{productId}', [SyncController::class, 'precio']);
    });
});
