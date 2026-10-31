<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cp\DashboardController;
use App\Http\Controllers\Cp\BalanceController;
use App\Http\Controllers\Cp\ClientController;
use App\Http\Controllers\Cp\ClientImportController;
use App\Http\Controllers\Cp\ClientGoodsTakeController;
use App\Http\Controllers\Cp\ClientServiceController;
use App\Http\Controllers\Cp\CashPaymentController;
use App\Http\Controllers\Cp\PartyBalanceMemoController;
use App\Http\Controllers\Cp\PersonController;
use App\Http\Controllers\Cp\VendorController;
use App\Http\Controllers\Cp\VendorChargeController;
use App\Http\Controllers\Cp\TransferController;
use App\Http\Controllers\Cp\LedgerController;
use App\Http\Controllers\Cp\ReportController;
use App\Http\Controllers\Cp\ServiceTypeController;
use App\Http\Controllers\Cp\SettingsController;
use App\Http\Controllers\Cp\ClientSubscriptionController;
use App\Http\Controllers\Cp\OfflineController;
use App\Http\Controllers\Cp\ApiOperationController;
use App\Http\Controllers\Api\BootstrapController as ApiBootstrapController;
use App\Http\Controllers\Api\BalanceController as ApiBalanceController;
use App\Http\Controllers\Api\SyncController as ApiSyncController;
use App\Http\Controllers\Api\CashPaymentController as ApiCashPaymentController;
use App\Http\Controllers\Super\AuthController as SuperAuthController;
use App\Http\Controllers\Super\TenantController as SuperTenantController;

Route::redirect('/', '/cp');

Route::get('/cp/login', [\App\Http\Controllers\Cp\AuthController::class, 'showLoginForm'])->name('cp.login');
Route::post('/cp/login', [\App\Http\Controllers\Cp\AuthController::class, 'login']);
Route::match(['get', 'post'], '/cp/logout', [\App\Http\Controllers\Cp\AuthController::class, 'logout'])->name('cp.logout');

Route::get('/super/login', [SuperAuthController::class, 'showLoginForm'])->name('super.login');
Route::post('/super/login', [SuperAuthController::class, 'login'])->name('super.login.submit');
Route::post('/super/logout', [SuperAuthController::class, 'logout'])->name('super.logout');

Route::prefix('super')->name('super.')->middleware(['super.auth'])->group(function () {
    Route::get('/', [SuperTenantController::class, 'dashboard'])->name('dashboard');
    Route::get('/tenants/{tenant}/finances', [SuperTenantController::class, 'finances'])->name('tenants.finances');
    Route::get('/tenants/{tenant}/reports', [SuperTenantController::class, 'reports'])->name('tenants.reports');
    Route::resource('tenants', SuperTenantController::class)->except(['destroy']);
});

Route::prefix('cp')->name('cp.')->middleware(['cp.auth', 'cp.check'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/archive-visibility', function () {
        \App\Support\ArchiveVisibility::toggle();

        return back();
    })->name('archive-visibility');
    Route::get('/offline', [OfflineController::class, 'index'])->name('offline');

    Route::prefix('api/v1')->group(function () {
        Route::get('/bootstrap', ApiBootstrapController::class);
        Route::get('/balances', ApiBalanceController::class);
        Route::post('/cash-payments', [ApiCashPaymentController::class, 'store']);
        Route::post('/sync/push', [ApiSyncController::class, 'push']);

        Route::middleware('cp.business')->group(function () {
            Route::get('/clients/{client}/unpaid-services', [ClientController::class, 'unpaidServices']);
        });
    });

    Route::get('/balances', [BalanceController::class, 'index'])->name('balances.index');
    Route::post('/balances/opening', [BalanceController::class, 'storeOpening'])->name('balances.opening');

    Route::get('/balance-memos/transfer', [PartyBalanceMemoController::class, 'transferCreate'])->name('balance-memos.transfer');
    Route::post('/balance-memos/transfer', [PartyBalanceMemoController::class, 'transferStore'])->name('balance-memos.transfer.store');
    Route::resource('balance-memos', PartyBalanceMemoController::class)->except(['index', 'show']);

    Route::get('/persons/{person}/export-pdf', [PersonController::class, 'exportPdf'])->name('persons.export-pdf');
    Route::post('/persons/{person}/restore', [PersonController::class, 'restore'])->name('persons.restore');
    Route::resource('persons', PersonController::class);

    Route::get('/payments/incoming', [CashPaymentController::class, 'incoming'])->name('payments.incoming');
    Route::get('/payments/outgoing', [CashPaymentController::class, 'outgoing'])->name('payments.outgoing');
    Route::get('/payments/{direction}/create', [CashPaymentController::class, 'create'])
        ->whereIn('direction', ['incoming', 'outgoing'])
        ->name('payments.create');
    Route::post('/payments/{direction}', [CashPaymentController::class, 'store'])
        ->whereIn('direction', ['incoming', 'outgoing'])
        ->name('payments.store');
    Route::get('/payments/{payment}', [CashPaymentController::class, 'show'])->name('payments.show');
    Route::get('/payments/{payment}/edit', [CashPaymentController::class, 'edit'])->name('payments.edit');
    Route::put('/payments/{payment}', [CashPaymentController::class, 'update'])->name('payments.update');
    Route::delete('/payments/{payment}', [CashPaymentController::class, 'destroy'])->name('payments.destroy');

    Route::middleware('cp.business')->group(function () {
        Route::get('/clients/import', [ClientImportController::class, 'create'])->name('clients.import');
        Route::post('/clients/import/preview', [ClientImportController::class, 'preview'])->name('clients.import.preview');
        Route::get('/clients/import/review', [ClientImportController::class, 'review'])->name('clients.import.review');
        Route::post('/clients/import', [ClientImportController::class, 'store'])->name('clients.import.store');
        Route::resource('clients', ClientController::class);
        Route::get('/clients/{client}/export-pdf', [ClientController::class, 'exportPdf'])->name('clients.export-pdf');
        Route::post('/clients/{client}/restore', [ClientController::class, 'restore'])->name('clients.restore');
        Route::get('/clients/{client}/unpaid-services', [ClientController::class, 'unpaidServices'])->name('clients.unpaid-services');
        Route::resource('client-services', ClientServiceController::class)->except(['show']);
        Route::resource('client-goods-takes', ClientGoodsTakeController::class)->except(['index', 'show']);
        Route::resource('service-types', ServiceTypeController::class)->except(['show']);

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/groups', [SettingsController::class, 'storeGroup'])->name('settings.groups.store');
        Route::put('/settings/groups/{group}', [SettingsController::class, 'updateGroup'])->name('settings.groups.update');
        Route::delete('/settings/groups/{group}', [SettingsController::class, 'destroyGroup'])->name('settings.groups.destroy');
        Route::post('/settings/services', [SettingsController::class, 'storeService'])->name('settings.services.store');
        Route::put('/settings/services/{serviceType}', [SettingsController::class, 'updateService'])->name('settings.services.update');
        Route::post('/settings/services/{serviceType}/toggle', [SettingsController::class, 'toggleService'])->name('settings.services.toggle');
        Route::delete('/settings/services/{serviceType}', [SettingsController::class, 'destroyService'])->name('settings.services.destroy');
        Route::post('/settings/methods', [SettingsController::class, 'storeMethod'])->name('settings.methods.store');
        Route::put('/settings/methods/{paymentMethod}', [SettingsController::class, 'updateMethod'])->name('settings.methods.update');
        Route::post('/settings/methods/{paymentMethod}/toggle', [SettingsController::class, 'toggleMethod'])->name('settings.methods.toggle');
        Route::delete('/settings/methods/{paymentMethod}', [SettingsController::class, 'destroyMethod'])->name('settings.methods.destroy');

        Route::get('/subscriptions/confirm', [ClientSubscriptionController::class, 'confirm'])->name('subscriptions.confirm');
        Route::post('/subscriptions/confirm', [ClientSubscriptionController::class, 'confirmStore'])->name('subscriptions.confirm.store');
        Route::post('/subscriptions', [ClientSubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::post('/subscriptions/{subscription}/stop', [ClientSubscriptionController::class, 'stop'])->name('subscriptions.stop');
        Route::delete('/subscriptions/{subscription}', [ClientSubscriptionController::class, 'destroy'])->name('subscriptions.destroy');

        Route::resource('vendor-charges', VendorChargeController::class)->except(['index', 'show']);

        foreach (['workers', 'suppliers'] as $prefix) {
            Route::get("/{$prefix}", [VendorController::class, 'index'])->name("{$prefix}.index");
            Route::get("/{$prefix}/create", [VendorController::class, 'create'])->name("{$prefix}.create");
            Route::post("/{$prefix}", [VendorController::class, 'store'])->name("{$prefix}.store");
            Route::get("/{$prefix}/{vendor}/export-pdf", [VendorController::class, 'exportPdf'])->name("{$prefix}.export-pdf");
            Route::post("/{$prefix}/{vendor}/restore", [VendorController::class, 'restore'])->name("{$prefix}.restore");
            Route::get("/{$prefix}/{vendor}", [VendorController::class, 'show'])->name("{$prefix}.show");
            Route::get("/{$prefix}/{vendor}/edit", [VendorController::class, 'edit'])->name("{$prefix}.edit");
            Route::put("/{$prefix}/{vendor}", [VendorController::class, 'update'])->name("{$prefix}.update");
            Route::delete("/{$prefix}/{vendor}", [VendorController::class, 'destroy'])->name("{$prefix}.destroy");
        }
    });

    Route::resource('transfers', TransferController::class)->only(['index', 'create', 'store']);
    Route::post('/transfers/{transfer}/reverse', [TransferController::class, 'reverse'])->name('transfers.reverse');

    Route::get('/ledger', [LedgerController::class, 'index'])->name('ledger.index');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/services', [\App\Http\Controllers\Cp\ServiceIncomeController::class, 'index'])->name('reports.services');
    Route::get('/reports/pdf', [ReportController::class, 'exportPdf'])->name('reports.export-pdf');

    Route::get('/api-operations', [ApiOperationController::class, 'index'])->name('api-operations.index');
    Route::get('/api-operations/{apiOperation}', [ApiOperationController::class, 'show'])->name('api-operations.show');
    Route::post('/api-operations/{apiOperation}/undo', [ApiOperationController::class, 'undo'])->name('api-operations.undo');

    Route::resource('users', \App\Http\Controllers\Cp\UserController::class)->names('users')->except(['show']);
    Route::resource('roles', \App\Http\Controllers\Cp\RoleController::class)->names('roles')->except(['show']);
});

Route::get('storage/{file}', function ($file) {
    $path = storage_path('app/public/' . $file);
    if (! file_exists($path)) {
        abort(404);
    }

    return response()->file($path);
})->where('file', '.*');
