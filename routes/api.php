<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BalanceController;
use App\Http\Controllers\Api\BootstrapController;
use App\Http\Controllers\Api\ClientPaymentController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FamilyLoanController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Offline cache bootstrap + balances
        Route::get('/bootstrap', BootstrapController::class);
        Route::get('/balances', BalanceController::class);

        // Lookups for forms
        Route::get('/clients/{client}/unpaid-services', [ClientPaymentController::class, 'unpaidServices']);
        Route::get('/family-members/{familyMember}/open-loans', [FamilyLoanController::class, 'openLoans']);

        // Idempotent write operations (also usable online directly)
        Route::post('/payments', [ClientPaymentController::class, 'store']);
        Route::post('/expenses', [ExpenseController::class, 'store']);
        Route::post('/family-loans', [FamilyLoanController::class, 'storeLoan']);
        Route::post('/family-loan-repayments', [FamilyLoanController::class, 'storeRepayment']);

        // Batch outbox sync
        Route::post('/sync/push', [SyncController::class, 'push']);
    });
});
