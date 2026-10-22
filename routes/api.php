<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BalanceController;
use App\Http\Controllers\Api\BootstrapController;
use App\Http\Controllers\Api\CashPaymentController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\V1;
use App\Http\Controllers\Cp\ClientController as CpClientController;
use App\Http\Controllers\Cp\PersonController as CpPersonController;
use App\Http\Controllers\Cp\ReportController as CpReportController;
use App\Http\Controllers\Cp\VendorController as CpVendorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'api.tenant', 'api.journal'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Offline mobile app (existing)
        Route::get('/bootstrap', BootstrapController::class);
        Route::get('/balances', BalanceController::class);
        Route::post('/cash-payments', [CashPaymentController::class, 'store']);
        Route::post('/sync/push', [SyncController::class, 'push']);

        // Full management API (same abilities as /cp, without delete)
        Route::get('/lookups', V1\LookupController::class);
        Route::get('/dashboard', [V1\DashboardController::class, 'index']);

        Route::name('api.')->middleware('api.permission:finance')->group(function () {
            Route::get('/balances/snapshot', [V1\BalanceController::class, 'index'])->name('balances.snapshot');
            Route::post('/balances/opening', [V1\BalanceController::class, 'storeOpening'])->name('balances.opening');

            Route::get('/balance-memos', [V1\PartyBalanceMemoController::class, 'index'])->name('balance-memos.index');
            Route::post('/balance-memos', [V1\PartyBalanceMemoController::class, 'store'])->name('balance-memos.store');
            Route::post('/balance-memos/transfer', [V1\PartyBalanceMemoController::class, 'transferStore'])->name('balance-memos.transfer');
            Route::get('/balance-memos/{balance_memo}', [V1\PartyBalanceMemoController::class, 'show'])->name('balance-memos.show');
            Route::put('/balance-memos/{balance_memo}', [V1\PartyBalanceMemoController::class, 'update'])->name('balance-memos.update');

            Route::get('/persons', [V1\PersonController::class, 'index'])->name('persons.index');
            Route::post('/persons', [V1\PersonController::class, 'store'])->name('persons.store');
            Route::get('/persons/{person}', [V1\PersonController::class, 'show'])->name('persons.show');
            Route::put('/persons/{person}', [V1\PersonController::class, 'update'])->name('persons.update');
            Route::get('/persons/{person}/export-pdf', [CpPersonController::class, 'exportPdf'])->name('persons.export-pdf');

            Route::get('/payments/incoming', [V1\CashPaymentController::class, 'incoming'])->name('payments.incoming');
            Route::get('/payments/outgoing', [V1\CashPaymentController::class, 'outgoing'])->name('payments.outgoing');
            Route::post('/payments/{direction}', [V1\CashPaymentController::class, 'store'])
                ->whereIn('direction', ['incoming', 'outgoing'])->name('payments.store');
            Route::get('/payments/{payment}', [V1\CashPaymentController::class, 'show'])->whereNumber('payment')->name('payments.show');
            Route::put('/payments/{payment}', [V1\CashPaymentController::class, 'update'])->whereNumber('payment')->name('payments.update');

            Route::get('/transfers', [V1\TransferController::class, 'index'])->name('transfers.index');
            Route::post('/transfers', [V1\TransferController::class, 'store'])->name('transfers.store');
            Route::get('/transfers/{transfer}', [V1\TransferController::class, 'show'])->name('transfers.show');
            Route::post('/transfers/{transfer}/reverse', [V1\TransferController::class, 'reverse'])->name('transfers.reverse');

            Route::get('/ledger', [V1\LedgerController::class, 'index'])->name('ledger.index');
            Route::get('/reports', [V1\ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/export-pdf', [CpReportController::class, 'exportPdf'])->name('reports.export-pdf');

            Route::middleware('cp.business')->group(function () {
                Route::get('/clients/{client}/unpaid-services', [CpClientController::class, 'unpaidServices'])->name('clients.unpaid-services');

                Route::post('/clients/import/preview', [V1\ClientImportController::class, 'preview'])->name('clients.import.preview');
                Route::post('/clients/import', [V1\ClientImportController::class, 'store'])->name('clients.import.store');

                Route::get('/clients', [V1\ClientController::class, 'index'])->name('clients.index');
                Route::post('/clients', [V1\ClientController::class, 'store'])->name('clients.store');
                Route::get('/clients/{client}', [V1\ClientController::class, 'show'])->name('clients.show');
                Route::put('/clients/{client}', [V1\ClientController::class, 'update'])->name('clients.update');
                Route::get('/clients/{client}/export-pdf', [CpClientController::class, 'exportPdf'])->name('clients.export-pdf');

                Route::get('/client-services', [V1\ClientServiceController::class, 'index'])->name('client-services.index');
                Route::post('/client-services', [V1\ClientServiceController::class, 'store'])->name('client-services.store');
                Route::get('/client-services/{client_service}', [V1\ClientServiceController::class, 'show'])->name('client-services.show');
                Route::put('/client-services/{client_service}', [V1\ClientServiceController::class, 'update'])->name('client-services.update');

                Route::get('/client-goods-takes', [V1\ClientGoodsTakeController::class, 'index'])->name('client-goods-takes.index');
                Route::post('/client-goods-takes', [V1\ClientGoodsTakeController::class, 'store'])->name('client-goods-takes.store');
                Route::get('/client-goods-takes/{client_goods_take}', [V1\ClientGoodsTakeController::class, 'show'])->name('client-goods-takes.show');
                Route::put('/client-goods-takes/{client_goods_take}', [V1\ClientGoodsTakeController::class, 'update'])->name('client-goods-takes.update');

                Route::get('/service-types', [V1\ServiceTypeController::class, 'index'])->name('service-types.index');
                Route::post('/service-types', [V1\ServiceTypeController::class, 'store'])->name('service-types.store');
                Route::get('/service-types/{service_type}', [V1\ServiceTypeController::class, 'show'])->name('service-types.show');
                Route::put('/service-types/{service_type}', [V1\ServiceTypeController::class, 'update'])->name('service-types.update');

                Route::get('/vendor-charges', [V1\VendorChargeController::class, 'index'])->name('vendor-charges.index');
                Route::post('/vendor-charges', [V1\VendorChargeController::class, 'store'])->name('vendor-charges.store');
                Route::get('/vendor-charges/{vendor_charge}', [V1\VendorChargeController::class, 'show'])->name('vendor-charges.show');
                Route::put('/vendor-charges/{vendor_charge}', [V1\VendorChargeController::class, 'update'])->name('vendor-charges.update');

                // Route names must contain "workers" / "suppliers": the vendor type is read from them.
                foreach (['workers', 'suppliers'] as $prefix) {
                    Route::get("/{$prefix}", [V1\VendorController::class, 'index'])->name("{$prefix}.index");
                    Route::post("/{$prefix}", [V1\VendorController::class, 'store'])->name("{$prefix}.store");
                    Route::get("/{$prefix}/{vendor}", [V1\VendorController::class, 'show'])->name("{$prefix}.show");
                    Route::put("/{$prefix}/{vendor}", [V1\VendorController::class, 'update'])->name("{$prefix}.update");
                    Route::get("/{$prefix}/{vendor}/export-pdf", [CpVendorController::class, 'exportPdf'])->name("{$prefix}.export-pdf");
                }
            });
        });

        Route::name('api.')->middleware('api.permission:users')->group(function () {
            Route::get('/users', [V1\UserController::class, 'index'])->name('users.index');
            Route::post('/users', [V1\UserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}', [V1\UserController::class, 'show'])->name('users.show');
            Route::put('/users/{user}', [V1\UserController::class, 'update'])->name('users.update');

            Route::get('/permissions', [V1\RoleController::class, 'permissions'])->name('permissions.index');
            Route::get('/roles', [V1\RoleController::class, 'index'])->name('roles.index');
            Route::post('/roles', [V1\RoleController::class, 'store'])->name('roles.store');
            Route::get('/roles/{role}', [V1\RoleController::class, 'show'])->name('roles.show');
            Route::put('/roles/{role}', [V1\RoleController::class, 'update'])->name('roles.update');
        });
    });
});
