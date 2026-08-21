<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cp\DashboardController;
use App\Http\Controllers\Cp\BalanceController;
use App\Http\Controllers\Cp\ClientController;
use App\Http\Controllers\Cp\ClientServiceController;
use App\Http\Controllers\Cp\ClientPaymentController;
use App\Http\Controllers\Cp\FamilyMemberController;
use App\Http\Controllers\Cp\FamilyLoanController;
use App\Http\Controllers\Cp\ExpenseController;
use App\Http\Controllers\Cp\TransferController;
use App\Http\Controllers\Cp\LedgerController;
use App\Http\Controllers\Cp\ReportController;
use App\Http\Controllers\Cp\ServiceTypeController;
use App\Http\Controllers\Cp\OfflineController;
use App\Http\Controllers\Api\BootstrapController as ApiBootstrapController;
use App\Http\Controllers\Api\BalanceController as ApiBalanceController;
use App\Http\Controllers\Api\ClientPaymentController as ApiClientPaymentController;
use App\Http\Controllers\Api\ExpenseController as ApiExpenseController;
use App\Http\Controllers\Api\FamilyLoanController as ApiFamilyLoanController;
use App\Http\Controllers\Api\SyncController as ApiSyncController;

Route::redirect('/', '/cp');

Route::get('/cp/login', [\App\Http\Controllers\Cp\AuthController::class, 'showLoginForm'])->name('cp.login');
Route::post('/cp/login', [\App\Http\Controllers\Cp\AuthController::class, 'login']);
Route::match(['get', 'post'], '/cp/logout', [\App\Http\Controllers\Cp\AuthController::class, 'logout'])->name('cp.logout');

Route::prefix('cp')->name('cp.')->middleware(['cp.auth', 'cp.check'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/offline', [OfflineController::class, 'index'])->name('offline');

    // Session-authenticated API for browser offline sync (same-origin + CSRF)
    Route::prefix('api/v1')->group(function () {
        Route::get('/bootstrap', ApiBootstrapController::class);
        Route::get('/balances', ApiBalanceController::class);
        Route::get('/clients/{client}/unpaid-services', [ApiClientPaymentController::class, 'unpaidServices']);
        Route::get('/family-members/{familyMember}/open-loans', [ApiFamilyLoanController::class, 'openLoans']);
        Route::post('/payments', [ApiClientPaymentController::class, 'store']);
        Route::post('/expenses', [ApiExpenseController::class, 'store']);
        Route::post('/family-loans', [ApiFamilyLoanController::class, 'storeLoan']);
        Route::post('/family-loan-repayments', [ApiFamilyLoanController::class, 'storeRepayment']);
        Route::post('/sync/push', [ApiSyncController::class, 'push']);
    });

    Route::get('/balances', [BalanceController::class, 'index'])->name('balances.index');
    Route::post('/balances/opening', [BalanceController::class, 'storeOpening'])->name('balances.opening');

    Route::resource('clients', ClientController::class);
    Route::resource('client-services', ClientServiceController::class)->except(['show']);
    Route::resource('payments', ClientPaymentController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('/payments/{payment}/reverse', [ClientPaymentController::class, 'reverse'])->name('payments.reverse');
    Route::get('/clients/{client}/unpaid-services', [ClientPaymentController::class, 'unpaidServices'])->name('clients.unpaid-services');

    Route::get('/family-members/{family_member}/open-loans', [FamilyLoanController::class, 'openLoans'])->name('family-members.open-loans');
    Route::resource('family-members', FamilyMemberController::class);
    Route::get('/family-loans', [FamilyLoanController::class, 'index'])->name('family-loans.index');
    Route::get('/family-loans/create', [FamilyLoanController::class, 'create'])->name('family-loans.create');
    Route::post('/family-loans', [FamilyLoanController::class, 'store'])->name('family-loans.store');
    Route::get('/family-loans/repay', [FamilyLoanController::class, 'createRepayment'])->name('family-loans.repay');
    Route::post('/family-loans/repay', [FamilyLoanController::class, 'storeRepayment'])->name('family-loans.repay.store');
    Route::post('/family-loans/{loan}/reverse', [FamilyLoanController::class, 'reverse'])->name('family-loans.reverse');

    Route::resource('expenses', ExpenseController::class)->only(['index', 'create', 'store']);
    Route::post('/expenses/{expense}/reverse', [ExpenseController::class, 'reverse'])->name('expenses.reverse');

    Route::resource('transfers', TransferController::class)->only(['index', 'create', 'store']);
    Route::post('/transfers/{transfer}/reverse', [TransferController::class, 'reverse'])->name('transfers.reverse');

    Route::get('/ledger', [LedgerController::class, 'index'])->name('ledger.index');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::resource('service-types', ServiceTypeController::class)->except(['show']);

    Route::resource('users', \App\Http\Controllers\Cp\UserController::class)->names('users')->except(['show']);
    Route::resource('roles', \App\Http\Controllers\Cp\RoleController::class)->names('roles')->except(['show']);
});

Route::get('storage/{file}', function ($file) {
    $path = storage_path('app/public/' . $file);
    if (! is_file($path)) {
        abort(404);
    }

    return response()->file($path);
})->where('file', '.+');
