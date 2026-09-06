<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cp\DashboardController;
use App\Http\Controllers\Cp\BalanceController;
use App\Http\Controllers\Cp\ClientController;
use App\Http\Controllers\Cp\ClientServiceController;
use App\Http\Controllers\Cp\CashPaymentController;
use App\Http\Controllers\Cp\PersonController;
use App\Http\Controllers\Cp\VendorController;
use App\Http\Controllers\Cp\VendorChargeController;
use App\Http\Controllers\Cp\TransferController;
use App\Http\Controllers\Cp\LedgerController;
use App\Http\Controllers\Cp\ReportController;
use App\Http\Controllers\Cp\ServiceTypeController;
use App\Http\Controllers\Cp\OfflineController;
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

    Route::get('/persons/{person}/export-pdf', [PersonController::class, 'exportPdf'])->name('persons.export-pdf');
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
        Route::resource('clients', ClientController::class);
        Route::get('/clients/{client}/export-pdf', [ClientController::class, 'exportPdf'])->name('clients.export-pdf');
        Route::get('/clients/{client}/unpaid-services', [ClientController::class, 'unpaidServices'])->name('clients.unpaid-services');
        Route::resource('client-services', ClientServiceController::class)->except(['show']);
        Route::resource('service-types', ServiceTypeController::class)->except(['show']);

        Route::resource('vendor-charges', VendorChargeController::class)->except(['index', 'show']);

        foreach (['workers', 'suppliers'] as $prefix) {
            Route::get("/{$prefix}", [VendorController::class, 'index'])->name("{$prefix}.index");
            Route::get("/{$prefix}/create", [VendorController::class, 'create'])->name("{$prefix}.create");
            Route::post("/{$prefix}", [VendorController::class, 'store'])->name("{$prefix}.store");
            Route::get("/{$prefix}/{vendor}/export-pdf", [VendorController::class, 'exportPdf'])->name("{$prefix}.export-pdf");
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
    Route::get('/reports/pdf', [ReportController::class, 'exportPdf'])->name('reports.export-pdf');

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
