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
use App\Http\Controllers\Cp\ExpenseCategoryController;
use App\Http\Controllers\Cp\VendorController;
use App\Http\Controllers\Cp\VendorChargeController;
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
        Route::get('/family-members/{familyMember}/open-loans', [ApiFamilyLoanController::class, 'openLoans']);
        Route::post('/expenses', [ApiExpenseController::class, 'store']);
        Route::post('/family-loans', [ApiFamilyLoanController::class, 'storeLoan']);
        Route::post('/family-loan-repayments', [ApiFamilyLoanController::class, 'storeRepayment']);
        Route::post('/sync/push', [ApiSyncController::class, 'push']);

        Route::middleware('cp.business')->group(function () {
            Route::get('/clients/{client}/unpaid-services', [ApiClientPaymentController::class, 'unpaidServices']);
            Route::post('/payments', [ApiClientPaymentController::class, 'store']);
        });
    });

    Route::get('/balances', [BalanceController::class, 'index'])->name('balances.index');
    Route::post('/balances/opening', [BalanceController::class, 'storeOpening'])->name('balances.opening');

    Route::middleware('cp.business')->group(function () {
        Route::resource('clients', ClientController::class);
        Route::get('/clients/{client}/export-pdf', [ClientController::class, 'exportPdf'])->name('clients.export-pdf');
        Route::resource('client-services', ClientServiceController::class)->except(['show']);
        Route::resource('payments', ClientPaymentController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('/payments/{payment}/reverse', [ClientPaymentController::class, 'reverse'])->name('payments.reverse');
        Route::get('/clients/{client}/unpaid-services', [ClientPaymentController::class, 'unpaidServices'])->name('clients.unpaid-services');
        Route::resource('service-types', ServiceTypeController::class)->except(['show']);

        Route::resource('vendor-charges', VendorChargeController::class)->except(['index', 'show']);

        foreach (['workers', 'suppliers'] as $prefix) {
            Route::get("/{$prefix}", [VendorController::class, 'index'])->name("{$prefix}.index");
            Route::get("/{$prefix}/create", [VendorController::class, 'create'])->name("{$prefix}.create");
            Route::post("/{$prefix}", [VendorController::class, 'store'])->name("{$prefix}.store");
            Route::get("/{$prefix}/{vendor}", [VendorController::class, 'show'])->name("{$prefix}.show");
            Route::get("/{$prefix}/{vendor}/edit", [VendorController::class, 'edit'])->name("{$prefix}.edit");
            Route::put("/{$prefix}/{vendor}", [VendorController::class, 'update'])->name("{$prefix}.update");
            Route::delete("/{$prefix}/{vendor}", [VendorController::class, 'destroy'])->name("{$prefix}.destroy");
        }
    });

    Route::get('/family-members/{family_member}/open-loans', [FamilyLoanController::class, 'openLoans'])->name('family-members.open-loans');
    Route::get('/family-members/{family_member}/export-pdf', [FamilyMemberController::class, 'exportPdf'])->name('family-members.export-pdf');
    Route::resource('family-members', FamilyMemberController::class);
    Route::get('/family-loans/debtors', [FamilyLoanController::class, 'debtors'])->name('family-loans.debtors');
    Route::get('/family-loans/creditors', [FamilyLoanController::class, 'creditors'])->name('family-loans.creditors');
    Route::get('/family-loans', [FamilyLoanController::class, 'index'])->name('family-loans.index');
    Route::get('/family-loans/create', [FamilyLoanController::class, 'create'])->name('family-loans.create');
    Route::post('/family-loans', [FamilyLoanController::class, 'store'])->name('family-loans.store');
    Route::get('/family-loans/repay', [FamilyLoanController::class, 'createRepayment'])->name('family-loans.repay');
    Route::post('/family-loans/repay', [FamilyLoanController::class, 'storeRepayment'])->name('family-loans.repay.store');
    Route::get('/family-loans/{loan}/edit', [FamilyLoanController::class, 'edit'])->name('family-loans.edit');
    Route::put('/family-loans/{loan}', [FamilyLoanController::class, 'update'])->name('family-loans.update');
    Route::delete('/family-loans/{loan}', [FamilyLoanController::class, 'destroy'])->name('family-loans.destroy');
    Route::post('/family-loans/{loan}/reverse', [FamilyLoanController::class, 'reverse'])->name('family-loans.reverse');
    Route::post('/family-loan-repayments/{repayment}/reverse', [FamilyLoanController::class, 'reverseRepayment'])->name('family-loan-repayments.reverse');

    Route::prefix('expense-categories/{scope}')
        ->whereIn('scope', ['personal', 'work'])
        ->name('expense-categories.')
        ->group(function () {
            Route::get('/', [ExpenseCategoryController::class, 'index'])->name('index');
            Route::get('/create', [ExpenseCategoryController::class, 'create'])->name('create');
            Route::post('/', [ExpenseCategoryController::class, 'store'])->name('store');
            Route::get('/{expense_category}/edit', [ExpenseCategoryController::class, 'edit'])->name('edit');
            Route::put('/{expense_category}', [ExpenseCategoryController::class, 'update'])->name('update');
            Route::delete('/{expense_category}', [ExpenseCategoryController::class, 'destroy'])->name('destroy');
        });

    Route::resource('expenses', ExpenseController::class)->only(['index', 'create', 'store']);
    Route::post('/expenses/{expense}/reverse', [ExpenseController::class, 'reverse'])->name('expenses.reverse');

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
