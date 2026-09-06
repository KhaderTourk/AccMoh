<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BalanceController;
use App\Http\Controllers\Api\BootstrapController;
use App\Http\Controllers\Api\CashPaymentController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Cp\ClientController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/bootstrap', BootstrapController::class);
        Route::get('/balances', BalanceController::class);

        Route::get('/clients/{client}/unpaid-services', [ClientController::class, 'unpaidServices']);

        Route::post('/cash-payments', [CashPaymentController::class, 'store']);

        Route::post('/sync/push', [SyncController::class, 'push']);
    });
});
